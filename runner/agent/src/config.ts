import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { join } from 'node:path';

export interface StoredCredentials {
  apiUrl: string;
  apiKey: string;
  apiKeyId: string;
  accountEmail: string;
  createdAt: string;
}

export interface FleetConfig {
  apiUrl: string;
  apiKey: string;
  source: 'env' | 'file';
}

/**
 * Local login credentials saved by `refleet login`, read back by `whoami`/
 * `logout` and used by `run` when REFLEET_API_URL/REFLEET_API_KEY aren't set.
 * Lives under XDG_CONFIG_HOME (falling back to ~/.config, the convention
 * most CLI tools on the host already follow) rather than inside this repo, since
 * it's per-machine/per-person, not project state. `baseDir` overrides the whole
 * resolution for tests, same DI shape as detectAgents' injectable `isExecutable`.
 */
function configDir(baseDir?: string): string {
  if (baseDir) {
    return baseDir;
  }
  const xdgConfigHome = process.env.XDG_CONFIG_HOME;
  const base = xdgConfigHome && '' !== xdgConfigHome ? xdgConfigHome : join(homedir(), '.config');
  return join(base, 'refleet');
}

function configPath(baseDir?: string): string {
  return join(configDir(baseDir), 'runner.json');
}

export function loadStoredConfig(baseDir?: string): StoredCredentials | null {
  const path = configPath(baseDir);
  if (!existsSync(path)) {
    return null;
  }
  return JSON.parse(readFileSync(path, 'utf8')) as StoredCredentials;
}

export function saveStoredConfig(config: StoredCredentials, baseDir?: string): void {
  mkdirSync(configDir(baseDir), { recursive: true, mode: 0o700 });
  writeFileSync(configPath(baseDir), JSON.stringify(config, null, 2) + '\n', { mode: 0o600 });
}

export function clearStoredConfig(baseDir?: string): void {
  const path = configPath(baseDir);
  if (existsSync(path)) {
    rmSync(path);
  }
}

/**
 * REFLEET_API_URL/REFLEET_API_KEY (automatic/cloud mode: Docker, CI, any headless
 * deployment) always win over a locally saved `login`; the stored file is only
 * consulted when both env vars are absent. Returns null when neither source has
 * credentials.
 */
export function resolveFleetConfig(env: NodeJS.ProcessEnv, baseDir?: string): FleetConfig | null {
  const envUrl = env.REFLEET_API_URL;
  const envKey = env.REFLEET_API_KEY;
  if (envUrl && envKey) {
    return { apiUrl: envUrl, apiKey: envKey, source: 'env' };
  }

  const stored = loadStoredConfig(baseDir);
  if (stored) {
    return { apiUrl: stored.apiUrl, apiKey: stored.apiKey, source: 'file' };
  }

  return null;
}
