import type { FetchFn } from '../backend-client.js';
import { AgentExecutionError, type AgentUsage } from '../backends/types.js';
import type { ClaimedJob, FleetApi, JobReport, RunnerIdentity } from './api.js';
import type { JobExecutor, JobKind } from './execute.js';
import type { Logger } from './log.js';
import { type ChangeResult, parseChangeResult, parseQualificationResult, QUALIFICATION_SCORE_MAX, QUALIFICATION_SCORE_MIN } from './result.js';
import {
  type ChangeWording,
  publishChange as defaultPublishChange,
  syncRepo as defaultSyncRepo,
  type PublishedChange,
  type Workspace,
  type WorkspaceProject,
} from './workspace.js';

/** The backend's summary/errorMessage columns are bounded; keep the tail, where the verdict is. */
const REPORT_TAIL_CHARS = 4000;

/**
 * The most recent AgentUsage this runner observed, shared between the job runner
 * (writes it after every agent run, successful or not) and the poll loop (sends it
 * on the next heartbeat). Never cleared: a job that reports nothing leaves the last
 * known figures standing rather than blanking the dashboard.
 */
export interface UsageTracker {
  latest: AgentUsage | null;
}

export interface JobRunnerDeps {
  api: FleetApi;
  identity: RunnerIdentity;
  workspace: Workspace;
  execute: JobExecutor;
  log: Logger;
  usage?: UsageTracker;
  fetchFn?: FetchFn;
  syncRepo?: typeof defaultSyncRepo;
  publishChange?: typeof defaultPublishChange;
}

type Report = (outcome: JobReport['outcome'], summary: string, extra?: Omit<JobReport, 'outcome' | 'summary'>) => Promise<void>;

function tail(text: string): string {
  return text.length > REPORT_TAIL_CHARS ? text.slice(-REPORT_TAIL_CHARS) : text;
}

function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : String(err);
}

/**
 * What the commit and merge request say. The agent proposes it (see the answer contract
 * in ShiftJobPayloadFactory); anything it left out or got wrong degrades to the shift
 * title and the summary — exactly what every change carried before the agent had a say.
 */
export function changeWording(job: Pick<ClaimedJob, 'ownerId' | 'ownerLabel'>, result: ChangeResult | null, summary: string): ChangeWording {
  const shiftTitle = job.ownerLabel?.trim() || `Refleet shift ${job.ownerId ?? ''}`;
  const commitSubject = result?.commit?.subject ?? shiftTitle;
  return {
    commitSubject,
    commitBody: result?.commit?.body ?? '',
    title: result?.mergeRequest?.title ?? commitSubject,
    description: result?.mergeRequest?.description || summary,
  };
}

function projectOf(job: ClaimedJob): WorkspaceProject | null {
  const project = job.payload?.project;
  if (!project?.externalId || !project.path) {
    return null;
  }
  return { externalId: project.externalId, path: project.path, defaultBranch: project.defaultBranch ?? 'main' };
}

/**
 * Claims at most one job and runs it to completion. Every failure mode after a
 * successful claim is reported back as a job failure and swallowed — the poll loop
 * must keep going — so only a genuinely unexpected exception escapes.
 */
export async function claimAndRunJob(deps: JobRunnerDeps): Promise<void> {
  const job = await deps.api.claimJob(deps.identity);
  if (!job) {
    return;
  }

  const report: Report = (outcome, summary, extra = {}) => deps.api.reportJob(deps.identity, job.jobId, { outcome, summary, ...extra });

  const project = projectOf(job);
  if (!project) {
    deps.log(`job ${job.jobId} is missing project info, reporting failure`);
    await report('failure', 'Runner job payload is missing project information', { errorMessage: 'missing project info' });
    return;
  }

  const credentials = await deps.api.fetchGitLabCredentials();
  if (!credentials) {
    deps.log(`job ${job.jobId}: failed to fetch GitLab credentials from Refleet`);
    await report('failure', 'Failed to fetch GitLab credentials from Refleet', { errorMessage: 'gitlab credentials fetch failed' });
    return;
  }

  let repoDir: string;
  try {
    repoDir = await (deps.syncRepo ?? defaultSyncRepo)(deps.workspace, credentials, project);
  } catch (err) {
    deps.log(`job ${job.jobId}: failed to check out ${project.path}: ${errorMessage(err)}`);
    await report('failure', `Failed to clone/update ${project.path}`, { errorMessage: tail(`git sync failed: ${errorMessage(err)}`) });
    return;
  }

  const kind: JobKind = 'qualification' === job.kind ? 'qualification' : 'change';
  deps.log(`claimed job ${job.jobId} (${kind}) for project ${project.path}, running in ${repoDir}`);

  let output: string;
  try {
    const result = await deps.execute(kind, job.payload ?? {}, repoDir);
    output = result.output;
    recordUsage(deps, result.usage);
  } catch (err) {
    recordUsage(deps, err instanceof AgentExecutionError ? err.usage : null);
    deps.log(`job ${job.jobId} failed`);
    await report('failure', 'Task execution failed', { errorMessage: tail(errorMessage(err)) });
    return;
  }

  if ('qualification' === kind) {
    await reportQualification(deps, job, output, report);
    return;
  }

  await publishAndReport(deps, job, repoDir, project, output, report);
}

function recordUsage(deps: JobRunnerDeps, usage: AgentUsage | null): void {
  if (deps.usage && null !== usage) {
    deps.usage.latest = usage;
  }
}

/**
 * The score is the decision, so a response without a valid one is a failed run (to be
 * retried), never a "not qualified". The cut-off itself lives in the backend's domain.
 */
async function reportQualification(deps: JobRunnerDeps, job: ClaimedJob, output: string, report: Report): Promise<void> {
  const result = parseQualificationResult(output);
  if (null === result) {
    deps.log(`job ${job.jobId} succeeded but returned no valid qualification score`);
    await report('failure', `Agent did not return a qualification score between ${String(QUALIFICATION_SCORE_MIN)} and ${String(QUALIFICATION_SCORE_MAX)}`, {
      errorMessage: tail(output),
    });
    return;
  }

  deps.log(`job ${job.jobId} succeeded, score=${String(result.score)}`);
  await report('success', tail(result.reasoning || output), { score: result.score });
}

async function publishAndReport(
  deps: JobRunnerDeps,
  job: ClaimedJob,
  repoDir: string,
  project: WorkspaceProject,
  output: string,
  report: Report,
): Promise<void> {
  // The prompt asks for a JSON summary, but a change that landed is worth reporting
  // even when the agent skipped it — the merge request, not the prose, is the result.
  const result = parseChangeResult(output);
  const summary = tail(result?.summary ?? output);
  const wording = changeWording(job, result, summary);

  // The agent may have run for longer than the access token fetched for the clone lives.
  const credentials = await deps.api.fetchGitLabCredentials();
  if (!credentials) {
    deps.log(`job ${job.jobId}: failed to fetch GitLab credentials from Refleet before publishing`);
    await report('failure', 'Failed to fetch GitLab credentials from Refleet', { errorMessage: 'gitlab credentials fetch failed' });
    return;
  }

  let published: PublishedChange | null;
  try {
    published = await (deps.publishChange ?? defaultPublishChange)(
      deps.workspace,
      credentials,
      repoDir,
      project,
      job.ownerTargetId ?? '',
      wording,
      deps.fetchFn,
    );
  } catch (err) {
    deps.log(`job ${job.jobId}: failed to push the change / open a merge request: ${errorMessage(err)}`);
    await report('failure', 'Failed to push the change and open a GitLab merge request', { errorMessage: tail(errorMessage(err)) });
    return;
  }

  if (!published) {
    deps.log(`job ${job.jobId} succeeded with no changes to publish`);
    await report('success', summary);
    return;
  }

  deps.log(`job ${job.jobId} succeeded, merge request ${published.mergeRequestUrl}`);
  await report('success', summary, {
    branchName: published.branchName,
    mergeRequestUrl: published.mergeRequestUrl,
    mergeRequestIid: published.mergeRequestIid,
  });
}
