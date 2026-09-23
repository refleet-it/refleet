import { AiModel, CriteriaEngine, CriteriaMode } from './criteria.model';
import { PromptSource } from './playbook.model';
import { PageInfo } from './pagination.model';
import { StatusBadgeVariant } from './status-badge.model';

export type QualificationStatus = 'draft' | 'running' | 'completed' | 'cancelled';

export type QualificationTargetStatus =
  'pending' | 'in_progress' | 'qualified' | 'not_qualified' | 'failed' | 'cancelled';

/** Qualification statuses from which no further transition is possible. */
export const TERMINAL_QUALIFICATION_STATUSES: readonly QualificationStatus[] = [
  'completed',
  'cancelled',
];

/** Target statuses from which a manual qualification override is allowed. */
export const OVERRIDABLE_QUALIFICATION_TARGET_STATUSES: readonly QualificationTargetStatus[] = [
  'pending',
  'qualified',
  'not_qualified',
  'failed',
];

/** Statuses in which a failed target can be sent back to the runner queue. */
export const RETRYABLE_QUALIFICATION_STATUSES: readonly QualificationStatus[] = [
  'running',
  'completed',
];

/** Statuses from which a qualification can be archived — anything not in flight. */
export const ARCHIVABLE_QUALIFICATION_STATUSES: readonly QualificationStatus[] = [
  'draft',
  'completed',
  'cancelled',
];

export const QUALIFICATION_STATUS_LABELS: Record<QualificationStatus, string> = {
  draft: 'Draft',
  running: 'Running',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

export const QUALIFICATION_STATUS_BADGE_VARIANTS: Record<QualificationStatus, StatusBadgeVariant> =
  {
    draft: 'outline',
    running: 'default',
    completed: 'success',
    cancelled: 'destructive',
  };

/** Statuses where a runner is actively processing the qualification. */
export const AGENT_WORKING_QUALIFICATION_STATUSES: ReadonlySet<QualificationStatus> = new Set([
  'running',
]);

/** Short badge text — see QUALIFICATION_TARGET_STATUS_DESCRIPTIONS for the tooltip text. */
export const QUALIFICATION_TARGET_STATUS_LABELS: Record<QualificationTargetStatus, string> = {
  pending: 'Pending',
  in_progress: 'Checking',
  qualified: 'Qualified',
  not_qualified: 'Not Qualified',
  failed: 'Failed',
  cancelled: 'Cancelled',
};

export const QUALIFICATION_TARGET_STATUS_DESCRIPTIONS: Record<QualificationTargetStatus, string> = {
  pending: 'Waiting to be checked against the qualification criteria.',
  in_progress: 'A runner is currently checking this project against the criteria.',
  qualified: 'This project met the criteria.',
  not_qualified: 'This project did not meet the criteria.',
  failed: 'The check errored out before it could reach a decision.',
  cancelled: 'This target was cancelled and will not be processed.',
};

export const QUALIFICATION_TARGET_STATUS_BADGE_VARIANTS: Record<
  QualificationTargetStatus,
  StatusBadgeVariant
> = {
  pending: 'outline',
  in_progress: 'default',
  qualified: 'success',
  not_qualified: 'secondary',
  failed: 'destructive',
  cancelled: 'destructive',
};

/** Target statuses where a runner is actively checking the project. */
export const AGENT_WORKING_QUALIFICATION_TARGET_STATUSES: ReadonlySet<QualificationTargetStatus> =
  new Set(['in_progress']);

export interface QualificationOverview {
  id: string;
  title: string;
  description: string | null;
  status: QualificationStatus;
  qualificationMode: CriteriaMode;
  targetCount: number;
  terminalTargetCount: number;
  statusBreakdown: Record<string, number>;
  progressPercent: number | null;
  createdAt: string;
  /** Set once the qualification has been archived; archiving cannot be undone. */
  archivedAt: string | null;
}

export interface QualificationList {
  qualifications: QualificationOverview[];
  pagination: PageInfo;
}

export interface QualificationDetails {
  id: string;
  organizationId: string;
  title: string;
  description: string | null;
  createdBy: string;
  status: QualificationStatus;
  qualificationMode: CriteriaMode;
  qualificationEngine: CriteriaEngine | null;
  qualificationPrompt: string;
  qualificationModel: AiModel | null;
  /** Organization rules composed from playbooks and edited by the user; sent verbatim before the criteria. */
  qualificationRules: string | null;
  qualificationSources: PromptSource[];
  cancelReason: string | null;
  targetCount: number;
  statusBreakdown: Record<string, number>;
  progressPercent: number | null;
  createdAt: string;
  startedAt: string | null;
  completedAt: string | null;
  cancelledAt: string | null;
  /** Set once the qualification has been archived; archiving cannot be undone. */
  archivedAt: string | null;
}

export interface CreatedQualification {
  id: string;
  title: string;
  status: QualificationStatus;
  createdAt: string;
}

export interface CreateQualificationPayload {
  title: string;
  description?: string;
  qualificationMode: CriteriaMode;
  qualificationEngine?: CriteriaEngine;
  qualificationPrompt: string;
  qualificationModel?: AiModel;
  projectIds?: string[];
  qualificationRules?: string;
  qualificationSources?: PromptSource[];
}

export interface QualificationPromptPreviewPayload {
  qualificationPrompt: string;
  qualificationRules?: string;
}

/**
 * Carried as router navigation state from the detail page's "Run again" action to the
 * new-qualification page, so re-running a qualification starts from a form pre-filled
 * with its criteria and target projects instead of a blank one.
 */
export interface QualificationRerunPrefill {
  title: string;
  description: string;
  qualificationMode: CriteriaMode;
  qualificationEngine: CriteriaEngine | null;
  qualificationPrompt: string;
  qualificationModel: AiModel | null;
  qualificationRules: string | null;
  qualificationSources: PromptSource[];
  projectIds: string[];
}

export interface OverrideQualificationTargetPayload {
  qualified: boolean;
  note?: string;
}

export interface CancelQualificationPayload {
  reason?: string;
}

export interface QualificationTargetOverview {
  id: string;
  qualificationId: string;
  projectId: string;
  projectSnapshot: {
    externalId: string;
    path: string;
    name: string;
    defaultBranch: string | null;
  };
  status: QualificationTargetStatus;
  summary: string | null;
  /** The agent's 1–5 verdict behind the automatic status (4–5 qualify); null until a run succeeds. */
  score: number | null;
  overridden: boolean;
  overrideNote: string | null;
  /** Name of the runner that claimed this target's job, once one has picked it up. */
  runnerName: string | null;
  /** Id of the runner identified by runnerName, if it still exists — links to its detail page. */
  runnerId: string | null;
  createdAt: string;
}

export interface QualificationTargetList {
  targets: QualificationTargetOverview[];
  pagination: PageInfo;
}

export interface QualificationTargetDetails extends Omit<QualificationTargetOverview, 'runnerId'> {
  organizationId: string;
  runnerJobId: string | null;
  startedAt: string | null;
  completedAt: string | null;
}
