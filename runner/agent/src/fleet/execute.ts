import { detectAgents, type Engine } from '../detect.js';
import { ClaudeBackend } from '../backends/claude.js';
import { KiroBackend } from '../backends/kiro.js';
import type { AgentBackend, AgentResult } from '../backends/types.js';
import { AgentExecutionError } from '../backends/types.js';
import type { ClaimedJobPayload } from './api.js';

export type JobKind = 'qualification' | 'change';

/** Runs one job's prompt to completion and resolves with the agent's final text output and what the run consumed. */
export type JobExecutor = (kind: JobKind, payload: ClaimedJobPayload, repoDir: string) => Promise<AgentResult>;

/**
 * Re-runs the same PATH-based detection the fleet loop used to decide which engines to
 * register, so a job can never make this host execute a caller-supplied path — only an
 * engine it actually has installed.
 */
export function resolveBackend(engine: Engine): AgentBackend {
  const detected = detectAgents().find(agent => agent.engine === engine);
  if (!detected) {
    throw new AgentExecutionError(`engine "${engine}" was not detected on this host`);
  }
  return 'claude' === engine ? new ClaudeBackend(detected.path) : new KiroBackend(detected.path);
}

/**
 * Extracts the prompt/engine/model an AI-mode job carries (see
 * StartQualificationHandler/StartShiftChangeHandler's buildPayload) and hands it to
 * the matching backend, which spawns the CLI and speaks its native protocol.
 */
export const executeJob: JobExecutor = async (kind, payload, repoDir) => {
  const engine = payload.engine ?? 'claude';
  if ('claude' !== engine && 'kiro' !== engine) {
    throw new AgentExecutionError(`unsupported engine "${engine}"`);
  }

  const prompt = (payload.prompt ?? '').trim();
  if ('' === prompt) {
    throw new AgentExecutionError('job payload has no prompt');
  }

  return resolveBackend(engine).execute(prompt, { cwd: repoDir, kind, model: payload.model });
};
