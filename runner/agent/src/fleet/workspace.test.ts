import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, utimesSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import type { GitRunner } from './git.js';
import { type ChangeWording, evictLruUntilUnderCap, publishChange, syncRepo, type Workspace } from './workspace.js';

function wording(title: string, description: string, commitBody = ''): ChangeWording {
  return { commitSubject: title, commitBody, title, description };
}
import { collectLog, fakeFetch } from './test-support.js';

const credentials = { baseUrl: 'https://gitlab.example.com', accessToken: 'glpat-secret' };
const project = { externalId: '4242', path: 'acme/robots', defaultBranch: 'main' };

function tempDir(): string {
  return mkdtempSync(join(tmpdir(), 'refleet-runner-workspace-test-'));
}

interface GitCall {
  args: string[];
  cwd: string | undefined;
  env: Record<string, string> | undefined;
}

function fakeGit(script: (args: string[]) => string = () => ''): { git: GitRunner; calls: GitCall[] } {
  const calls: GitCall[] = [];
  const git: GitRunner = (args, cwd, env) => {
    calls.push({ args, cwd, env });
    return Promise.resolve({ stdout: script(args), stderr: '' });
  };
  return { git, calls };
}

function workspaceIn(cacheDir: string, git: GitRunner, maxSizeMb = 5120): Workspace {
  return { cacheDir, maxSizeMb, git, log: collectLog().log };
}

function fillCheckout(cacheDir: string, name: string, sizeBytes: number, lastUsed: Date): string {
  const dir = join(cacheDir, name);
  mkdirSync(dir, { recursive: true });
  writeFileSync(join(dir, 'blob'), Buffer.alloc(sizeBytes));
  writeFileSync(join(dir, '.refleet-last-used'), '');
  utimesSync(join(dir, '.refleet-last-used'), lastUsed, lastUsed);
  return dir;
}

test('evictLruUntilUnderCap removes least-recently-used checkouts until the cache is under the cap', () => {
  const cacheDir = tempDir();
  const old = fillCheckout(cacheDir, '1', 1024 * 1024, new Date('2026-01-01'));
  const middle = fillCheckout(cacheDir, '2', 1024 * 1024, new Date('2026-02-01'));
  const recent = fillCheckout(cacheDir, '3', 1024 * 1024, new Date('2026-03-01'));

  evictLruUntilUnderCap(workspaceIn(cacheDir, fakeGit().git, 2));

  assert.equal(existsSync(old), false);
  assert.equal(existsSync(middle), false);
  assert.equal(existsSync(recent), true);
});

test('evictLruUntilUnderCap is a no-op below the cap or without a cache directory', () => {
  const cacheDir = tempDir();
  const kept = fillCheckout(cacheDir, '1', 10, new Date('2026-01-01'));

  evictLruUntilUnderCap(workspaceIn(cacheDir, fakeGit().git, 5120));
  assert.equal(existsSync(kept), true);

  assert.doesNotThrow(() => evictLruUntilUnderCap(workspaceIn(join(cacheDir, 'missing'), fakeGit().git, 1)));
});

test('syncRepo clones on first use with the auth header passed per invocation, not persisted', async () => {
  const cacheDir = tempDir();
  const repoDir = join(cacheDir, '4242');
  const { git, calls } = fakeGit(args => {
    if (args.includes('clone')) {
      mkdirSync(join(repoDir, '.git', 'info'), { recursive: true });
    }
    return '';
  });

  const result = await syncRepo(workspaceIn(cacheDir, git), credentials, project);

  assert.equal(result, repoDir);
  assert.equal(calls.length, 1);
  const clone = calls[0]?.args ?? [];
  assert.deepEqual(clone, ['clone', '--origin', 'origin', 'https://gitlab.example.com/acme/robots.git', repoDir]);
  assert.equal(calls[0]?.env?.GIT_CONFIG_KEY_0, 'http.extraHeader');
  assert.match(calls[0]?.env?.GIT_CONFIG_VALUE_0 ?? '', /^Authorization: Basic /);
  assert.ok(!clone.some(arg => arg.includes('Authorization')), 'the token must never be part of argv');
  assert.equal(existsSync(join(repoDir, '.refleet-last-used')), true);
  assert.equal(readFileSync(join(repoDir, '.git', 'info', 'exclude'), 'utf8'), '.refleet-last-used\n');
});

test('syncRepo reuses a cached checkout with fetch + hard reset and does not duplicate the exclude entry', async () => {
  const cacheDir = tempDir();
  const repoDir = join(cacheDir, '4242');
  mkdirSync(join(repoDir, '.git', 'info'), { recursive: true });
  writeFileSync(join(repoDir, '.git', 'info', 'exclude'), '.refleet-last-used\n');
  const { git, calls } = fakeGit();

  await syncRepo(workspaceIn(cacheDir, git), credentials, project);

  assert.deepEqual(
    calls.map(call => call.args),
    [['fetch', '--prune', 'origin'], ['checkout', '-f', 'main'], ['reset', '--hard', 'origin/main'], ['clean', '-fdx']],
  );
  assert.equal(calls[0]?.env?.GIT_CONFIG_COUNT, '1');
  assert.equal(calls[1]?.env, undefined);
  assert.ok(calls.every(call => call.cwd === repoDir));
  assert.equal(readFileSync(join(repoDir, '.git', 'info', 'exclude'), 'utf8'), '.refleet-last-used\n');
});

test('syncRepo propagates a git failure instead of returning a broken working copy', async () => {
  const cacheDir = tempDir();
  const git: GitRunner = () => Promise.reject(new Error('fatal: could not read from remote'));

  await assert.rejects(() => syncRepo(workspaceIn(cacheDir, git), credentials, project), /could not read from remote/);
});

test('publishChange returns null when the job changed nothing', async () => {
  const { git, calls } = fakeGit(() => '');
  const { fetchFn, requests } = fakeFetch(() => ({ status: 201 }));

  const result = await publishChange(workspaceIn(tempDir(), git), credentials, '/repo', project, 't1', wording('title', 'desc'), fetchFn);

  assert.equal(result, null);
  assert.deepEqual(
    calls.map(call => call.args),
    [
      ['add', '-A'],
      ['diff', '--cached', '--name-only'],
    ],
  );
  assert.equal(requests.length, 0);
});

test('publishChange commits, force-pushes the per-target branch and opens the merge request with the refleet label', async () => {
  const { git, calls } = fakeGit(args => ('diff' === args[0] ? 'src/app.php\n' : ''));
  const { fetchFn, requests } = fakeFetch(request => {
    if (request.url.includes('/labels')) {
      return { status: 200, body: [{ name: 'refleet' }] };
    }
    return 'GET' === request.method
      ? { status: 200, body: [] }
      : { status: 201, body: { web_url: 'https://gitlab.example.com/acme/robots/-/merge_requests/9', iid: 9 } };
  });

  const result = await publishChange(workspaceIn(tempDir(), git), credentials, '/repo', project, 't1', wording('Bump legacy-lib', 'what changed'), fetchFn);

  assert.deepEqual(result, { branchName: 'refleet/change-t1', mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9', mergeRequestIid: '9' });
  assert.deepEqual(calls[2]?.args, ['-c', 'user.name=Refleet', '-c', 'user.email=refleet@localhost', 'commit', '-m', 'Bump legacy-lib']);
  assert.deepEqual(calls[3]?.args, ['push', '--force', 'origin', 'HEAD:refs/heads/refleet/change-t1']);
  assert.equal(calls[3]?.env?.GIT_CONFIG_KEY_0, 'http.extraHeader');
  const created = requests.find(request => 'POST' === request.method && request.url.endsWith('/merge_requests'));
  assert.equal(created?.url, 'https://gitlab.example.com/api/v4/projects/4242/merge_requests');
  assert.deepEqual(created?.body, {
    source_branch: 'refleet/change-t1',
    target_branch: 'main',
    title: 'Bump legacy-lib',
    description: 'what changed',
    labels: 'refleet',
    remove_source_branch: true,
  });
});

test('publishChange passes a commit body as a second -m so git keeps the blank line after the subject', async () => {
  const { git, calls } = fakeGit(args => ('diff' === args[0] ? 'src/app.php\n' : ''));
  const { fetchFn } = fakeFetch(request => {
    if (request.url.includes('/labels')) {
      return { status: 200, body: [{ name: 'refleet' }] };
    }
    return 'GET' === request.method ? { status: 200, body: [] } : { status: 201, body: { web_url: 'https://gitlab.example.com/mr/1', iid: 1 } };
  });

  await publishChange(workspaceIn(tempDir(), git), credentials, '/repo', project, 't1', wording('chore: bump', 'desc', 'Because it was old.'), fetchFn);

  assert.deepEqual(calls[2]?.args, ['-c', 'user.name=Refleet', '-c', 'user.email=refleet@localhost', 'commit', '-m', 'chore: bump', '-m', 'Because it was old.']);
});

test('publishChange still labels the merge request when the label colour cannot be ensured', async () => {
  const { git } = fakeGit(args => ('diff' === args[0] ? 'src/app.php\n' : ''));
  const { fetchFn, requests } = fakeFetch(request => {
    if (request.url.includes('/labels')) {
      return { status: 403, body: {} };
    }
    return 'GET' === request.method
      ? { status: 200, body: [] }
      : { status: 201, body: { web_url: 'https://gitlab.example.com/acme/robots/-/merge_requests/9', iid: 9 } };
  });
  const { log, lines } = collectLog();

  const result = await publishChange({ ...workspaceIn(tempDir(), git), log }, credentials, '/repo', project, 't1', wording('title', 'desc'), fetchFn);

  assert.equal(result?.mergeRequestUrl, 'https://gitlab.example.com/acme/robots/-/merge_requests/9');
  const created = requests.find(request => 'POST' === request.method && request.url.endsWith('/merge_requests'));
  assert.equal((created?.body as { labels?: string }).labels, 'refleet');
  assert.ok(lines.some(line => line.includes('"refleet" label')));
});
