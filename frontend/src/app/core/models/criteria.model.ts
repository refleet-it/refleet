/**
 * Shared vocabulary for "how a runner should evaluate/apply a criterion" — used by both
 * Qualification's criteria and Shift's change criteria, and by RunnerJob.mode. AI is the
 * only mode left (static regex rewrites were dropped); it stays a named value because the
 * backend keeps it on the wire.
 */
export type CriteriaMode = 'ai';

/** Which agent CLI runs the prompt, defaulting to "claude". */
export type CriteriaEngine = 'claude' | 'kiro';

/**
 * The agents a runner can be asked to run a prompt through. Same set as CriteriaEngine —
 * kept as its own name where the code means "an installable agent" rather than "the
 * engine recorded on a criteria".
 */
export type AiAgentEngine = CriteriaEngine;

/**
 * Overrides which model the runner invokes for a prompt; omitted/null means the runner
 * falls back to its own configured default. Not a fixed set of values — valid ids come
 * from whatever the organization's runner fleet reports (see RunnerService.availableModels
 * and GET /runners/available-models), since neither the claude CLI nor kiro-cli's ACP
 * implementation expose a way to list this at runtime.
 */
export type AiModel = string;

export const CRITERIA_MODE_LABELS: Record<CriteriaMode, string> = {
  ai: 'AI (prompt)',
};

export const CRITERIA_ENGINE_LABELS: Record<CriteriaEngine, string> = {
  claude: 'Claude Code',
  kiro: 'Kiro',
};

export const AI_AGENT_ENGINE_LABELS: Record<AiAgentEngine, string> = {
  claude: 'Claude Code',
  kiro: 'Kiro',
};
