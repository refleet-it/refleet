import { test } from 'node:test';
import assert from 'node:assert/strict';
import { join } from 'node:path';
import { FleetApi } from './api.js';
import type { JobRunnerDeps } from './job.js';
import { fleetOptionsFromEnv, FleetStartupError, identityFor, pollLoop, runFleet, type FleetOptions } from './loop.js';
import { collectLog, fakeFetch } from './test-support.js';
import { Updater } from './update.js';

const credentials = { apiUrl: 'https://refleet.it/api', apiKey: 'ib_key' };

test('fleetOptionsFromEnv applies the documented defaults', () => {
  const options = fleetOptionsFromEnv({ XDG_CACHE_HOME: '/xdg' }, credentials);

  assert.equal(options.apiUrl, 'https://refleet.it/api');
  assert.equal(options.heartbeatIntervalSeconds, 30);
  assert.equal(options.pollIntervalSeconds, 10);
  assert.equal(options.cacheDir, join('/xdg', 'refleet', 'workspace'));
  assert.equal(options.cacheMaxSizeMb, 5120);
  assert.notEqual(options.runnerName, '');
  assert.deepEqual(options.availableModels, { claude: [], kiro: [] });
  assert.match(options.version, /^\d+\.\d+\.\d+$/);
  assert.equal(options.autoUpdate, 'off');
});

test('fleetOptionsFromEnv reads REFLEET_AUTO_UPDATE', () => {
  assert.equal(fleetOptionsFromEnv({ REFLEET_AUTO_UPDATE: 'exit' }, credentials).autoUpdate, 'exit');
});

test('fleetOptionsFromEnv reads the RUNNER_*/WORKSPACE_*/*_AVAILABLE_MODELS variables', () => {
  const options = fleetOptionsFromEnv(
    {
      RUNNER_NAME: 'box',
      HEARTBEAT_INTERVAL_SECONDS: '5',
      POLL_INTERVAL_SECONDS: '2',
      WORKSPACE_CACHE_DIR: '/workspace/cache',
      WORKSPACE_CACHE_MAX_SIZE_MB: '100',
      CLAUDE_AVAILABLE_MODELS: 'claude-sonnet-5, claude-opus-5',
      KIRO_AVAILABLE_MODELS: '',
    },
    credentials,
  );

  assert.equal(options.runnerName, 'box');
  assert.equal(options.heartbeatIntervalSeconds, 5);
  assert.equal(options.pollIntervalSeconds, 2);
  assert.equal(options.cacheDir, '/workspace/cache');
  assert.equal(options.cacheMaxSizeMb, 100);
  assert.deepEqual(options.availableModels, { claude: ['claude-sonnet-5', 'claude-opus-5'], kiro: [] });
});

test('fleetOptionsFromEnv falls back to defaults for unparsable numbers', () => {
  const options = fleetOptionsFromEnv({ POLL_INTERVAL_SECONDS: 'soon', WORKSPACE_CACHE_MAX_SIZE_MB: '-1' }, credentials);

  assert.equal(options.pollIntervalSeconds, 10);
  assert.equal(options.cacheMaxSizeMb, 5120);
});

function options(overrides: Partial<FleetOptions> = {}): FleetOptions {
  return {
    ...fleetOptionsFromEnv({ RUNNER_NAME: 'box', CLAUDE_AVAILABLE_MODELS: 'claude-sonnet-5' }, credentials),
    ...overrides,
  };
}

test('identityFor registers one runner per engine, scoped to that engine', () => {
  assert.deepEqual(identityFor(options(), 'claude'), {
    name: 'box-claude',
    supportedModes: ['ai'],
    supportedEngines: ['claude'],
    supportedModels: ['claude-sonnet-5'],
  });
  assert.deepEqual(identityFor(options(), 'kiro'), { name: 'box-kiro', supportedModes: ['ai'], supportedEngines: ['kiro'], supportedModels: [] });
});

test('pollLoop heartbeats on its own interval, claims every cycle and stops when aborted', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 204 }));
  const { log } = collectLog();
  const deps: JobRunnerDeps = {
    api: new FleetApi('http://localhost/api', 'k', log, fetchFn),
    identity: { name: 'box-claude' },
    workspace: { cacheDir: '/unused', maxSizeMb: 1, git: () => Promise.reject(new Error('unused')), log },
    execute: () => Promise.reject(new Error('unused')),
    log,
  };

  const controller = new AbortController();
  let clock = 0;
  let cycles = 0;
  const sleepFn = (ms: number): Promise<void> => {
    clock += ms;
    if (5 === ++cycles) {
      controller.abort();
    }
    return Promise.resolve();
  };

  await pollLoop(deps, { heartbeatIntervalSeconds: 25, pollIntervalSeconds: 10 }, controller.signal, sleepFn, () => clock);

  const paths = requests.map(request => new URL(request.url).pathname);
  assert.deepEqual(paths, [
    '/api/runner/heartbeat',
    '/api/runner/jobs/claim',
    '/api/runner/jobs/claim',
    '/api/runner/jobs/claim',
    '/api/runner/heartbeat',
    '/api/runner/jobs/claim',
    '/api/runner/jobs/claim',
  ]);
});

test('pollLoop heartbeats right after a job reports fresh usage instead of waiting out the interval', async () => {
  const usage = { latest: null as null | { observedAt: string; rateLimits: null; context: null; tokens: null; cost: null } };
  let claims = 0;
  const { fetchFn, requests } = fakeFetch(request => {
    if (request.url.endsWith('/jobs/claim') && 1 === ++claims) {
      return { status: 200, body: { jobId: 'job-1', kind: 'qualification', payload: { project: { externalId: '1', path: 'a/b' }, prompt: 'p' } } };
    }
    if (request.url.endsWith('/gitlab-credentials')) {
      return { status: 200, body: { baseUrl: 'https://gitlab.example.com', accessToken: 't' } };
    }
    return { status: 204 };
  });
  const { log } = collectLog();
  const deps: JobRunnerDeps = {
    api: new FleetApi('http://localhost/api', 'k', log, fetchFn),
    identity: { name: 'box-claude' },
    workspace: { cacheDir: '/unused', maxSizeMb: 1, git: () => Promise.reject(new Error('unused')), log },
    execute: () => Promise.resolve({ output: '{"score": 4, "reasoning": "ok"}', usage: { observedAt: 'now', rateLimits: null, context: null, tokens: null, cost: null } }),
    log,
    usage,
    syncRepo: () => Promise.resolve('/cache/1'),
  };

  const controller = new AbortController();
  let cycles = 0;
  const sleepFn = (): Promise<void> => {
    if (3 === ++cycles) {
      controller.abort();
    }
    return Promise.resolve();
  };

  await pollLoop(deps, { heartbeatIntervalSeconds: 1000, pollIntervalSeconds: 10 }, controller.signal, sleepFn, () => 0);

  const heartbeats = requests.filter(request => request.url.endsWith('/heartbeat'));
  assert.equal(heartbeats.length, 2);
  assert.equal((heartbeats[0]?.body as { usage?: unknown }).usage, undefined);
  assert.equal((heartbeats[1]?.body as { usage: { observedAt: string } }).usage.observedAt, 'now');
});

test('pollLoop sends its version on heartbeat and stops, without claiming, once the updater says so', async () => {
  const { fetchFn, requests } = fakeFetch(request =>
    request.url.endsWith('/heartbeat') ? { status: 200, body: { updateRequested: true } } : { status: 204 },
  );
  const { log } = collectLog();
  const deps: JobRunnerDeps = {
    api: new FleetApi('http://localhost/api', 'k', log, fetchFn),
    identity: { name: 'box-claude' },
    workspace: { cacheDir: '/unused', maxSizeMb: 1, git: () => Promise.reject(new Error('unused')), log },
    execute: () => Promise.reject(new Error('unused')),
    log,
  };

  const stopped = await pollLoop(
    deps,
    { heartbeatIntervalSeconds: 25, pollIntervalSeconds: 10, version: '0.1.186', updater: new Updater('off', log) },
    new AbortController().signal,
    () => Promise.resolve(),
    () => 0,
  );

  assert.equal(stopped, true);
  assert.deepEqual(
    requests.map(request => new URL(request.url).pathname),
    ['/api/runner/heartbeat'],
  );
  assert.equal((requests[0]?.body as { version: string }).version, '0.1.186');
});

test('runFleet refuses to start without any detected agent', async () => {
  const controller = new AbortController();

  await assert.rejects(
    () => runFleet(options(), controller.signal, { detect: () => [], logWrite: () => undefined }),
    (err: unknown) => err instanceof FleetStartupError,
  );
});

test('runFleet registers one poll loop per detected engine and warns about a missing ANTHROPIC_API_KEY', async () => {
  const controller = new AbortController();
  const { fetchFn, requests } = fakeFetch(request => {
    if (requests.length >= 4) {
      controller.abort();
    }
    return request.url.endsWith('/claim') ? { status: 204 } : { status: 200 };
  });
  const lines: string[] = [];

  await runFleet(options({ pollIntervalSeconds: 1 }), controller.signal, {
    env: {},
    detect: () => [
      { engine: 'claude', path: '/usr/bin/claude' },
      { engine: 'kiro', path: '/usr/bin/kiro-cli' },
    ],
    execute: () => Promise.reject(new Error('unused')),
    git: () => Promise.reject(new Error('unused')),
    fetchFn,
    logWrite: line => lines.push(line),
  });

  const heartbeats = requests.filter(request => request.url.endsWith('/heartbeat')).map(request => (request.body as { name: string }).name);
  assert.deepEqual(new Set(heartbeats), new Set(['box-claude', 'box-kiro']));
  assert.ok(lines.some(line => line.includes('WARNING: claude was detected but ANTHROPIC_API_KEY is not set')));
  assert.ok(lines.some(line => line.includes('registering as box-kiro')));
});

test('runFleet installs the published version once and drains every engine loop when one heartbeat reports it outdated', async () => {
  const { fetchFn, requests } = fakeFetch(request => {
    if (request.url.endsWith('/heartbeat')) {
      const outdated = (request.body as { name: string }).name.endsWith('-claude');
      return { status: 200, body: { latestVersion: '0.1.200', updateAvailable: outdated, updateRequested: false } };
    }
    return { status: 204 };
  });
  const installs: string[] = [];

  const updating = await runFleet(options({ pollIntervalSeconds: 1, version: '0.1.186', autoUpdate: 'npm' }), new AbortController().signal, {
    env: { ANTHROPIC_API_KEY: 'x' },
    detect: () => [
      { engine: 'claude', path: '/usr/bin/claude' },
      { engine: 'kiro', path: '/usr/bin/kiro-cli' },
    ],
    execute: () => Promise.reject(new Error('unused')),
    git: () => Promise.reject(new Error('unused')),
    fetchFn,
    logWrite: () => undefined,
    install: version => {
      installs.push(version);
      return Promise.resolve();
    },
  });

  assert.equal(updating, true);
  assert.deepEqual(installs, ['0.1.200']);
  const claimsByClaude = requests.filter(request => request.url.endsWith('/claim') && (request.body as { runnerId: string }).runnerId === 'box-claude');
  assert.equal(claimsByClaude.length, 0);
});
