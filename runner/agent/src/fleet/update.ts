import { execFile } from 'node:child_process';
import type { HeartbeatResponse } from './api.js';
import type { Logger } from './log.js';

/**
 * What the runner does when a heartbeat says it is behind the published version:
 * - `npm`: reinstall itself with `npm install -g @refleet-it/runner@<latest>` and stop, so
 *   the supervisor's restart runs the new code. Only meaningful for an npm install.
 * - `exit`: just stop and leave the update to whatever restarts it — `npx
 *   @refleet-it/runner@latest`, a Watchtower-managed container, a wrapper that pulls.
 * - `off`: keep running; the dashboard still shows it as outdated.
 * An update requested from the dashboard stops the runner in every mode.
 */
export type AutoUpdateMode = 'npm' | 'exit' | 'off';

/** The exit code of a runner that stopped to be restarted on a newer version. */
export const UPDATE_EXIT_CODE = 75;

const PACKAGE = '@refleet-it/runner';

const INSTALL_TIMEOUT_MS = 5 * 60 * 1000;

/** A failed install is retried no sooner than this, so a broken npm never turns into a tight loop. */
const RETRY_AFTER_MS = 60 * 60 * 1000;

export type Installer = (version: string) => Promise<void>;

/**
 * `npm install <pkg>@<spec>` reads far more than a version into the spec — a dist-tag, a
 * range, and `npm:<other-package>@<version>`, which installs a different package under
 * this one's name. The heartbeat's `latestVersion` is a plain release number, so anything
 * else is refused before it reaches npm: whoever can answer a heartbeat (or sit between the
 * runner and the API) must not be able to pick what gets executed on this host.
 */
const RELEASE_VERSION = /^\d+\.\d+\.\d+$/;

export function isReleaseVersion(version: string): boolean {
  return RELEASE_VERSION.test(version);
}

export function autoUpdateModeFromEnv(env: NodeJS.ProcessEnv, npmInstalled: boolean): AutoUpdateMode {
  const value = (env.REFLEET_AUTO_UPDATE ?? '').trim().toLowerCase();
  if ('npm' === value || 'exit' === value || 'off' === value) {
    return value;
  }
  if ('false' === value || '0' === value || 'no' === value) {
    return 'off';
  }
  return npmInstalled ? 'npm' : 'off';
}

export function installFromNpm(version: string): Promise<void> {
  return new Promise((resolve, reject) => {
    execFile('npm', ['install', '-g', `${PACKAGE}@${version}`, '--no-audit', '--no-fund'], { timeout: INSTALL_TIMEOUT_MS }, (err, _stdout, stderr) => {
      if (err) {
        reject(new Error(`${err.message}${stderr ? `\n${stderr.trim()}` : ''}`));
        return;
      }
      resolve();
    });
  });
}

/**
 * Shared by every engine loop in the process: one decision, one retry clock. Returns
 * true when the whole runner should stop after the jobs in progress so its supervisor
 * restarts it — on the new version, if this side could install it.
 */
export class Updater {
  private retryAfter = 0;

  constructor(
    private readonly mode: AutoUpdateMode,
    private readonly log: Logger,
    private readonly install: Installer = installFromNpm,
    private readonly now: () => number = Date.now,
  ) {}

  async afterHeartbeat(response: HeartbeatResponse | null): Promise<boolean> {
    if (!response) {
      return false;
    }

    if (response.updateRequested) {
      this.log('update requested from the dashboard — stopping after the current jobs');
      if ('npm' === this.mode && response.latestVersion) {
        await this.tryInstall(response.latestVersion);
      }
      return true;
    }

    if (!response.updateAvailable || !response.latestVersion || 'off' === this.mode) {
      return false;
    }

    if ('exit' === this.mode) {
      this.log(`version ${response.latestVersion} is available — stopping so the supervisor restarts on it`);
      return true;
    }

    if (this.now() < this.retryAfter) {
      return false;
    }

    if (await this.tryInstall(response.latestVersion)) {
      return true;
    }

    this.retryAfter = this.now() + RETRY_AFTER_MS;
    return false;
  }

  private async tryInstall(version: string): Promise<boolean> {
    if (!isReleaseVersion(version)) {
      this.log(`WARNING: refusing to install ${PACKAGE}@${JSON.stringify(version)}: not a release version`);
      return false;
    }

    this.log(`installing ${PACKAGE}@${version}`);
    try {
      await this.install(version);
      this.log(`installed ${PACKAGE}@${version} — stopping after the current jobs so the supervisor restarts on it`);
      return true;
    } catch (err) {
      this.log(`WARNING: could not install ${PACKAGE}@${version}: ${err instanceof Error ? err.message : String(err)}`);
      return false;
    }
  }
}
