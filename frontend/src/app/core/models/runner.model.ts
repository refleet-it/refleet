import { AiAgentEngine, CRITERIA_MODE_LABELS, CriteriaMode } from './criteria.model';
import { PageInfo } from './pagination.model';
import { StatusBadgeVariant } from './status-badge.model';

export type RunnerStatus = 'working' | 'idle' | 'offline';

export const RUNNER_STATUS_LABELS: Record<RunnerStatus, string> = {
  working: 'Working',
  idle: 'Idle',
  offline: 'Offline',
};

export const RUNNER_STATUS_BADGE_VARIANTS: Record<RunnerStatus, StatusBadgeVariant> = {
  working: 'default',
  idle: 'secondary',
  offline: 'outline',
};

/** Statuses where the runner is busy with a job right now. */
export const AGENT_WORKING_RUNNER_STATUSES: ReadonlySet<RunnerStatus> = new Set(['working']);

export type RunnerRateLimitStatus = 'allowed' | 'allowed_warning' | 'rejected';

export const RUNNER_RATE_LIMIT_STATUS_LABELS: Record<RunnerRateLimitStatus, string> = {
  allowed: 'OK',
  allowed_warning: 'Near limit',
  rejected: 'Exhausted',
};

export const RUNNER_RATE_LIMIT_STATUS_BADGE_VARIANTS: Record<
  RunnerRateLimitStatus,
  StatusBadgeVariant
> = {
  allowed: 'success',
  allowed_warning: 'secondary',
  rejected: 'destructive',
};

/** One subscription rate-limit window as the claude CLI reports it; kiro has no equivalent. */
export interface RunnerRateLimitWindow {
  /** five_hour, seven_day, seven_day:<model> or extra_usage. */
  window: string;
  status: RunnerRateLimitStatus;
  /** Share of the window already consumed, 0..1; null when the CLI build does not report it. */
  utilization: number | null;
  resetsAt: string | null;
}

/**
 * What the runner's last agent run reported about its consumption — see
 * runner/agent/src/backends/types.ts AgentUsage. Every section is null when the engine
 * cannot report it.
 */
export interface RunnerUsage {
  observedAt: string;
  rateLimits: RunnerRateLimitWindow[] | null;
  /** Main-agent context occupancy at the end of the run, in tokens. */
  context: { used: number; size: number } | null;
  /** Tokens billed for the whole run; input counts cache reads/writes too. */
  tokens: { input: number; output: number } | null;
  cost: { amount: number; currency: string } | null;
}

const RATE_LIMIT_WINDOW_LABELS: Record<string, string> = {
  five_hour: '5-hour window',
  seven_day: '7-day window',
  extra_usage: 'Extra usage',
};

/** `seven_day:claude-opus-5` is a per-model weekly window; anything unknown is shown verbatim. */
export function rateLimitWindowLabel(window: string): string {
  const [kind, model] = window.split(':', 2);
  const label = RATE_LIMIT_WINDOW_LABELS[kind ?? ''] ?? window;
  return model ? `${label} (${model})` : label;
}

export interface RunnerOverview {
  id: string;
  name: string;
  status: RunnerStatus;
  lastSeenAt: string | null;
  createdAt: string;
  /** Set once the runner has been archived; archiving cannot be undone. */
  archivedAt: string | null;
  /** Agents this runner auto-detected on its host, reported on heartbeat; null until its first heartbeat with this field. */
  supportedEngines: AiAgentEngine[] | null;
  /** Model ids this runner was configured to offer for its engine(s), reported on heartbeat; null until its first heartbeat with this field, or if never configured. */
  supportedModels: string[] | null;
  /** What its last agent run consumed, reported on heartbeat; null until a job has run on it. */
  usage: RunnerUsage | null;
  /** The @refleet-it/runner version its process reported on heartbeat; null until its first heartbeat with this field. */
  version: string | null;
  /** The newest published runner version; null when the registry could not be reached. */
  latestVersion: string | null;
  /** True when both versions are known and this runner's is older. */
  updateAvailable: boolean;
  /** Set by "Update", cleared when the runner picks the request up on its next heartbeat. */
  updateRequestedAt: string | null;
}

/** Model ids currently offered across the organization's non-archived runner fleet, grouped by engine — see GET /runners/available-models. */
export interface AvailableModels {
  claude: string[];
  kiro: string[];
}

export interface RunnerList {
  runners: RunnerOverview[];
  pagination: PageInfo;
}

export type RunnerJobKind = 'qualification' | 'change';

export type RunnerJobStatus = 'pending' | 'claimed' | 'succeeded' | 'failed';

export const RUNNER_JOB_KIND_LABELS: Record<RunnerJobKind, string> = {
  qualification: 'Qualification',
  change: 'Change',
};

export const RUNNER_JOB_STATUS_LABELS: Record<RunnerJobStatus, string> = {
  pending: 'Pending',
  claimed: 'Claimed',
  succeeded: 'Succeeded',
  failed: 'Failed',
};

export const RUNNER_JOB_STATUS_BADGE_VARIANTS: Record<RunnerJobStatus, StatusBadgeVariant> = {
  pending: 'outline',
  claimed: 'default',
  succeeded: 'success',
  failed: 'destructive',
};

/** Job statuses where a runner is working on the job right now. */
export const AGENT_WORKING_RUNNER_JOB_STATUSES: ReadonlySet<RunnerJobStatus> = new Set(['claimed']);

export { CRITERIA_MODE_LABELS as RUNNER_JOB_MODE_LABELS };

export interface RunnerJobOverview {
  id: string;
  kind: RunnerJobKind;
  mode: CriteriaMode;
  status: RunnerJobStatus;
  /** Id of the owning Qualification (kind=qualification) or Shift (kind=change). */
  ownerId: string;
  /** Title of the owning Qualification/Shift, snapshotted at job creation time. */
  ownerLabel: string;
  ownerTargetId: string;
  projectName: string;
  attemptCount: number;
  resultSummary: string | null;
  errorMessage: string | null;
  createdAt: string;
  claimedAt: string | null;
  completedAt: string | null;
}

export interface RunnerJobList {
  jobs: RunnerJobOverview[];
  pagination: PageInfo;
}
