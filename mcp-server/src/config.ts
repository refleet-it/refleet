import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** dist/config.js -> mcp-server/dist -> mcp-server -> refleet (repo root) */
export const REPO_ROOT = path.resolve(__dirname, '..', '..');

export const SERVICES = ['backend', 'worker', 'database', 'frontend', 'mail'] as const;
export type Service = (typeof SERVICES)[number];

/**
 * Services docker_compose_exec is allowed to touch. `database` and `mail` are
 * intentionally excluded so this server can never be used
 * to read/write application data directly - only backend/frontend dev tooling.
 */
export const EXEC_SERVICES = ['backend', 'frontend'] as const satisfies readonly Service[];
export type ExecService = (typeof EXEC_SERVICES)[number];

/**
 * Whitelisted `make` targets. Deliberately excludes `clean`, `init`, and
 * `db-recreate`, which drop database schemas and/or wipe docker volumes -
 * those stay a manual, human-run action.
 */
export const MAKE_TARGETS = [
  'lint',
  'quality-check',
  'phpcsfixer',
  'phpstan',
  'rector',
  'test',
  'test-coverage',
  'lighthouse',
  'start',
  'xdebug-on',
  'xdebug-off',
] as const;

/** quality-check runs ~10 tools back to back; give it room before timing out. */
export const DEFAULT_TIMEOUT_MS = 15 * 60 * 1000;
export const SHORT_TIMEOUT_MS = 30_000;
export const MEDIUM_TIMEOUT_MS = 120_000;
