import type { FetchFn } from '../backend-client.js';
import type { GitLabCredentials } from './api.js';

export interface MergeRequestSpec {
  sourceBranch: string;
  targetBranch: string;
  title: string;
  description: string;
}

export interface MergeRequest {
  url: string;
  iid: string;
}

/**
 * Every merge request a runner opens carries this label so customers can tell Refleet's
 * work from their own. The backend creates it in the group at sync time; the runner only
 * falls back to a project label when it is missing, because GitLab would otherwise invent
 * one in a random colour the moment the MR is created.
 */
export const REFLEET_LABEL = { name: 'refleet', color: '#000000', description: 'Merge requests opened by Refleet' } as const;

export class GitLabRequestError extends Error {
  constructor(
    message: string,
    readonly status: number,
  ) {
    super(message);
  }
}

interface MergeRequestResource {
  web_url?: string;
  iid?: number | string;
}

function apiBase(credentials: GitLabCredentials): string {
  return `${credentials.baseUrl.replace(/\/+$/, '')}/api/v4`;
}

async function gitlabRequest(
  credentials: GitLabCredentials,
  fetchFn: FetchFn,
  method: string,
  path: string,
  body?: unknown,
): Promise<Response> {
  return fetchFn(`${apiBase(credentials)}${path}`, {
    method,
    headers: {
      Authorization: `Bearer ${credentials.accessToken}`,
      'Content-Type': 'application/json',
    },
    body: undefined === body ? undefined : JSON.stringify(body),
  });
}

async function failWith(response: Response, action: string): Promise<never> {
  const detail = (await response.text()).slice(0, 500);
  throw new GitLabRequestError(`merge request ${action} failed (status ${String(response.status)}): ${detail}`, response.status);
}

function toMergeRequest(resource: MergeRequestResource, status: number): MergeRequest {
  if (!resource.web_url || undefined === resource.iid) {
    throw new GitLabRequestError('merge request response carried no web_url/iid', status);
  }
  return { url: resource.web_url, iid: String(resource.iid) };
}

async function findOpenMergeRequest(
  credentials: GitLabCredentials,
  projectId: string,
  spec: MergeRequestSpec,
  fetchFn: FetchFn,
): Promise<MergeRequest | null> {
  const query = new URLSearchParams({
    source_branch: spec.sourceBranch,
    target_branch: spec.targetBranch,
    state: 'opened',
  });
  const response = await gitlabRequest(credentials, fetchFn, 'GET', `/projects/${projectId}/merge_requests?${query.toString()}`);
  if (!response.ok) {
    return null;
  }

  const list = (await response.json()) as MergeRequestResource[];
  const first = list[0];
  return first?.web_url && undefined !== first.iid ? { url: first.web_url, iid: String(first.iid) } : null;
}

/**
 * Returns true when the label is available to the project (its own or inherited from an
 * ancestor group) by the time it returns. Never throws: a missing label is worth a warning,
 * not a failed job — the MR itself is what the shift is about.
 */
export async function ensureRefleetLabel(credentials: GitLabCredentials, projectId: string, fetchFn: FetchFn = fetch): Promise<boolean> {
  try {
    const query = new URLSearchParams({ search: REFLEET_LABEL.name, include_ancestor_groups: 'true', per_page: '100' });
    const listed = await gitlabRequest(credentials, fetchFn, 'GET', `/projects/${projectId}/labels?${query.toString()}`);
    if (listed.ok) {
      const labels = (await listed.json()) as { name?: string }[];
      if (labels.some(label => label.name === REFLEET_LABEL.name)) {
        return true;
      }
    }

    const created = await gitlabRequest(credentials, fetchFn, 'POST', `/projects/${projectId}/labels`, REFLEET_LABEL);
    return created.ok || 409 === created.status;
  } catch {
    return false;
  }
}

async function updateMergeRequest(
  credentials: GitLabCredentials,
  projectId: string,
  existing: MergeRequest,
  spec: MergeRequestSpec,
  fetchFn: FetchFn,
): Promise<MergeRequest> {
  const response = await gitlabRequest(credentials, fetchFn, 'PUT', `/projects/${projectId}/merge_requests/${existing.iid}`, {
    title: spec.title,
    description: spec.description,
    add_labels: REFLEET_LABEL.name,
  });
  if (!response.ok) {
    return failWith(response, 'update');
  }
  return toMergeRequest((await response.json()) as MergeRequestResource, response.status);
}

/**
 * Opens the merge request for a pushed branch, or refreshes the one already open for it
 * — a re-run force-pushes the same per-target branch, and the customer must end up with
 * exactly one merge request per target, not a new one per attempt. Straight through the
 * GitLab REST API, since the branch is already pushed and no git credentials are needed.
 *
 * The label is always attached here in code (never left to the agent): on create through
 * `labels`, on reuse through `add_labels`, so an MR opened before the label existed picks
 * it up on its next run.
 */
export async function publishMergeRequest(
  credentials: GitLabCredentials,
  projectId: string,
  spec: MergeRequestSpec,
  fetchFn: FetchFn = fetch,
): Promise<MergeRequest> {
  const existing = await findOpenMergeRequest(credentials, projectId, spec, fetchFn);
  if (existing) {
    return updateMergeRequest(credentials, projectId, existing, spec, fetchFn);
  }

  const response = await gitlabRequest(credentials, fetchFn, 'POST', `/projects/${projectId}/merge_requests`, {
    source_branch: spec.sourceBranch,
    target_branch: spec.targetBranch,
    title: spec.title,
    description: spec.description,
    labels: REFLEET_LABEL.name,
    remove_source_branch: true,
  });

  // Lost a race with a parallel run for the same branch: pick up the MR it just opened.
  if (409 === response.status) {
    const raced = await findOpenMergeRequest(credentials, projectId, spec, fetchFn);
    if (raced) {
      return updateMergeRequest(credentials, projectId, raced, spec, fetchFn);
    }
  }

  if (!response.ok) {
    return failWith(response, 'creation');
  }

  return toMergeRequest((await response.json()) as MergeRequestResource, response.status);
}
