import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/**
 * What package.json says. The release workflow stamps `<major>.<minor>.<github run number>` at
 * publish time, so a checkout reports the bare series with patch 0 while an installed copy
 * reports a real patch — bump the minor in package.json to open a new series.
 */
export function runnerVersion(): string {
  try {
    const pkg = JSON.parse(readFileSync(new URL('../package.json', import.meta.url), 'utf8')) as { version?: unknown };
    return 'string' === typeof pkg.version ? pkg.version : '0.0.0';
  } catch {
    return '0.0.0';
  }
}

/**
 * True when this code was installed by npm (globally or under an npx cache) rather
 * than run from a checkout or the Docker image — the only case where reinstalling the
 * package actually replaces the code the next start runs.
 */
export function isNpmInstalled(moduleUrl: string = import.meta.url): boolean {
  try {
    return fileURLToPath(moduleUrl).split(/[\\/]/).includes('node_modules');
  } catch {
    return false;
  }
}
