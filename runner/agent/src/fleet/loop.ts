import { homedir, hostname } from 'node:os';
import { join } from 'node:path';
import type { FetchFn } from '../backend-client.js';
import type { AgentUsage } from '../backends/types.js';
import { detectAgents, type DetectedAgent, type Engine } from '../detect.js';
import { FleetApi, type RunnerIdentity } from './api.js';
import { executeJob, type JobExecutor } from './execute.js';
import { createGitRunner, type GitRunner } from './git.js';
import { claimAndRunJob, type JobRunnerDeps } from './job.js';
import { createLogger, type Logger } from './log.js';
import { autoUpdateModeFromEnv, type AutoUpdateMode, type Installer, Updater } from './update.js';
import type { Workspace } from './workspace.js';
import { isNpmInstalled, runnerVersion } from '../version.js';

export interface FleetOptions {
  apiUrl: string;
  apiKey: string;
  runnerName: string;
  heartbeatIntervalSeconds: number;
  pollIntervalSeconds: number;
  cacheDir: string;
  cacheMaxSizeMb: number;
  /** Operator-declared per engine (CLAUDE_AVAILABLE_MODELS / KIRO_AVAILABLE_MODELS): neither CLI can list its models at runtime. */
  availableModels: Partial<Record<Engine, string[]>>;
  version: string;
  autoUpdate: AutoUpdateMode;
}

function positiveInt(value: string | undefined, fallback: number): number {
  const parsed = Number.parseInt(value ?? '', 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

function commaList(value: string | undefined): string[] {
  return (value ?? '')
    .split(',')
    .map(item => item.trim())
    .filter(item => '' !== item);
}

function shortHostname(): string {
  const name = hostname().split('.')[0];
  return name && '' !== name ? name : 'local-runner';
}

/**
 * The Docker image pins WORKSPACE_CACHE_DIR to /workspace/cache (its mounted volume);
 * an npm install has no such mount, so it defaults to the XDG cache dir — the same
 * ~/.cache convention the login config already follows with ~/.config.
 */
function defaultCacheDir(env: NodeJS.ProcessEnv): string {
  const xdgCacheHome = env.XDG_CACHE_HOME;
  const base = xdgCacheHome && '' !== xdgCacheHome ? xdgCacheHome : join(homedir(), '.cache');
  return join(base, 'refleet', 'workspace');
}

export function fleetOptionsFromEnv(env: NodeJS.ProcessEnv, credentials: { apiUrl: string; apiKey: string }): FleetOptions {
  return {
    apiUrl: credentials.apiUrl,
    apiKey: credentials.apiKey,
    runnerName: env.RUNNER_NAME && '' !== env.RUNNER_NAME ? env.RUNNER_NAME : shortHostname(),
    heartbeatIntervalSeconds: positiveInt(env.HEARTBEAT_INTERVAL_SECONDS, 30),
    pollIntervalSeconds: positiveInt(env.POLL_INTERVAL_SECONDS, 10),
    cacheDir: env.WORKSPACE_CACHE_DIR && '' !== env.WORKSPACE_CACHE_DIR ? env.WORKSPACE_CACHE_DIR : defaultCacheDir(env),
    cacheMaxSizeMb: positiveInt(env.WORKSPACE_CACHE_MAX_SIZE_MB, 5120),
    availableModels: {
      claude: commaList(env.CLAUDE_AVAILABLE_MODELS),
      kiro: commaList(env.KIRO_AVAILABLE_MODELS),
    },
    version: runnerVersion(),
    autoUpdate: autoUpdateModeFromEnv(env, isNpmInstalled()),
  };
}

/**
 * One identity per detected engine, each registering as its own Runner
 * (`<name>-<engine>`) scoped to just that engine — see
 * DoctrineRunnerJobRepository::CLAIM_NEXT_SQL, which filters claims by engine. A host
 * with both claude and kiro shows up as two independent Runners instead of one that
 * silently mixes both.
 */
export function identityFor(options: FleetOptions, engine: Engine): RunnerIdentity {
  return {
    name: `${options.runnerName}-${engine}`,
    supportedModes: ['ai'],
    supportedEngines: [engine],
    supportedModels: options.availableModels[engine] ?? [],
  };
}

export type SleepFn = (ms: number, signal: AbortSignal) => Promise<void>;

export function sleep(ms: number, signal: AbortSignal): Promise<void> {
  return new Promise(resolve => {
    if (signal.aborted) {
      resolve();
      return;
    }
    const timer = setTimeout(done, ms);
    function done(): void {
      clearTimeout(timer);
      signal.removeEventListener('abort', done);
      resolve();
    }
    signal.addEventListener('abort', done, { once: true });
  });
}

export interface PollLoopTiming {
  heartbeatIntervalSeconds: number;
  pollIntervalSeconds: number;
}

export interface PollLoopOptions extends PollLoopTiming {
  version?: string;
  /** Shared across the process's loops; absent in tests that only care about polling. */
  updater?: Updater;
}

/**
 * Heartbeats and claims until `signal` aborts; a job in progress is finished first. A
 * job that reported fresh usage triggers an extra heartbeat right away, so the
 * dashboard does not wait out the rest of the interval for figures it already has.
 * Resolves true when a heartbeat answered with an update this runner should restart
 * for — decided between jobs, so nothing is ever cut short.
 */
export async function pollLoop(
  deps: JobRunnerDeps,
  options: PollLoopOptions,
  signal: AbortSignal,
  sleepFn: SleepFn = sleep,
  now: () => number = Date.now,
): Promise<boolean> {
  const heartbeatMs = options.heartbeatIntervalSeconds * 1000;
  let lastHeartbeat: number | null = null;
  let lastSentUsage: AgentUsage | null = null;

  deps.log(`polling for jobs every ${String(options.pollIntervalSeconds)}s`);

  while (!signal.aborted) {
    const usage = deps.usage?.latest ?? null;
    if (null === lastHeartbeat || now() - lastHeartbeat >= heartbeatMs || usage !== lastSentUsage) {
      const response = await deps.api.heartbeat(deps.identity, usage, options.version ?? null);
      lastHeartbeat = now();
      lastSentUsage = usage;
      if (options.updater && (await options.updater.afterHeartbeat(response))) {
        return true;
      }
    }

    await claimAndRunJob(deps);

    await sleepFn(options.pollIntervalSeconds * 1000, signal);
  }

  return false;
}

export interface RunFleetDeps {
  env?: NodeJS.ProcessEnv;
  detect?: () => DetectedAgent[];
  execute?: JobExecutor;
  git?: GitRunner;
  fetchFn?: FetchFn;
  logWrite?: (line: string) => void;
  install?: Installer;
}

export class FleetStartupError extends Error {}

/**
 * Detects the installed agent CLIs, registers one Runner per engine and polls until
 * `signal` aborts. Rejects as soon as any engine's loop dies of an unexpected error
 * (Docker's `restart: unless-stopped` or a systemd unit brings the whole process
 * back), rather than silently continuing with fewer runners than were detected.
 * Resolves true when the runner stopped for an update: the first loop to learn of it
 * drains the others, which finish their jobs in progress first.
 */
export async function runFleet(options: FleetOptions, signal: AbortSignal, deps: RunFleetDeps = {}): Promise<boolean> {
  const env = deps.env ?? process.env;
  const startupLog: Logger = createLogger(options.runnerName, deps.logWrite);

  const detected = (deps.detect ?? detectAgents)();
  if (0 === detected.length) {
    throw new FleetStartupError('no supported agent CLI (claude/kiro-cli) found on PATH');
  }
  startupLog(`version ${options.version}, auto-update ${options.autoUpdate}`);
  startupLog(`detected agent CLIs: ${detected.map(agent => agent.engine).join(' ')}`);

  if (detected.some(agent => 'claude' === agent.engine) && !env.ANTHROPIC_API_KEY) {
    startupLog('WARNING: claude was detected but ANTHROPIC_API_KEY is not set — claude-engine jobs will fail until it is.');
  }

  const git = deps.git ?? createGitRunner();
  const updater = new Updater(options.autoUpdate, startupLog, deps.install);
  const drain = new AbortController();
  signal.addEventListener('abort', () => drain.abort(), { once: true });
  let updating = false;

  const loops = detected.map(async agent => {
    const identity = identityFor(options, agent.engine);
    const log = createLogger(identity.name, deps.logWrite);
    const workspace: Workspace = { cacheDir: options.cacheDir, maxSizeMb: options.cacheMaxSizeMb, git, log };
    const jobDeps: JobRunnerDeps = {
      api: new FleetApi(options.apiUrl, options.apiKey, log, deps.fetchFn),
      identity,
      workspace,
      execute: deps.execute ?? executeJob,
      log,
      usage: { latest: null },
      fetchFn: deps.fetchFn,
    };
    log(`registering as ${identity.name} against ${options.apiUrl}`);
    if (await pollLoop(jobDeps, { ...options, updater }, drain.signal)) {
      updating = true;
      drain.abort();
    }
  });

  await Promise.all(loops);

  return updating;
}
