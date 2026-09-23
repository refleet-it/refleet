/**
 * A RunnerJob is always a single one-shot prompt (no session resume, no follow-up
 * turns) — the runner fleet member claims a job, runs one prompt to completion, and
 * reports the result. This mirrors multica's Backend interface but drops session/streaming
 * concerns that don't apply here.
 */
export interface ExecOptions {
  /** Working directory the agent operates in — the job's checked-out repo. */
  cwd: string;
  /** Per-job model override (RunnerJob.payload.model); absent means the backend's own default. */
  model?: string;
  /**
   * "qualification" jobs are a read-only yes/no decision and must not modify cwd;
   * "change" jobs make real edits. Only consumed by the claude backend today (mirrors
   * runner/claude/entrypoint.sh's CLAUDE_QUALIFICATION_*  vs CLAUDE_* split) — kiro
   * ignores it and runs the same way for both kinds.
   */
  kind: 'qualification' | 'change';
}

/**
 * One subscription rate-limit window as the claude CLI reports it in a stream-json
 * `rate_limit_event` (five_hour, seven_day, seven_day:<model>, extra_usage). kiro-cli has
 * no equivalent — ACP deliberately leaves quotas out of its usage_update notification.
 */
export interface RateLimitWindow {
  window: string;
  status: 'allowed' | 'allowed_warning' | 'rejected';
  /** Share of the window already consumed, 0..1; null when the CLI build does not report it. */
  utilization: number | null;
  /** ISO 8601; null when unknown. */
  resetsAt: string | null;
}

/**
 * What a single agent run told us about its consumption, reported to the backend on
 * the next heartbeat and shown on the runner's detail page. Every section is null when
 * the engine has no way to report it: rate limits are claude-only, the context window
 * comes from ACP's usage_update (kiro) or the final stream-json result (claude).
 */
export interface AgentUsage {
  /** ISO 8601 moment the run finished. */
  observedAt: string;
  rateLimits: RateLimitWindow[] | null;
  /** Main-agent context occupancy at the end of the run, in tokens. */
  context: { used: number; size: number } | null;
  /** Tokens billed for the whole run; input counts cache reads/writes too. */
  tokens: { input: number; output: number } | null;
  cost: { amount: number; currency: string } | null;
}

export interface AgentResult {
  /** The agent's final text output — printed verbatim to stdout by run.ts. */
  output: string;
  usage: AgentUsage | null;
}

export interface AgentBackend {
  execute(prompt: string, opts: ExecOptions): Promise<AgentResult>;
}

export class AgentExecutionError extends Error {
  /**
   * A run that died of a rejected rate limit is exactly the one whose usage the
   * dashboard should show, so a failure carries whatever the CLI reported before
   * exiting.
   */
  constructor(
    message: string,
    public usage: AgentUsage | null = null,
  ) {
    super(message);
  }
}

/** Keeps a number only when it is finite — CLIs are free to omit or null any counter. */
export function finiteNumber(value: unknown): number | null {
  return 'number' === typeof value && Number.isFinite(value) ? value : null;
}

/**
 * The agent runs untrusted code and prompts from a customer repository with tool
 * permissions switched off, so anything it can read from its environment it can also be
 * talked into exfiltrating. The runner's own credentials — the API key that fetches
 * GitLab tokens — have no business there.
 */
export function agentEnv(env: NodeJS.ProcessEnv): NodeJS.ProcessEnv {
  return Object.fromEntries(Object.entries(env).filter(([name]) => !name.startsWith('REFLEET_')));
}
