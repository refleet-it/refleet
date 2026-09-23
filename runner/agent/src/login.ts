import { spawn } from 'node:child_process';
import { hostname } from 'node:os';
import {
  claimCliAuthorization,
  revokeApiKey,
  startCliAuthorization,
  type ClaimedCliAuthorization,
  type FetchFn,
  type StartedCliAuthorization,
} from './backend-client.js';
import { loadStoredConfig, saveStoredConfig, type StoredCredentials } from './config.js';

export const DEFAULT_API_URL = 'https://api.refleet.it/api';

export interface LoginOptions {
  apiUrl: string;
  openBrowser: boolean;
}

/**
 * `refleet login [--api-url <url>] [--no-browser]`. The URL falls back to REFLEET_API_URL
 * and then to production, so a plain `refleet login` just works and a self-hosted or
 * local stack is one flag away. `--no-browser` only prints the link, for SSH sessions
 * and machines without a desktop — the link opens fine from any other device.
 */
export function parseLoginArgs(args: readonly string[], env: NodeJS.ProcessEnv): LoginOptions {
  let apiUrl = env.REFLEET_API_URL?.trim() || DEFAULT_API_URL;
  let openBrowser = true;

  for (let i = 0; i < args.length; i++) {
    const arg = args[i] ?? '';
    if ('--no-browser' === arg) {
      openBrowser = false;
    } else if ('--api-url' === arg) {
      const value = args[i + 1];
      if (undefined === value || value.startsWith('--')) {
        throw new Error('--api-url needs a value, e.g. --api-url https://api.refleet.it/api');
      }
      apiUrl = value;
      i++;
    } else if (arg.startsWith('--api-url=')) {
      apiUrl = arg.slice('--api-url='.length);
    } else {
      throw new Error(`unknown option ${JSON.stringify(arg)}\nUsage: refleet login [--api-url <url>] [--no-browser]`);
    }
  }

  if ('' === apiUrl) {
    throw new Error('--api-url must not be empty');
  }

  return { apiUrl, openBrowser };
}

/**
 * Best-effort: a failure here is not a failure of the login, the URL is printed either
 * way. Detached and unref'd so a browser that outlives the CLI never keeps it alive.
 */
export function openInBrowser(url: string, platform: NodeJS.Platform = process.platform): boolean {
  const [command, args] =
    'darwin' === platform
      ? ['open', [url]]
      : 'win32' === platform
        ? ['cmd', ['/c', 'start', '', url]]
        : ['xdg-open', [url]];

  try {
    const child = spawn(command, args, { detached: true, stdio: 'ignore' });
    child.on('error', () => undefined);
    child.unref();
    return true;
  } catch {
    return false;
  }
}

export interface LoginDeps {
  fetchFn: FetchFn;
  sleep: (ms: number) => Promise<void>;
  open: (url: string) => boolean;
  now: () => number;
  stdout: (line: string) => void;
  stderr: (line: string) => void;
  runnerName: string;
  configDir?: string;
}

const defaultDeps: LoginDeps = {
  fetchFn: fetch,
  sleep: ms => new Promise(resolve => setTimeout(resolve, ms)),
  open: openInBrowser,
  now: Date.now,
  stdout: line => process.stdout.write(`${line}\n`),
  stderr: line => process.stderr.write(`${line}\n`),
  runnerName: hostname(),
};

/**
 * Start → show the link → poll until the browser answers → store the key. Revokes the
 * key of a previous local login first (best-effort) so re-running `login` doesn't
 * litter the account's key list; the new key is saved before that so a failed revoke
 * can never leave the machine with nothing.
 */
export async function runLogin(options: LoginOptions, overrides: Partial<LoginDeps> = {}): Promise<StoredCredentials> {
  const deps = { ...defaultDeps, ...overrides };
  const existing = loadStoredConfig(deps.configDir);

  const started = await startCliAuthorization(options.apiUrl, deps.runnerName, deps.fetchFn);
  announce(started, options, deps);

  const claimed = await waitForDecision(options.apiUrl, started, deps);

  const credentials: StoredCredentials = {
    apiUrl: options.apiUrl,
    apiKey: claimed.apiKey.token,
    apiKeyId: claimed.apiKey.id,
    accountEmail: claimed.accountEmail,
    createdAt: claimed.apiKey.createdAt,
  };
  saveStoredConfig(credentials, deps.configDir);

  if (existing) {
    try {
      await revokeApiKey(existing.apiUrl, existing.apiKey, existing.apiKeyId, deps.fetchFn);
    } catch (err) {
      deps.stderr(`warning: failed to revoke the previous local API key: ${err instanceof Error ? err.message : String(err)}`);
    }
  }

  deps.stdout(`Logged in as ${claimed.accountEmail}. API key ${claimed.apiKey.prefix}… saved to your local config.`);
  deps.stdout("Run 'refleet run' to start the runner.");

  return credentials;
}

function announce(started: StartedCliAuthorization, options: LoginOptions, deps: LoginDeps): void {
  const opened = options.openBrowser && deps.open(started.verificationUrl);

  deps.stdout(opened ? 'Opening your browser to finish logging in. If nothing happened, open this link:' : 'Open this link in a browser to finish logging in:');
  deps.stdout('');
  deps.stdout(`    ${started.verificationUrl}`);
  deps.stdout('');
  deps.stdout(`Waiting for approval (this link expires ${describeExpiry(started.expiresAt, deps.now())})…`);
}

async function waitForDecision(
  apiUrl: string,
  started: StartedCliAuthorization,
  deps: LoginDeps,
): Promise<Extract<ClaimedCliAuthorization, { status: 'approved' }>> {
  const intervalMs = Math.max(1, started.pollIntervalSeconds) * 1000;
  const apiKeyName = `refleet-runner (${deps.runnerName})`;

  for (;;) {
    await deps.sleep(intervalMs);
    const claimed = await claimCliAuthorization(apiUrl, started.deviceSecret, apiKeyName, deps.fetchFn);

    switch (claimed.status) {
      case 'approved':
        return claimed;
      case 'denied':
        throw new Error('login denied in the browser');
      case 'expired':
        throw new Error("login link expired before it was approved — run 'refleet login' again");
      case 'pending':
        break;
    }
  }
}

function describeExpiry(expiresAt: string, now: number): string {
  const minutes = Math.round((Date.parse(expiresAt) - now) / 60_000);
  return Number.isFinite(minutes) && minutes > 0 ? `in ${String(minutes)} min` : 'soon';
}
