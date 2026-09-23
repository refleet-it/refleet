import { appendFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, utimesSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import type { FetchFn } from '../backend-client.js';
import type { GitLabCredentials } from './api.js';
import { gitAuthEnv, type GitRunner } from './git.js';
import { ensureRefleetLabel, publishMergeRequest, REFLEET_LABEL } from './gitlab.js';
import type { Logger } from './log.js';

const LAST_USED_MARKER = '.refleet-last-used';

export interface WorkspaceProject {
  externalId: string;
  path: string;
  defaultBranch: string;
}

export interface Workspace {
  cacheDir: string;
  maxSizeMb: number;
  git: GitRunner;
  log: Logger;
}

export interface PublishedChange {
  branchName: string;
  mergeRequestUrl: string;
  mergeRequestIid: string;
}

function directorySizeBytes(dir: string): number {
  let total = 0;
  let entries;
  try {
    entries = readdirSync(dir, { withFileTypes: true });
  } catch {
    return 0;
  }

  for (const entry of entries) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) {
      total += directorySizeBytes(path);
    } else if (entry.isFile()) {
      try {
        total += statSync(path).size;
      } catch {
        // deleted between readdir and stat — a size estimate is all the cap needs
      }
    }
  }

  return total;
}

export function cacheSizeMb(cacheDir: string): number {
  return Math.ceil(directorySizeBytes(cacheDir) / (1024 * 1024));
}

function touchMarker(repoDir: string): void {
  const marker = join(repoDir, LAST_USED_MARKER);
  if (existsSync(marker)) {
    const now = new Date();
    utimesSync(marker, now, now);
  } else {
    writeFileSync(marker, '');
  }
}

function markerMtime(repoDir: string): number {
  const marker = join(repoDir, LAST_USED_MARKER);
  if (!existsSync(marker)) {
    touchMarker(repoDir);
  }
  return statSync(marker).mtimeMs;
}

/**
 * Per-project checkout cache: a long-lived runner reuses one working copy per project
 * (fetch + hard reset instead of a full clone on every job), bounded by total size
 * rather than project count — least-recently-used checkouts go first once the cap is
 * hit. Keyed by GitLab externalId alone since one runner talks to one GitLab instance.
 */
export function evictLruUntilUnderCap(workspace: Workspace): void {
  if (!existsSync(workspace.cacheDir)) {
    return;
  }

  while (cacheSizeMb(workspace.cacheDir) >= workspace.maxSizeMb) {
    const checkouts = readdirSync(workspace.cacheDir, { withFileTypes: true })
      .filter(entry => entry.isDirectory())
      .map(entry => join(workspace.cacheDir, entry.name));
    if (0 === checkouts.length) {
      return;
    }

    const oldest = checkouts.reduce((a, b) => (markerMtime(b) < markerMtime(a) ? b : a));
    workspace.log(`workspace cache over ${String(workspace.maxSizeMb)}MB, evicting ${oldest}`);
    rmSync(oldest, { recursive: true, force: true });
  }
}

function excludeMarkerFromGit(repoDir: string): void {
  const excludeFile = join(repoDir, '.git', 'info', 'exclude');
  const existing = existsSync(excludeFile) ? readFileSync(excludeFile, 'utf8') : '';
  if (existing.split('\n').includes(LAST_USED_MARKER)) {
    return;
  }
  mkdirSync(join(repoDir, '.git', 'info'), { recursive: true });
  appendFileSync(excludeFile, `${LAST_USED_MARKER}\n`);
}

/** Returns the ready-to-use working directory; throws (GitError) when any git step fails. */
export async function syncRepo(workspace: Workspace, credentials: GitLabCredentials, project: WorkspaceProject): Promise<string> {
  const repoDir = join(workspace.cacheDir, project.externalId);
  const auth = gitAuthEnv(credentials.accessToken);

  if (existsSync(join(repoDir, '.git'))) {
    workspace.log(`reusing cached checkout for ${project.path}`);
    await workspace.git(['fetch', '--prune', 'origin'], repoDir, auth);
    await workspace.git(['checkout', '-f', project.defaultBranch], repoDir);
    await workspace.git(['reset', '--hard', `origin/${project.defaultBranch}`], repoDir);
    await workspace.git(['clean', '-fdx'], repoDir);
  } else {
    mkdirSync(workspace.cacheDir, { recursive: true });
    evictLruUntilUnderCap(workspace);
    workspace.log(`cloning ${project.path} (first use on this runner)`);
    const cloneUrl = `${credentials.baseUrl.replace(/\/+$/, '')}/${project.path}.git`;
    await workspace.git(['clone', '--origin', 'origin', cloneUrl, repoDir], undefined, auth);
  }

  touchMarker(repoDir);
  excludeMarkerFromGit(repoDir);
  return repoDir;
}

/** The commit message and merge request text a change is published with — see job.ts's changeWording. */
export interface ChangeWording {
  commitSubject: string;
  commitBody: string;
  title: string;
  description: string;
}

/**
 * Commits whatever the job changed, force-pushes it to a branch deterministic per shift
 * target (so a re-run overwrites its own previous attempt instead of piling up branches)
 * and opens — or refreshes — the one merge request for that branch. Returns null when
 * there was nothing to commit: a change job that legitimately decided no change was
 * needed. Throws when commit, push or MR publication fails, which the caller treats as a
 * job failure rather than leaving the target MERGE_REQUEST_OPEN with no real merge
 * request behind it.
 */
export async function publishChange(
  workspace: Workspace,
  credentials: GitLabCredentials,
  repoDir: string,
  project: WorkspaceProject,
  shiftTargetId: string,
  wording: ChangeWording,
  fetchFn: FetchFn = fetch,
): Promise<PublishedChange | null> {
  const branchName = `refleet/change-${shiftTargetId}`;

  await workspace.git(['add', '-A'], repoDir);
  const staged = await workspace.git(['diff', '--cached', '--name-only'], repoDir);
  if ('' === staged.stdout.trim()) {
    workspace.log('job produced no file changes, skipping branch/MR creation');
    return null;
  }

  const commitMessage = '' === wording.commitBody ? [wording.commitSubject] : [wording.commitSubject, wording.commitBody];
  await workspace.git(['-c', 'user.name=Refleet', '-c', 'user.email=refleet@localhost', 'commit', ...commitMessage.flatMap(part => ['-m', part])], repoDir);
  await workspace.git(['push', '--force', 'origin', `HEAD:refs/heads/${branchName}`], repoDir, gitAuthEnv(credentials.accessToken));

  // Only the colour is at stake here: the MR gets the label either way, and GitLab
  // creates a missing one on the fly in a random colour.
  const labelled = await ensureRefleetLabel(credentials, project.externalId, fetchFn);
  if (!labelled) {
    workspace.log(`could not ensure the "${REFLEET_LABEL.name}" label on ${project.path}; GitLab will create it with its own colour`);
  }

  const mergeRequest = await publishMergeRequest(
    credentials,
    project.externalId,
    { sourceBranch: branchName, targetBranch: project.defaultBranch, title: wording.title, description: wording.description },
    fetchFn,
  );

  return { branchName, mergeRequestUrl: mergeRequest.url, mergeRequestIid: mergeRequest.iid };
}
