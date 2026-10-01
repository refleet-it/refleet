#!/usr/bin/env node
import { realpathSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { clearStoredConfig, loadStoredConfig, resolveFleetConfig } from './config.js';
import { listApiKeys, revokeApiKey } from './backend-client.js';
import { FleetStartupError, fleetOptionsFromEnv, runFleet } from './fleet/loop.js';
import { UPDATE_EXIT_CODE } from './fleet/update.js';
import { DEFAULT_API_URL, parseLoginArgs, runLogin } from './login.js';
import { runnerVersion } from './version.js';

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : String(err);
}

/**
 * The one list of commands: main's switch dispatches these and usageText renders them, so a
 * command cannot exist undocumented — which is what a hand-maintained usage string drifts into.
 */
export const COMMANDS = [
  ['run', 'Claim jobs and work them until stopped'],
  ['login', 'Authorize in a browser and store an API key'],
  ['logout', 'Revoke the stored API key and forget it'],
  ['whoami', 'Show the credentials in effect and check they still work'],
  ['version', 'Print the runner version'],
  ['help', 'Show this help'],
] as const;

export function usageText(): string {
  const width = Math.max(...COMMANDS.map(([name]) => name.length));
  const commands = COMMANDS.map(([name, summary]) => `  ${name.padEnd(width)}  ${summary}`).join('\n');

  return `refleet ${runnerVersion()} — a Refleet runner: claims modernisation jobs and works them with Claude Code or Kiro.

Usage: refleet <command> [options]

Commands:
${commands}

Options for login:
  --api-url <url>  Instance to authorize against (default: ${DEFAULT_API_URL})
  --no-browser     Only print the link, for SSH sessions and machines without a desktop

Credentials, set directly or obtained by 'refleet login':
  REFLEET_API_URL  Instance API, e.g. ${DEFAULT_API_URL}
  REFLEET_API_KEY  API key for that instance

Also read from the environment:
  RUNNER_NAME        How this runner registers (default: this machine's short hostname)
  ANTHROPIC_API_KEY  Required for jobs that run Claude Code
  KIRO_API_KEY       Required for jobs that run Kiro

'run' needs git and at least one agent CLI — claude or kiro-cli — on PATH, and registers only
the engines it finds. Full guide: docs/runner/installation.md
`;
}

/** Revokes the locally saved key on the backend (best-effort) and clears the local config. */
async function runLogout(): Promise<void> {
  const existing = loadStoredConfig();
  if (!existing) {
    process.stdout.write('Not logged in.\n');
    return;
  }

  try {
    await revokeApiKey(existing.apiUrl, existing.apiKey, existing.apiKeyId);
  } catch (err) {
    process.stderr.write(`warning: failed to revoke the API key on the backend: ${errorMessage(err)}\n`);
  }

  clearStoredConfig();
  process.stdout.write('Logged out.\n');
}

/** Shows which credentials are currently effective and whether they still work. */
async function runWhoami(): Promise<void> {
  const resolved = resolveFleetConfig(process.env);
  if (!resolved) {
    process.stdout.write("Not logged in, and REFLEET_API_URL/REFLEET_API_KEY aren't set. Run 'refleet login'.\n");
    process.exitCode = 1;
    return;
  }

  const sourceLabel = 'env' === resolved.source ? 'environment variables (automatic mode)' : 'refleet login';
  process.stdout.write(`API URL: ${resolved.apiUrl}\n`);
  process.stdout.write(`API key: ${resolved.apiKey.slice(0, 12)}…\n`);
  process.stdout.write(`Source:  ${sourceLabel}\n`);

  try {
    const keys = await listApiKeys(resolved.apiUrl, resolved.apiKey);
    process.stdout.write(`Connected (${String(keys.length)} API key(s) on this account).\n`);
  } catch (err) {
    process.stdout.write(`Could not reach the backend, or this key is no longer valid: ${errorMessage(err)}\n`);
    process.exitCode = 1;
  }
}

/**
 * The fleet loop itself: REFLEET_API_URL/REFLEET_API_KEY (automatic/cloud mode: Docker,
 * CI, any headless deployment) win over a locally saved `login`; without either there
 * is no idle mode — the process exits so a misconfigured runner is noticed, not left
 * silently waiting. SIGINT/SIGTERM finish the job in progress and then return. A stop
 * for an update exits with UPDATE_EXIT_CODE, non-zero on purpose: `Restart=on-failure`
 * and `restart: on-failure` count it as a restart, `Restart=always` does anyway.
 */
async function runRunner(): Promise<void> {
  const credentials = resolveFleetConfig(process.env);
  if (!credentials) {
    throw new Error(
      "REFLEET_API_URL and REFLEET_API_KEY are both required — this runner only supports fleet mode. Run 'refleet login' (or set them directly) first.",
    );
  }

  const controller = new AbortController();
  const stop = (): void => {
    process.stdout.write('refleet: shutting down after the current job\n');
    controller.abort();
  };
  process.once('SIGINT', stop);
  process.once('SIGTERM', stop);

  try {
    if (await runFleet(fleetOptionsFromEnv(process.env, credentials), controller.signal)) {
      process.stdout.write('refleet: stopping for an update — restart to run the new version\n');
      process.exitCode = UPDATE_EXIT_CODE;
    }
  } catch (err) {
    if (err instanceof FleetStartupError) {
      throw err;
    }
    // Reached only through an unexpected exception inside a poll loop: exit non-zero so
    // the supervisor (Docker restart policy, systemd) restarts the whole runner.
    process.stderr.write(`refleet: fleet loop died: ${errorMessage(err)}\n`);
    process.exitCode = 1;
  }
}

async function main(): Promise<void> {
  const command = process.argv[2];

  switch (command) {
    case 'login':
      await runLogin(parseLoginArgs(process.argv.slice(3), process.env));
      return;
    case 'logout':
      await runLogout();
      return;
    case 'whoami':
      await runWhoami();
      return;
    case 'run':
      await runRunner();
      return;
    case 'version':
    case '--version':
    case '-v':
      process.stdout.write(`${runnerVersion()}\n`);
      return;
    case 'help':
    case '--help':
    case '-h':
      process.stdout.write(usageText());
      return;
    case undefined:
      // Asked for nothing: the help belongs on stderr and the exit non-zero, so a supervisor
      // or a script that forgot the command does not read it as a successful run.
      process.stderr.write(usageText());
      process.exitCode = 1;
      return;
    default:
      process.stderr.write(`refleet: unknown command ${JSON.stringify(command)}\n\n`);
      process.stderr.write(usageText());
      process.exitCode = 1;
  }
}

/**
 * npm links bin entries as symlinks, so argv[1] is node_modules/.bin/refleet while
 * import.meta.url is the resolved dist/cli.js — comparing them raw made the installed CLI a
 * silent no-op. Resolve the symlink, and build the URL rather than concatenating it so paths
 * with spaces compare correctly too.
 */
function isMainModule(): boolean {
  const entry = process.argv[1];
  if (undefined === entry) {
    return false;
  }

  try {
    return import.meta.url === pathToFileURL(realpathSync(entry)).href;
  } catch {
    return false;
  }
}

if (isMainModule()) {
  main().catch((err: unknown) => {
    process.stderr.write(`refleet: ${errorMessage(err)}\n`);
    process.exitCode = 1;
  });
}
