import { AiModel, CriteriaEngine } from './criteria.model';

/**
 * What a playbook contributes to a prompt: a task is the change or criteria itself (one per
 * prompt, may take parameters and carry a preferred engine/model); a rule is a standing
 * instruction that goes in front of it (any number, concatenated, optionally on by default).
 */
export type PlaybookKind = 'task' | 'rule';

export type PlaybookAppliesTo = 'change' | 'qualification' | 'both';

/** The two prompt kinds a playbook can be composed for — never "both" here. */
export type PlaybookUsage = Exclude<PlaybookAppliesTo, 'both'>;

export interface PlaybookParameter {
  name: string;
  label: string;
  default: string | null;
  required: boolean;
}

export interface Playbook {
  /** A UUID for the organization's own playbooks, "builtin:<name>" for the shipped ones. */
  id: string;
  name: string;
  description: string | null;
  kind: PlaybookKind;
  appliesTo: PlaybookAppliesTo;
  body: string;
  default: boolean;
  parameters: PlaybookParameter[];
  engine: CriteriaEngine | null;
  model: AiModel | null;
  builtIn: boolean;
}

export interface PlaybookList {
  playbooks: Playbook[];
}

export interface PlaybookPayload {
  name: string;
  description?: string;
  kind: PlaybookKind;
  appliesTo: PlaybookAppliesTo;
  body: string;
  default: boolean;
  parameters: PlaybookParameter[];
  engine?: CriteriaEngine;
  model?: AiModel;
}

export interface ComposePromptPayload {
  appliesTo: PlaybookUsage;
  ruleIds: string[];
  taskId?: string;
  parameters: Record<string, string>;
}

/**
 * Which playbook a piece of a prompt was composed from. Recorded on a shift/qualification
 * purely for display — the text is the snapshot the agent runs.
 */
export interface PromptSource {
  id: string;
  name: string;
  kind: PlaybookKind;
  builtIn: boolean;
}

export interface ComposedPrompt {
  rules: string | null;
  prompt: string | null;
  engine: CriteriaEngine | null;
  model: AiModel | null;
  sources: PromptSource[];
}

export const PLAYBOOK_KIND_LABELS: Record<PlaybookKind, string> = {
  task: 'Task',
  rule: 'Rule',
};

export const PLAYBOOK_APPLIES_TO_LABELS: Record<PlaybookAppliesTo, string> = {
  change: 'Shifts',
  qualification: 'Qualifications',
  both: 'Shifts & qualifications',
};

export function playbookAppliesTo(
  playbook: Pick<Playbook, 'appliesTo'>,
  usage: PlaybookUsage
): boolean {
  return 'both' === playbook.appliesTo || playbook.appliesTo === usage;
}
