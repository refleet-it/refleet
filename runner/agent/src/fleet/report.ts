import type { AgentUsage } from '../backends/types.js';
import type { JobReport } from './api.js';
import type { UsageTracker } from './job.js';

/** The backend's summary/errorMessage columns are bounded; keep the tail, where the verdict is. */
const REPORT_TAIL_CHARS = 4000;

export type Report = (outcome: JobReport['outcome'], summary: string, extra?: Omit<JobReport, 'outcome' | 'summary'>) => Promise<void>;

export function tail(text: string): string {
  return text.length > REPORT_TAIL_CHARS ? text.slice(-REPORT_TAIL_CHARS) : text;
}

export function errorMessage(err: unknown): string {
  return err instanceof Error ? err.message : String(err);
}

export function recordUsage(deps: { usage?: UsageTracker }, usage: AgentUsage | null): void {
  if (deps.usage && null !== usage) {
    deps.usage.latest = usage;
  }
}
