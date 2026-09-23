import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  BackendRequestError,
  claimCliAuthorization,
  listApiKeys,
  revokeApiKey,
  startCliAuthorization,
  type FetchFn,
} from './backend-client.js';

interface RecordedCall {
  url: string;
  init?: RequestInit;
}

function fakeFetch(status: number, body: unknown): { fetchFn: FetchFn; calls: RecordedCall[] } {
  const calls: RecordedCall[] = [];
  // 204 (and other null-body statuses) reject a non-null body at the Response
  // constructor level — mirrors the real /identity/api-keys DELETE response.
  const responseBody = 204 === status ? null : JSON.stringify(body);
  const fetchFn = (async (input: string | URL, init?: RequestInit) => {
    calls.push({ url: String(input), init });
    return new Response(responseBody, { status });
  }) as FetchFn;
  return { fetchFn, calls };
}

test('startCliAuthorization posts the runner name without any credentials and returns the handshake', async () => {
  const started = {
    userCode: 'c0de',
    deviceSecret: 's3cret',
    verificationUrl: 'https://refleet.it/cli/authorize/c0de',
    expiresAt: '2026-09-17T09:10:00+00:00',
    pollIntervalSeconds: 3,
  };
  const { fetchFn, calls } = fakeFetch(201, started);

  const result = await startCliAuthorization('http://localhost/api/', 'my-laptop', fetchFn);

  assert.deepEqual(result, started);
  assert.equal(calls[0]?.url, 'http://localhost/api/identity/cli-authorizations');
  assert.equal(calls[0]?.init?.method, 'POST');
  assert.equal((calls[0]?.init?.headers as Record<string, string>).Authorization, undefined);
  assert.deepEqual(JSON.parse(String(calls[0]?.init?.body)), { runnerName: 'my-laptop' });
});

test('startCliAuthorization throws BackendRequestError on failure', async () => {
  const { fetchFn } = fakeFetch(500, {});
  await assert.rejects(startCliAuthorization('http://localhost/api', 'x', fetchFn), BackendRequestError);
});

test('claimCliAuthorization sends the device secret in the body and returns the outcome', async () => {
  const { fetchFn, calls } = fakeFetch(200, { status: 'pending' });

  const result = await claimCliAuthorization('http://localhost/api', 's3cret', 'refleet-runner (host)', fetchFn);

  assert.deepEqual(result, { status: 'pending' });
  assert.equal(calls[0]?.url, 'http://localhost/api/identity/cli-authorizations/claim');
  assert.equal(calls[0]?.init?.method, 'POST');
  assert.deepEqual(JSON.parse(String(calls[0]?.init?.body)), { deviceSecret: 's3cret', apiKeyName: 'refleet-runner (host)' });
});

test('claimCliAuthorization maps 404 to a message about the request being gone', async () => {
  const { fetchFn } = fakeFetch(404, {});
  await assert.rejects(claimCliAuthorization('http://localhost/api', 'old', 'x', fetchFn), /no longer known/);
});

test('claimCliAuthorization throws BackendRequestError on other failures', async () => {
  const { fetchFn } = fakeFetch(500, {});
  await assert.rejects(claimCliAuthorization('http://localhost/api', 's3cret', 'x', fetchFn), BackendRequestError);
});

test('revokeApiKey sends DELETE with a Bearer API key', async () => {
  const { fetchFn, calls } = fakeFetch(204, {});

  await revokeApiKey('http://localhost/api', 'ib_apikey', 'key-1', fetchFn);

  assert.equal(calls[0]?.url, 'http://localhost/api/identity/api-keys/key-1');
  assert.equal(calls[0]?.init?.method, 'DELETE');
  assert.equal((calls[0]?.init?.headers as Record<string, string>).Authorization, 'Bearer ib_apikey');
});

test('revokeApiKey treats 404 as success (idempotent logout)', async () => {
  const { fetchFn } = fakeFetch(404, {});
  await assert.doesNotReject(revokeApiKey('http://localhost/api', 'ib_apikey', 'key-1', fetchFn));
});

test('revokeApiKey throws on other failures', async () => {
  const { fetchFn } = fakeFetch(500, {});
  await assert.rejects(revokeApiKey('http://localhost/api', 'ib_apikey', 'key-1', fetchFn), BackendRequestError);
});

test('listApiKeys returns the apiKeys array with a Bearer API key', async () => {
  const { fetchFn, calls } = fakeFetch(200, { apiKeys: [{ id: 'key-1' }], count: 1 });

  const keys = await listApiKeys('http://localhost/api', 'ib_apikey', fetchFn);

  assert.deepEqual(keys, [{ id: 'key-1' }]);
  assert.equal((calls[0]?.init?.headers as Record<string, string>).Authorization, 'Bearer ib_apikey');
});

test('listApiKeys throws BackendRequestError on failure', async () => {
  const { fetchFn } = fakeFetch(401, {});
  await assert.rejects(listApiKeys('http://localhost/api', 'bad-key', fetchFn), BackendRequestError);
});
