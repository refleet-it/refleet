import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import type { FetchFn } from './backend-client.js';
import { loadStoredConfig, saveStoredConfig } from './config.js';
import { DEFAULT_API_URL, parseLoginArgs, runLogin, type LoginDeps } from './login.js';

test('parseLoginArgs defaults to production and opens the browser', () => {
  assert.deepEqual(parseLoginArgs([], {}), { apiUrl: DEFAULT_API_URL, openBrowser: true });
});

test('parseLoginArgs takes the API URL from the environment before the default', () => {
  assert.equal(parseLoginArgs([], { REFLEET_API_URL: 'http://localhost/api' }).apiUrl, 'http://localhost/api');
});

test('parseLoginArgs lets --api-url (both spellings) win over the environment', () => {
  const env = { REFLEET_API_URL: 'http://localhost/api' };
  assert.equal(parseLoginArgs(['--api-url', 'https://api.example.test/api'], env).apiUrl, 'https://api.example.test/api');
  assert.equal(parseLoginArgs(['--api-url=https://other.test/api'], env).apiUrl, 'https://other.test/api');
});

test('parseLoginArgs understands --no-browser and rejects anything else', () => {
  assert.equal(parseLoginArgs(['--no-browser'], {}).openBrowser, false);
  assert.throws(() => parseLoginArgs(['--api-url'], {}), /needs a value/);
  assert.throws(() => parseLoginArgs(['--api-url', '--no-browser'], {}), /needs a value/);
  assert.throws(() => parseLoginArgs(['--email', 'a@b.test'], {}), /unknown option "--email"/);
});

interface Scripted {
  fetchFn: FetchFn;
  requests: { url: string; body: unknown }[];
}

/** Answers POSTs in order: the start, then one reply per poll, then a revoke if any. */
function scriptedFetch(replies: { status: number; body: unknown }[]): Scripted {
  const requests: Scripted['requests'] = [];
  const fetchFn = (async (input: string | URL, init?: RequestInit) => {
    requests.push({ url: String(input), body: init?.body ? JSON.parse(String(init.body)) : null });
    const reply = replies.shift();
    if (!reply) {
      throw new Error(`unexpected request to ${String(input)}`);
    }
    return new Response(204 === reply.status ? null : JSON.stringify(reply.body), { status: reply.status });
  }) as FetchFn;
  return { fetchFn, requests };
}

const started = {
  userCode: 'c0de',
  deviceSecret: 's3cret',
  verificationUrl: 'https://refleet.it/cli/authorize/c0de',
  expiresAt: '2026-09-17T09:10:00+00:00',
  pollIntervalSeconds: 3,
};

const approved = {
  status: 'approved',
  accountEmail: 'owner@refleet.it',
  apiKey: { id: 'key-2', name: 'refleet-runner (laptop)', prefix: 'ib_new', token: 'ib_newsecret', createdAt: '2026-09-17T09:01:00+00:00' },
};

function deps(fetchFn: FetchFn, extra: Partial<LoginDeps> = {}): Partial<LoginDeps> & { out: string[]; err: string[]; opened: string[]; slept: number[] } {
  const out: string[] = [];
  const err: string[] = [];
  const opened: string[] = [];
  const slept: number[] = [];
  return {
    out,
    err,
    opened,
    slept,
    fetchFn,
    sleep: async ms => {
      slept.push(ms);
    },
    open: url => {
      opened.push(url);
      return true;
    },
    now: () => Date.parse('2026-09-17T09:00:00+00:00'),
    stdout: line => out.push(line),
    stderr: line => err.push(line),
    runnerName: 'laptop',
    configDir: mkdtempSync(join(tmpdir(), 'refleet-login-test-')),
    ...extra,
  };
}

test('runLogin opens the link, polls until approved and stores the key', async () => {
  const { fetchFn, requests } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: { status: 'pending' } },
    { status: 200, body: approved },
  ]);
  const d = deps(fetchFn);

  const credentials = await runLogin({ apiUrl: 'http://localhost/api', openBrowser: true }, d);

  assert.deepEqual(d.opened, ['https://refleet.it/cli/authorize/c0de']);
  assert.ok(d.out.some(line => line.includes('https://refleet.it/cli/authorize/c0de')));
  assert.ok(d.out.some(line => line.includes('expires in 10 min')));
  assert.deepEqual(d.slept, [3000, 3000]);
  assert.equal(requests.length, 3);
  assert.deepEqual(requests[1]?.body, { deviceSecret: 's3cret', apiKeyName: 'refleet-runner (laptop)' });
  assert.deepEqual(credentials, {
    apiUrl: 'http://localhost/api',
    apiKey: 'ib_newsecret',
    apiKeyId: 'key-2',
    accountEmail: 'owner@refleet.it',
    createdAt: '2026-09-17T09:01:00+00:00',
  });
  assert.deepEqual(loadStoredConfig(d.configDir), credentials);
  assert.ok(d.out.some(line => line.includes('Logged in as owner@refleet.it')));
});

test('runLogin with --no-browser only prints the link', async () => {
  const { fetchFn } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: approved },
  ]);
  const d = deps(fetchFn);

  await runLogin({ apiUrl: 'http://localhost/api', openBrowser: false }, d);

  assert.deepEqual(d.opened, []);
  assert.ok(d.out.some(line => line.startsWith('Open this link')));
});

test('runLogin revokes the previous local key after the new one is saved', async () => {
  const { fetchFn, requests } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: approved },
    { status: 204, body: null },
  ]);
  const d = deps(fetchFn);
  saveStoredConfig(
    { apiUrl: 'http://old/api', apiKey: 'ib_old', apiKeyId: 'key-1', accountEmail: 'owner@refleet.it', createdAt: '2026-01-01T00:00:00+00:00' },
    d.configDir,
  );

  await runLogin({ apiUrl: 'http://localhost/api', openBrowser: false }, d);

  assert.equal(requests[2]?.url, 'http://old/api/identity/api-keys/key-1');
  assert.equal(loadStoredConfig(d.configDir)?.apiKey, 'ib_newsecret');
  assert.deepEqual(d.err, []);
});

test('runLogin keeps the new key and only warns when revoking the old one fails', async () => {
  const { fetchFn } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: approved },
    { status: 500, body: {} },
  ]);
  const d = deps(fetchFn);
  saveStoredConfig(
    { apiUrl: 'http://old/api', apiKey: 'ib_old', apiKeyId: 'key-1', accountEmail: 'owner@refleet.it', createdAt: '2026-01-01T00:00:00+00:00' },
    d.configDir,
  );

  await runLogin({ apiUrl: 'http://localhost/api', openBrowser: false }, d);

  assert.equal(loadStoredConfig(d.configDir)?.apiKey, 'ib_newsecret');
  assert.match(d.err[0] ?? '', /failed to revoke the previous local API key/);
});

test('runLogin fails without touching the config when the browser denies', async () => {
  const { fetchFn } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: { status: 'denied' } },
  ]);
  const d = deps(fetchFn);

  await assert.rejects(runLogin({ apiUrl: 'http://localhost/api', openBrowser: false }, d), /denied in the browser/);
  assert.equal(loadStoredConfig(d.configDir), null);
});

test('runLogin fails when the link expires before anyone approves', async () => {
  const { fetchFn } = scriptedFetch([
    { status: 201, body: started },
    { status: 200, body: { status: 'expired' } },
  ]);
  const d = deps(fetchFn);

  await assert.rejects(runLogin({ apiUrl: 'http://localhost/api', openBrowser: false }, d), /expired/);
});
