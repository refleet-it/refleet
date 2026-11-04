import { AiModel, CriteriaEngine, CriteriaMode } from './criteria.model';
import { PromptSource } from './playbook.model';
import { PageInfo } from './pagination.model';
import { StatusBadgeVariant } from './status-badge.model';

export type ShiftStatus = 'draft' | 'applying_change' | 'completed' | 'cancelled';

export type ShiftTargetStatus =
  | 'pending_change'
  | 'change_in_progress'
  | 'change_failed'
  | 'no_changes'
  | 'merge_request_open'
  | 'completed'
  | 'merge_request_closed'
  | 'cancelled';

export type MergeRequestStatus = 'none' | 'open' | 'merged' | 'closed';

export type PipelineStatus =
  | 'not_watched'
  | 'waiting'
  | 'passed'
  | 'fixing'
  | 'unrelated'
  | 'gave_up'
  | 'inconclusive'
  | 'no_pipeline'
  | 'stood_down';

/** Shift statuses from which no further transition is possible. */
export const TERMINAL_SHIFT_STATUSES: readonly ShiftStatus[] = ['completed', 'cancelled'];

/** Statuses from which a shift can be archived — anything not in flight. */
export const ARCHIVABLE_SHIFT_STATUSES: readonly ShiftStatus[] = [
  'draft',
  'completed',
  'cancelled',
];

export const SHIFT_STATUS_LABELS: Record<ShiftStatus, string> = {
  draft: 'Draft',
  applying_change: 'Applying Change',
  completed: 'Completed',
  cancelled: 'Cancelled',
};

export const SHIFT_STATUS_BADGE_VARIANTS: Record<ShiftStatus, StatusBadgeVariant> = {
  draft: 'outline',
  applying_change: 'default',
  completed: 'success',
  cancelled: 'destructive',
};

/** Statuses where a runner is actively processing the shift. */
export const AGENT_WORKING_SHIFT_STATUSES: ReadonlySet<ShiftStatus> = new Set(['applying_change']);

/** Short badge text — see SHIFT_TARGET_STATUS_DESCRIPTIONS for the tooltip text. */
export const SHIFT_TARGET_STATUS_LABELS: Record<ShiftTargetStatus, string> = {
  pending_change: 'Pending',
  change_in_progress: 'Changing',
  change_failed: 'Change Failed',
  no_changes: 'No Changes',
  merge_request_open: 'MR Open',
  completed: 'Completed',
  merge_request_closed: 'MR Closed',
  cancelled: 'Cancelled',
};

export const SHIFT_TARGET_STATUS_DESCRIPTIONS: Record<ShiftTargetStatus, string> = {
  pending_change: 'Waiting for the change to start.',
  change_in_progress: 'A runner is currently applying the change to this project.',
  change_failed: 'Applying the change to this project failed.',
  no_changes: 'The agent found nothing to change in this project, so no merge request was opened.',
  merge_request_open: 'The change was applied and a merge request is open for review.',
  completed: 'The merge request was merged — this project is done.',
  merge_request_closed: 'The merge request was closed without merging.',
  cancelled: 'This target was cancelled and will not be processed.',
};

export const PIPELINE_STATUS_LABELS: Record<PipelineStatus, string> = {
  not_watched: 'Not watched',
  waiting: 'Watching pipeline',
  passed: 'Pipeline passed',
  fixing: 'Fixing pipeline',
  unrelated: 'Pipeline failed',
  gave_up: 'Pipeline not fixed',
  inconclusive: 'Pipeline inconclusive',
  no_pipeline: 'No pipeline',
  stood_down: 'Stood down',
};

export const PIPELINE_STATUS_DESCRIPTIONS: Record<PipelineStatus, string> = {
  not_watched: 'The pipeline on this merge request is not being watched.',
  waiting: 'Waiting for the pipeline on the merge request to finish.',
  passed: 'The pipeline on the merge request passed.',
  fixing: 'The pipeline failed and an agent is looking at it.',
  unrelated: 'The pipeline failed, but not because of this change. Left for you to look at.',
  gave_up:
    'The pipeline failed and the agent could not fix it. Retry the fix or take it from here.',
  inconclusive: 'The pipeline was cancelled, skipped or is waiting on a manual job.',
  no_pipeline: 'No pipeline appeared for this merge request, so the project may have no CI.',
  stood_down: 'Someone else pushed to the merge request, so Refleet stopped touching it.',
};

export const PIPELINE_STATUS_BADGE_VARIANTS: Record<PipelineStatus, StatusBadgeVariant> = {
  not_watched: 'outline',
  waiting: 'outline',
  passed: 'success',
  fixing: 'default',
  unrelated: 'destructive',
  gave_up: 'destructive',
  inconclusive: 'secondary',
  no_pipeline: 'secondary',
  stood_down: 'secondary',
};

export const SHIFT_TARGET_STATUS_BADGE_VARIANTS: Record<ShiftTargetStatus, StatusBadgeVariant> = {
  pending_change: 'outline',
  change_in_progress: 'default',
  change_failed: 'destructive',
  no_changes: 'secondary',
  merge_request_open: 'default',
  completed: 'success',
  merge_request_closed: 'outline',
  cancelled: 'destructive',
};

/** Target statuses where a runner is actively changing the project. */
export const AGENT_WORKING_SHIFT_TARGET_STATUSES: ReadonlySet<ShiftTargetStatus> = new Set([
  'change_in_progress',
]);

/**
 * Target statuses whose last run has settled and can be sent through the agent again —
 * the runner refreshes the merge request already open for the target instead of opening
 * another one.
 */
export const RERUNNABLE_SHIFT_TARGET_STATUSES: ReadonlySet<ShiftTargetStatus> = new Set([
  'change_failed',
  'merge_request_open',
  'merge_request_closed',
  'no_changes',
]);

/** Shift statuses in which a single target may be run (a trial on a draft) or re-run. */
export const TARGET_RUNNABLE_SHIFT_STATUSES: ReadonlySet<ShiftStatus> = new Set([
  'draft',
  'applying_change',
  'completed',
]);

export interface ShiftOverview {
  id: string;
  title: string;
  description: string | null;
  status: ShiftStatus;
  qualificationId: string | null;
  changeMode: CriteriaMode | null;
  targetCount: number;
  terminalTargetCount: number;
  statusBreakdown: Record<string, number>;
  progressPercent: number | null;
  createdAt: string;
  /** Set once the shift has been archived; archiving cannot be undone. */
  archivedAt: string | null;
}

export interface ShiftList {
  shifts: ShiftOverview[];
  pagination: PageInfo;
}

export interface ShiftDetails {
  id: string;
  organizationId: string;
  title: string;
  description: string | null;
  createdBy: string;
  status: ShiftStatus;
  qualificationId: string | null;
  changeMode: CriteriaMode | null;
  changeEngine: CriteriaEngine | null;
  changePrompt: string | null;
  changeModel: AiModel | null;
  /** Organization rules composed from playbooks and edited by the user; sent verbatim before the change. */
  changeRules: string | null;
  changeSources: PromptSource[];
  /** Whether an agent may push fixes when a merge request's pipeline fails because of the change. */
  changeFixPipelines: boolean;
  cancelReason: string | null;
  targetCount: number;
  statusBreakdown: Record<string, number>;
  progressPercent: number | null;
  createdAt: string;
  changeStartedAt: string | null;
  completedAt: string | null;
  cancelledAt: string | null;
  /** Set once the shift has been archived; archiving cannot be undone. */
  archivedAt: string | null;
}

export interface CreatedShift {
  id: string;
  title: string;
  status: ShiftStatus;
  qualificationId: string | null;
  createdAt: string;
}

/**
 * Three ways to resolve the target set:
 *  1. qualificationId only -> every currently QUALIFIED target of that qualification.
 *  2. qualificationId + projectIds -> those specific targets of that qualification.
 *  3. projectIds only (no qualificationId) -> a fully manual project selection.
 */
export interface CreateShiftPayload {
  title: string;
  description?: string;
  qualificationId?: string;
  projectIds?: string[];
}

export interface DefineShiftChangePayload {
  changeMode: CriteriaMode;
  changeEngine?: CriteriaEngine;
  changePrompt: string;
  changeModel?: AiModel;
  changeRules?: string;
  changeSources?: PromptSource[];
  changeFixPipelines?: boolean;
}

export interface ShiftChangePromptPreviewPayload {
  changePrompt: string;
  changeRules?: string;
}

export interface CancelShiftPayload {
  reason?: string;
}

export interface ReportShiftMergeRequestStatusPayload {
  status: 'opened' | 'merged' | 'closed';
  url?: string;
  externalIid?: string;
}

export interface ShiftTargetOverview {
  id: string;
  shiftId: string;
  projectId: string;
  projectName: string;
  projectPath: string;
  status: ShiftTargetStatus;
  changeSummary: string | null;
  /** Name of the runner that claimed the change job, once one has picked it up. */
  runnerName: string | null;
  /** Id of the runner identified by runnerName, if it still exists — links to its detail page. */
  runnerId: string | null;
  mergeRequestUrl: string | null;
  mergeRequestStatus: MergeRequestStatus;
  pipelineStatus: PipelineStatus;
  /** What the agent concluded the last time it looked at a failed pipeline. */
  pipelineSummary: string | null;
  pipelineFixAttempts: number;
  pipelineFixMaxAttempts: number;
  createdAt: string;
}

export interface ShiftTargetList {
  targets: ShiftTargetOverview[];
  pagination: PageInfo;
}

export interface ShiftTargetDetails extends Omit<
  ShiftTargetOverview,
  'projectName' | 'projectPath' | 'runnerId' | 'pipelineFixMaxAttempts'
> {
  organizationId: string;
  projectSnapshot: {
    externalId: string;
    path: string;
    name: string;
    defaultBranch: string | null;
  };
  changeBranchName: string | null;
  runnerJobId: string | null;
  changeStartedAt: string | null;
  changeCompletedAt: string | null;
  mergeRequestExternalIid: string | null;
}
