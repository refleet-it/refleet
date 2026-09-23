import { test } from 'node:test';
import assert from 'node:assert/strict';
import type { HeartbeatResponse } from './api.js';
import { autoUpdateModeFromEnv, isReleaseVersion, Updater } from './update.js';
import { collectLog } from './test-support.js';

function response(overrides: Partial<HeartbeatResponse> = {}): HeartbeatResponse {
  return { latestVersion: '0.1.200', updateAvailable: false, updateRequested: false, ...overrides };
}

test('autoUpdateModeFromEnv defaults to npm for an npm install and off everywhere else', () => {
  assert.equal(autoUpdateModeFromEnv({}, true), 'npm');
  assert.equal(autoUpdateModeFromEnv({}, false), 'off');
  assert.equal(autoUpdateModeFromEnv({ REFLEET_AUTO_UPDATE: 'exit' }, false), 'exit');
  assert.equal(autoUpdateModeFromEnv({ REFLEET_AUTO_UPDATE: 'false' }, true), 'off');
  assert.equal(autoUpdateModeFromEnv({ REFLEET_AUTO_UPDATE: 'NPM' }, false), 'npm');
  assert.equal(autoUpdateModeFromEnv({ REFLEET_AUTO_UPDATE: 'whatever' }, true), 'npm');
});

test('nothing happens without a heartbeat answer or while the runner is current', async () => {
  const installs: string[] = [];
  const updater = new Updater('npm', collectLog().log, version => {
    installs.push(version);
    return Promise.resolve();
  });

  assert.equal(await updater.afterHeartbeat(null), false);
  assert.equal(await updater.afterHeartbeat(response()), false);
  assert.deepEqual(installs, []);
});

test('npm mode installs the published version and asks to stop', async () => {
  const installs: string[] = [];
  const { log, lines } = collectLog();
  const updater = new Updater('npm', log, version => {
    installs.push(version);
    return Promise.resolve();
  });

  assert.equal(await updater.afterHeartbeat(response({ updateAvailable: true })), true);
  assert.deepEqual(installs, ['0.1.200']);
  assert.ok(lines.some(line => line.includes('installed @refleet-it/runner@0.1.200')));
});

test('a failed install keeps the runner going and is not retried for an hour', async () => {
  let clock = 0;
  const installs: string[] = [];
  const { log, lines } = collectLog();
  const updater = new Updater(
    'npm',
    log,
    version => {
      installs.push(version);
      return Promise.reject(new Error('EACCES'));
    },
    () => clock,
  );

  assert.equal(await updater.afterHeartbeat(response({ updateAvailable: true })), false);
  clock = 30 * 60 * 1000;
  assert.equal(await updater.afterHeartbeat(response({ updateAvailable: true })), false);
  clock = 61 * 60 * 1000;
  assert.equal(await updater.afterHeartbeat(response({ updateAvailable: true })), false);

  assert.equal(installs.length, 2);
  assert.ok(lines.some(line => line.includes('WARNING: could not install @refleet-it/runner@0.1.200: EACCES')));
});

test('exit mode stops without installing; off mode ignores an available update', async () => {
  const installs: string[] = [];
  const install = (version: string): Promise<void> => {
    installs.push(version);
    return Promise.resolve();
  };

  assert.equal(await new Updater('exit', collectLog().log, install).afterHeartbeat(response({ updateAvailable: true })), true);
  assert.equal(await new Updater('off', collectLog().log, install).afterHeartbeat(response({ updateAvailable: true })), false);
  assert.deepEqual(installs, []);
});

test('an update requested from the dashboard stops the runner in every mode, installing first only in npm mode', async () => {
  const installs: string[] = [];
  const install = (version: string): Promise<void> => {
    installs.push(version);
    return Promise.reject(new Error('EACCES'));
  };

  assert.equal(await new Updater('off', collectLog().log, install).afterHeartbeat(response({ updateRequested: true })), true);
  assert.equal(await new Updater('exit', collectLog().log, install).afterHeartbeat(response({ updateRequested: true })), true);
  assert.deepEqual(installs, []);

  assert.equal(await new Updater('npm', collectLog().log, install).afterHeartbeat(response({ updateRequested: true })), true);
  assert.deepEqual(installs, ['0.1.200']);
});

test('only a plain release number is a version npm may be asked for', () => {
  assert.equal(isReleaseVersion('0.1.200'), true);
  assert.equal(isReleaseVersion('latest'), false);
  assert.equal(isReleaseVersion('^0.1.0'), false);
  assert.equal(isReleaseVersion('npm:evil-package@1.0.0'), false);
  assert.equal(isReleaseVersion('0.1.200 --ignore-scripts'), false);
  assert.equal(isReleaseVersion(''), false);
});

test('a heartbeat naming anything but a release version is never handed to npm', async () => {
  const installs: string[] = [];
  const { log, lines } = collectLog();
  const updater = new Updater('npm', log, version => {
    installs.push(version);
    return Promise.resolve();
  });

  assert.equal(await updater.afterHeartbeat(response({ updateAvailable: true, latestVersion: 'npm:evil-package@1.0.0' })), false);
  assert.equal(await updater.afterHeartbeat(response({ updateRequested: true, latestVersion: 'latest' })), true);

  assert.deepEqual(installs, []);
  assert.ok(lines.some(line => line.includes('refusing to install @refleet-it/runner@"npm:evil-package@1.0.0"')));
});
