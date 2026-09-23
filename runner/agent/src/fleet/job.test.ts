import { test } from 'node:test';
import assert from 'node:assert/strict';
import { FleetApi, type ClaimedJob } from './api.js';
import { AgentExecutionError, type AgentResult, type AgentUsage } from '../backends/types.js';
import { changeWording, claimAndRunJob, type JobRunnerDeps, type UsageTracker } from './job.js';
import type { ChangeWording } from './workspace.js';
import { collectLog, fakeFetch, type RecordedRequest } from './test-support.js';

const identity = { name: 'box-claude', supportedModes: ['ai'], supportedEngines: ['claude'] };
const credentials = { baseUrl: 'https://gitlab.example.com', accessToken: 'glpat' };

function claimedJob(overrides: Partial<ClaimedJob> = {}): ClaimedJob {
  return {
    jobId: 'job-1',
    ownerId: 'shift-1',
    ownerTargetId: 'target-1',
    ownerLabel: 'Bump legacy-lib',
    kind: 'change',
    payload: { project: { externalId: '4242', path: 'acme/robots', defaultBranch: 'main' }, engine: 'claude', prompt: 'do it' },
    ...overrides,
  };
}

interface Scenario {
  job: ClaimedJob | null;
  credentials?: boolean;
  sync?: () => Promise<string>;
  execute?: () => Promise<AgentResult>;
  usage?: UsageTracker;
  publish?: () => Promise<{ branchName: string; mergeRequestUrl: string; mergeRequestIid: string } | null>;
}

function agentSaid(output: string, usage: AgentUsage | null = null): () => Promise<AgentResult> {
  return () => Promise.resolve({ output, usage });
}

function someUsage(): AgentUsage {
  return { observedAt: '2026-09-21T10:00:00.000Z', rateLimits: null, context: null, tokens: { input: 10, output: 2 }, cost: null };
}

function setUp(scenario: Scenario): { deps: JobRunnerDeps; requests: RecordedRequest[]; lines: string[] } {
  const { fetchFn, requests } = fakeFetch(request => {
    if (request.url.endsWith('/runner/jobs/claim')) {
      return scenario.job ? { status: 200, body: scenario.job } : { status: 204 };
    }
    if (request.url.endsWith('/runner/gitlab-credentials')) {
      return false === scenario.credentials ? { status: 404 } : { status: 200, body: credentials };
    }
    return { status: 200 };
  });
  const { log, lines } = collectLog();
  const deps: JobRunnerDeps = {
    api: new FleetApi('http://localhost/api', 'ib_key', log, fetchFn),
    identity,
    workspace: { cacheDir: '/tmp/unused', maxSizeMb: 1, git: () => Promise.reject(new Error('git must not be called')), log },
    execute: scenario.execute ?? agentSaid('{"score": 5, "reasoning": "yes"}'),
    log,
    usage: scenario.usage,
    fetchFn,
    syncRepo: scenario.sync ?? (() => Promise.resolve('/cache/4242')),
    publishChange: scenario.publish ?? (() => Promise.resolve(null)),
  };
  return { deps, requests, lines };
}

function reportOf(requests: RecordedRequest[]): unknown {
  return requests.find(request => request.url.endsWith('/report'))?.body;
}

test('nothing claimed means nothing else is called', async () => {
  const { deps, requests } = setUp({ job: null });

  await claimAndRunJob(deps);

  assert.equal(requests.length, 1);
});

test('a job without project info is reported as a failure before any checkout', async () => {
  const { deps, requests } = setUp({ job: claimedJob({ payload: { prompt: 'x' } }) });

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'failure',
    summary: 'Runner job payload is missing project information',
    errorMessage: 'missing project info',
  });
});

test('missing GitLab credentials fail the job without touching git', async () => {
  const { deps, requests } = setUp({ job: claimedJob(), credentials: false });

  await claimAndRunJob(deps);

  const report = reportOf(requests) as { outcome: string; summary: string };
  assert.equal(report.outcome, 'failure');
  assert.match(report.summary, /GitLab credentials/);
});

test('a checkout failure is reported with the git error', async () => {
  const { deps, requests } = setUp({ job: claimedJob(), sync: () => Promise.reject(new Error('fatal: repository not found')) });

  await claimAndRunJob(deps);

  const report = reportOf(requests) as { outcome: string; summary: string; errorMessage: string };
  assert.equal(report.outcome, 'failure');
  assert.equal(report.summary, 'Failed to clone/update acme/robots');
  assert.match(report.errorMessage, /git sync failed: fatal: repository not found/);
});

test('an agent failure is reported as "Task execution failed" with the error tail', async () => {
  const { deps, requests } = setUp({ job: claimedJob(), execute: () => Promise.reject(new Error('claude exited with 1')) });

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'failure',
    summary: 'Task execution failed',
    errorMessage: 'claude exited with 1',
  });
});

test('usage reported by a successful run is recorded for the next heartbeat', async () => {
  const usage: UsageTracker = { latest: null };
  const { deps } = setUp({ job: claimedJob({ kind: 'qualification' }), usage, execute: agentSaid('{"score": 5, "reasoning": "yes"}', someUsage()) });

  await claimAndRunJob(deps);

  assert.deepEqual(usage.latest, someUsage());
});

test('usage a failed run still managed to report is recorded, and a run without any leaves the last one standing', async () => {
  const usage: UsageTracker = { latest: someUsage() };
  const { deps } = setUp({ job: claimedJob(), usage, execute: () => Promise.reject(new AgentExecutionError('claude exited with status 1', null)) });

  await claimAndRunJob(deps);
  assert.deepEqual(usage.latest, someUsage());

  const rejected: AgentUsage = { ...someUsage(), rateLimits: [{ window: 'five_hour', status: 'rejected', utilization: 1, resetsAt: null }] };
  const { deps: failing } = setUp({ job: claimedJob(), usage, execute: () => Promise.reject(new AgentExecutionError('claude exited with status 1', rejected)) });

  await claimAndRunJob(failing);
  assert.deepEqual(usage.latest, rejected);
});

test('a qualification job reports the score with the reasoning as summary and never publishes', async () => {
  let published = false;
  const { deps, requests } = setUp({
    job: claimedJob({ kind: 'qualification' }),
    execute: agentSaid('Looked at composer.json.\n```json\n{"score": 2, "reasoning": "No trace of acme/legacy-lib in composer.json."}\n```'),
    publish: () => {
      published = true;
      return Promise.resolve(null);
    },
  });

  await claimAndRunJob(deps);

  assert.equal(published, false);
  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'success',
    summary: 'No trace of acme/legacy-lib in composer.json.',
    score: 2,
  });
});

test('a qualification job without a valid score is a failure carrying the raw output', async () => {
  const { deps, requests } = setUp({ job: claimedJob({ kind: 'qualification' }), execute: agentSaid('I am not sure.') });

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'failure',
    summary: 'Agent did not return a qualification score between 1 and 5',
    errorMessage: 'I am not sure.',
  });
});

test('a change job reports the branch and the merge request in the one report, titled after the shift', async () => {
  let wording: ChangeWording | null = null;
  const { deps, requests } = setUp({
    job: claimedJob(),
    execute: agentSaid('Did things.\n{"summary": "Renamed the helper."}'),
    publish: () => Promise.resolve({ branchName: 'refleet/change-target-1', mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9', mergeRequestIid: '9' }),
  });
  deps.publishChange = (_workspace, _credentials, _repoDir, _project, _targetId, published) => {
    wording = published;
    return Promise.resolve({ branchName: 'refleet/change-target-1', mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9', mergeRequestIid: '9' });
  };

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'success',
    summary: 'Renamed the helper.',
    branchName: 'refleet/change-target-1',
    mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9',
    mergeRequestIid: '9',
  });
  assert.deepEqual(wording, { commitSubject: 'Bump legacy-lib', commitBody: '', title: 'Bump legacy-lib', description: 'Renamed the helper.' });
  assert.equal(requests.some(request => request.url.endsWith('/merge-request')), false);
  assert.equal(
    requests.filter(request => request.url.endsWith('/runner/gitlab-credentials')).length,
    2,
    'credentials are fetched again before publishing, since the first token may have expired during the agent run',
  );
});

test('a change job without a JSON summary falls back to the raw output', async () => {
  const { deps, requests } = setUp({ job: claimedJob(), execute: agentSaid('Nothing to do.'), publish: () => Promise.resolve(null) });

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), { runnerId: 'box-claude', outcome: 'success', summary: 'Nothing to do.' });
});

test('a publish failure is a job failure, so the target is not left MERGE_REQUEST_OPEN without an MR', async () => {
  const { deps, requests } = setUp({ job: claimedJob(), execute: agentSaid('Changed.'), publish: () => Promise.reject(new Error('push rejected')) });

  await claimAndRunJob(deps);

  assert.deepEqual(reportOf(requests), {
    runnerId: 'box-claude',
    outcome: 'failure',
    summary: 'Failed to push the change and open a GitLab merge request',
    errorMessage: 'push rejected',
  });
});

test('summaries are cut to their last 4000 characters', async () => {
  const output = 'x'.repeat(5000) + 'END';
  const { deps, requests } = setUp({ job: claimedJob(), execute: agentSaid(output) });

  await claimAndRunJob(deps);

  const report = reportOf(requests) as { summary: string };
  assert.equal(report.summary.length, 4000);
  assert.ok(report.summary.endsWith('END'));
});

test('changeWording takes the agent proposal and falls back field by field to the shift title and summary', () => {
  const job = { ownerId: 'shift-1', ownerLabel: 'Bump legacy-lib' };

  assert.deepEqual(
    changeWording(
      job,
      {
        summary: 'Bumped it.',
        commit: { subject: 'chore(deps): bump legacy-lib', body: 'Because.' },
        mergeRequest: { title: 'Bump legacy-lib to 3', description: '## Why\n\nBecause.' },
      },
      'Bumped it.',
    ),
    { commitSubject: 'chore(deps): bump legacy-lib', commitBody: 'Because.', title: 'Bump legacy-lib to 3', description: '## Why\n\nBecause.' },
  );
  assert.deepEqual(changeWording(job, { summary: 'Bumped it.', commit: { subject: 'chore: bump', body: '' } }, 'Bumped it.'), {
    commitSubject: 'chore: bump',
    commitBody: '',
    title: 'chore: bump',
    description: 'Bumped it.',
  });
  assert.deepEqual(changeWording(job, { summary: 'x', mergeRequest: { title: 'MR', description: '' } }, 'x'), {
    commitSubject: 'Bump legacy-lib',
    commitBody: '',
    title: 'MR',
    description: 'x',
  });
  assert.deepEqual(changeWording({ ownerId: 'shift-1', ownerLabel: '  ' }, null, 'raw output'), {
    commitSubject: 'Refleet shift shift-1',
    commitBody: '',
    title: 'Refleet shift shift-1',
    description: 'raw output',
  });
});
