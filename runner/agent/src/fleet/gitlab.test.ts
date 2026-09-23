import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ensureRefleetLabel, GitLabRequestError, publishMergeRequest } from './gitlab.js';
import { fakeFetch } from './test-support.js';

const credentials = { baseUrl: 'https://gitlab.example.com/', accessToken: 'glpat-secret' };
const spec = { sourceBranch: 'refleet/change-t1', targetBranch: 'main', title: 'Apply shift', description: 'summary' };

test('publishMergeRequest looks for an open MR first, then creates one with the refleet label', async () => {
  const { fetchFn, requests } = fakeFetch(request =>
    'GET' === request.method ? { status: 200, body: [] } : { status: 201, body: { web_url: 'https://gitlab.example.com/g/p/-/merge_requests/7', iid: 7 } },
  );

  const mergeRequest = await publishMergeRequest(credentials, '4242', spec, fetchFn);

  assert.deepEqual(mergeRequest, { url: 'https://gitlab.example.com/g/p/-/merge_requests/7', iid: '7' });
  assert.equal(requests[0]?.method, 'GET');
  assert.match(requests[0]?.url ?? '', /merge_requests\?source_branch=refleet%2Fchange-t1&target_branch=main&state=opened$/);
  assert.equal(requests[1]?.method, 'POST');
  assert.equal(requests[1]?.url, 'https://gitlab.example.com/api/v4/projects/4242/merge_requests');
  assert.equal(requests[1]?.headers['Authorization'], 'Bearer glpat-secret');
  assert.deepEqual(requests[1]?.body, {
    source_branch: 'refleet/change-t1',
    target_branch: 'main',
    title: 'Apply shift',
    description: 'summary',
    labels: 'refleet',
    remove_source_branch: true,
  });
});

test('an open MR for the branch pair is updated in place instead of a second one being opened', async () => {
  const { fetchFn, requests } = fakeFetch(request =>
    'GET' === request.method
      ? { status: 200, body: [{ web_url: 'https://gitlab.example.com/g/p/-/merge_requests/3', iid: 3 }] }
      : { status: 200, body: { web_url: 'https://gitlab.example.com/g/p/-/merge_requests/3', iid: 3 } },
  );

  const mergeRequest = await publishMergeRequest(credentials, '4242', spec, fetchFn);

  assert.deepEqual(mergeRequest, { url: 'https://gitlab.example.com/g/p/-/merge_requests/3', iid: '3' });
  assert.equal(requests.length, 2);
  assert.equal(requests[1]?.method, 'PUT');
  assert.equal(requests[1]?.url, 'https://gitlab.example.com/api/v4/projects/4242/merge_requests/3');
  assert.deepEqual(requests[1]?.body, { title: 'Apply shift', description: 'summary', add_labels: 'refleet' });
});

test('a 409 on create (a parallel run won the race) falls back to updating the MR it opened', async () => {
  let listed = 0;
  const { fetchFn, requests } = fakeFetch(request => {
    if ('GET' === request.method) {
      listed++;
      return 1 === listed ? { status: 200, body: [] } : { status: 200, body: [{ web_url: 'https://gitlab.example.com/g/p/-/merge_requests/5', iid: 5 }] };
    }
    if ('POST' === request.method) {
      return { status: 409, body: { message: ['Another open merge request already exists for this source branch'] } };
    }
    return { status: 200, body: { web_url: 'https://gitlab.example.com/g/p/-/merge_requests/5', iid: 5 } };
  });

  const mergeRequest = await publishMergeRequest(credentials, '4242', spec, fetchFn);

  assert.equal(mergeRequest.iid, '5');
  assert.deepEqual(
    requests.map(request => request.method),
    ['GET', 'POST', 'GET', 'PUT'],
  );
});

test('a 409 with no open MR to reuse is still a failure', async () => {
  const { fetchFn } = fakeFetch(request => ('POST' === request.method ? { status: 409, body: {} } : { status: 200, body: [] }));

  await assert.rejects(() => publishMergeRequest(credentials, '4242', spec, fetchFn), (err: unknown) => err instanceof GitLabRequestError && 409 === err.status);
});

test('any other error status throws with the status and the response head', async () => {
  const { fetchFn } = fakeFetch(request => ('GET' === request.method ? { status: 200, body: [] } : { status: 403, body: { message: '403 Forbidden' } }));

  await assert.rejects(() => publishMergeRequest(credentials, '4242', spec, fetchFn), /creation failed \(status 403\).*Forbidden/);
});

test('a created MR without web_url/iid is an error rather than a half-reported target', async () => {
  const { fetchFn } = fakeFetch(request => ('GET' === request.method ? { status: 200, body: [] } : { status: 201, body: { id: 1 } }));

  await assert.rejects(() => publishMergeRequest(credentials, '4242', spec, fetchFn), /no web_url\/iid/);
});

test('ensureRefleetLabel is satisfied by a label inherited from an ancestor group', async () => {
  const { fetchFn, requests } = fakeFetch(() => ({ status: 200, body: [{ name: 'refleet-old' }, { name: 'refleet' }] }));

  assert.equal(await ensureRefleetLabel(credentials, '4242', fetchFn), true);
  assert.equal(requests.length, 1);
  assert.match(requests[0]?.url ?? '', /projects\/4242\/labels\?search=refleet&include_ancestor_groups=true/);
});

test('ensureRefleetLabel creates a pure black project label when none is visible', async () => {
  const { fetchFn, requests } = fakeFetch(request => ('GET' === request.method ? { status: 200, body: [] } : { status: 201, body: { id: 1 } }));

  assert.equal(await ensureRefleetLabel(credentials, '4242', fetchFn), true);
  assert.equal(requests[1]?.method, 'POST');
  assert.equal(requests[1]?.url, 'https://gitlab.example.com/api/v4/projects/4242/labels');
  assert.deepEqual(requests[1]?.body, { name: 'refleet', color: '#000000', description: 'Merge requests opened by Refleet' });
});

test('ensureRefleetLabel reports false instead of throwing when GitLab refuses', async () => {
  const { fetchFn } = fakeFetch(request => ('GET' === request.method ? { status: 200, body: [] } : { status: 403, body: {} }));

  assert.equal(await ensureRefleetLabel(credentials, '4242', fetchFn), false);
});

test('ensureRefleetLabel reports false when GitLab is unreachable', async () => {
  const { fetchFn } = fakeFetch(() => new Error('ECONNRESET'));

  assert.equal(await ensureRefleetLabel(credentials, '4242', fetchFn), false);
});
