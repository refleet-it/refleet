import { accessSync, constants } from 'node:fs';
import { delimiter, join } from 'node:path';

export type Engine = 'claude' | 'kiro';

export interface DetectedAgent {
  engine: Engine;
  /** Resolved, absolute (or as-given, if already absolute/relative with a slash) path to the executable. */
  path: string;
}

interface AgentSpec {
  engine: Engine;
  /** Bare command name looked up on PATH when no override is set. */
  defaultCommand: string;
  /** Env var that pins an explicit path/command, bypassing PATH lookup — mirrors multica's MULTICA_*_PATH. */
  pathEnvVar: string;
}

/**
 * kiro-cli is Kiro's CLI binary name; claude is the Claude Code CLI. Scoped to these
 * two agents for now (see the Runner redesign plan) — extending to more agents means
 * adding a spec here, nothing else.
 */
const AGENT_SPECS: readonly AgentSpec[] = [
  { engine: 'claude', defaultCommand: 'claude', pathEnvVar: 'REFLEET_CLAUDE_PATH' },
  { engine: 'kiro', defaultCommand: 'kiro-cli', pathEnvVar: 'REFLEET_KIRO_PATH' },
];

export type ExecutableChecker = (path: string) => boolean;

export function defaultIsExecutable(path: string): boolean {
  try {
    accessSync(path, constants.X_OK);
    return true;
  } catch {
    return false;
  }
}

/**
 * Resolves a command to an executable path the same way a Unix shell would: if it
 * already contains a path separator, it's used as-is (only checked for
 * executability); otherwise every directory on PATH is scanned in order. Returns null
 * when nothing executable is found — the caller treats that engine as not installed.
 */
export function resolveExecutable(
  command: string,
  env: NodeJS.ProcessEnv,
  isExecutable: ExecutableChecker = defaultIsExecutable,
): string | null {
  const trimmed = command.trim();
  if ('' === trimmed) {
    return null;
  }

  if (trimmed.includes('/')) {
    return isExecutable(trimmed) ? trimmed : null;
  }

  const pathEnv = env.PATH ?? '';
  for (const dir of pathEnv.split(delimiter)) {
    if ('' === dir) {
      continue;
    }
    const candidate = join(dir, trimmed);
    if (isExecutable(candidate)) {
      return candidate;
    }
  }

  return null;
}

/**
 * Detects which of the supported agent CLIs are available on this host — the
 * auto-detection this whole runner redesign is for. Called once when `refleet
 * run` starts (see fleet/loop.ts); a CLI installed after that requires a restart, not
 * a live re-probe.
 */
export function detectAgents(
  env: NodeJS.ProcessEnv = process.env,
  isExecutable: ExecutableChecker = defaultIsExecutable,
): DetectedAgent[] {
  const detected: DetectedAgent[] = [];

  for (const spec of AGENT_SPECS) {
    const override = env[spec.pathEnvVar];
    const command = override && '' !== override.trim() ? override : spec.defaultCommand;
    const path = resolveExecutable(command, env, isExecutable);
    if (null !== path) {
      detected.push({ engine: spec.engine, path });
    }
  }

  return detected;
}

function isMainModule(): boolean {
  return undefined !== process.argv[1] && import.meta.url === `file://${process.argv[1]}`;
}

if (isMainModule()) {
  const agents = detectAgents();
  process.stdout.write(agents.map(a => a.engine).join(' '));
}
