import { test } from 'node:test';
import assert from 'node:assert/strict';
import { FleetApi } from './api.js';
import { collectLog, fakeFetch } from './test-support.js';

const identity = { name: 'box-claude', supportedModes: ['ai'], supportedEngines: ['claude'], supportedModels: ['claude-sonnet-5'] };

test('heartbeat posts name plus the non-empty capability lists with a bearer token', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200 }));
  const api = new FleetApi('http://localhost/api/', 'ib_key', collectLog().log, fetchFn);

  await api.heartbeat(identity);

  assert.equal(requests[0]?.url, 'http://localhost/api/runner/heartbeat');
  assert.equal(requests[0]?.headers.Authorization, 'Bearer ib_key');
  assert.deepEqual(requests[0]?.body, {
    name: 'box-claude',
    supportedEngines: ['claude'],
    supportedModels: ['claude-sonnet-5'],
  });
});

test('heartbeat omits empty lists entirely, matching the old jq body', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200 }));
  const api = new FleetApi('http://localhost/api', 'ib_key', collectLog().log, fetchFn);

  await api.heartbeat({ name: 'box-kiro', supportedEngines: ['kiro'], supportedModels: [] });

  assert.deepEqual(requests[0]?.body, { name: 'box-kiro', supportedEngines: ['kiro'] });
});

test('heartbeat carries the last agent usage when there is one', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200 }));
  const api = new FleetApi('http://localhost/api', 'ib_key', collectLog().log, fetchFn);
  const usage = { observedAt: '2026-09-21T10:00:00.000Z', rateLimits: null, context: { used: 1, size: 2 }, tokens: null, cost: null };

  await api.heartbeat(identity, usage);

  assert.deepEqual((requests[0]?.body as { usage: unknown }).usage, usage);
});

test('heartbeat sends the runner version and returns what the backend says about its release', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200, body: { id: 'r1', latestVersion: '0.1.200', updateAvailable: true, updateRequested: false } }));
  const api = new FleetApi('http://localhost/api', 'ib_key', collectLog().log, fetchFn);

  const response = await api.heartbeat(identity, null, '0.1.186');

  assert.equal((requests[0]?.body as { version: string }).version, '0.1.186');
  assert.deepEqual(response, { latestVersion: '0.1.200', updateAvailable: true, updateRequested: false });
});

test('heartbeat returns null when it failed or the answer carries no release fields worth trusting', async () => {
  const failed = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 500 })).fetchFn);
  assert.equal(await failed.heartbeat(identity), null);

  const empty = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 200 })).fetchFn);
  assert.equal(await empty.heartbeat(identity), null);

  const old = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 200, body: { id: 'r1' } })).fetchFn);
  assert.deepEqual(await old.heartbeat(identity), { latestVersion: null, updateAvailable: false, updateRequested: false });
});

test('claimJob returns the job on 200 and sends the claim filters', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200, body: { jobId: 'job-1', kind: 'change', payload: {} } }));
  const api = new FleetApi('http://localhost/api', 'ib_key', collectLog().log, fetchFn);

  const job = await api.claimJob(identity);

  assert.equal(job?.jobId, 'job-1');
  assert.deepEqual(requests[0]?.body, { runnerId: 'box-claude', supportedModes: ['ai'], supportedEngines: ['claude'] });
});

test('claimJob returns null on 204 (nothing to do) and on a connection failure', async () => {
  const noJob = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 204 })).fetchFn);
  assert.equal(await noJob.claimJob(identity), null);

  const offline = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => new Error('ECONNREFUSED')).fetchFn);
  assert.equal(await offline.claimJob(identity), null);
});

test('a failed request never throws — it is logged and the loop moves on', async () => {
  const { log, lines } = collectLog();
  const api = new FleetApi('http://localhost/api', 'k', log, fakeFetch(() => new Error('ECONNREFUSED')).fetchFn);

  await assert.doesNotReject(() => api.heartbeat(identity));
  await assert.doesNotReject(() => api.reportJob(identity, 'job-1', { outcome: 'failure', summary: 'x' }));

  assert.match(lines[0] ?? '', /heartbeat request failed \(status 0\)/);
  assert.match(lines[1] ?? '', /report failed for job job-1/);
});

test('reportJob includes only the optional fields that were given', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200 }));
  const api = new FleetApi('http://localhost/api', 'k', collectLog().log, fetchFn);

  await api.reportJob(identity, 'job-1', { outcome: 'success', summary: 'done', score: 1 });
  await api.reportJob(identity, 'job-2', {
    outcome: 'success',
    summary: 'done',
    branchName: 'refleet/change-t1',
    mergeRequestUrl: 'https://gitlab.example.com/g/p/-/merge_requests/3',
    mergeRequestIid: '3',
  });

  assert.equal(requests[0]?.url, 'http://localhost/api/runner/jobs/job-1/report');
  assert.deepEqual(requests[0]?.body, { runnerId: 'box-claude', outcome: 'success', summary: 'done', score: 1 });
  assert.deepEqual(requests[1]?.body, {
    runnerId: 'box-claude',
    outcome: 'success',
    summary: 'done',
    branchName: 'refleet/change-t1',
    mergeRequestUrl: 'https://gitlab.example.com/g/p/-/merge_requests/3',
    mergeRequestIid: '3',
  });
});

test('fetchGitLabCredentials returns null unless both fields are present', async () => {
  const ok = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 200, body: { baseUrl: 'https://gitlab.com', accessToken: 'glpat' } })).fetchFn);
  assert.deepEqual(await ok.fetchGitLabCredentials(), { baseUrl: 'https://gitlab.com', accessToken: 'glpat' });

  const incomplete = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 200, body: { baseUrl: 'https://gitlab.com' } })).fetchFn);
  assert.equal(await incomplete.fetchGitLabCredentials(), null);

  const notConnected = new FleetApi('http://localhost/api', 'k', collectLog().log, fakeFetch(() => ({ status: 404 })).fetchFn);
  assert.equal(await notConnected.fetchGitLabCredentials(), null);
});
