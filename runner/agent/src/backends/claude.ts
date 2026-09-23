import { spawn } from 'node:child_process';
import type { AgentBackend, AgentResult, AgentUsage, ExecOptions, RateLimitWindow } from './types.js';
import { agentEnv, AgentExecutionError, finiteNumber } from './types.js';

export type SpawnFn = typeof spawn;

/**
 * Builds the `claude` CLI argument list for one job kind. Ported 1:1 from
 * runner/claude/entrypoint.sh's build_claude_args/build_kind_claude_args: qualification
 * jobs are a read-only yes/no decision (must not modify the checkout) and default to
 * read-only tools + an optional cheaper model, while change jobs get whatever
 * model/tool access the operator configured for CLAUDE_MODEL/CLAUDE_ALLOWED_TOOLS.
 *
 * Unlike the bash version, this always adds --output-format stream-json so the output
 * is structured NDJSON instead of the CLI's human-oriented text — see extractFinalText.
 */
export function buildClaudeArgs(
  kind: 'qualification' | 'change',
  modelOverride: string | undefined,
  env: NodeJS.ProcessEnv,
): string[] {
  const args: string[] = [];

  const permissionMode = env.CLAUDE_PERMISSION_MODE;
  if (permissionMode) {
    args.push('--permission-mode', permissionMode);
  }

  // Defaults to true: headless (`claude -p`, no TTY) has no one to interactively
  // approve Edit/Write/Bash prompts, so without this the agent can only report that
  // it lacks permission instead of acting.
  const skipPermissions = env.CLAUDE_SKIP_PERMISSIONS ?? 'true';
  if ('true' === skipPermissions) {
    args.push('--dangerously-skip-permissions');
  }

  if (env.CLAUDE_ADD_DIRS) {
    args.push('--add-dir', ...splitCsv(env.CLAUDE_ADD_DIRS));
  }

  if (env.CLAUDE_EXTRA_ARGS) {
    args.push(...env.CLAUDE_EXTRA_ARGS.split(/\s+/).filter(Boolean));
  }

  const isQualification = 'qualification' === kind;
  const model =
    modelOverride ||
    (isQualification ? env.CLAUDE_QUALIFICATION_MODEL || env.CLAUDE_MODEL : env.CLAUDE_MODEL);
  const allowedToolsCsv = isQualification
    ? env.CLAUDE_QUALIFICATION_ALLOWED_TOOLS || env.CLAUDE_ALLOWED_TOOLS || 'Read,Grep,Glob'
    : env.CLAUDE_ALLOWED_TOOLS;

  if (model) {
    args.push('--model', model);
  }
  if (allowedToolsCsv) {
    args.push('--allowedTools', ...splitCsv(allowedToolsCsv));
  }

  args.push('--output-format', 'stream-json', '--verbose');

  return args;
}

function splitCsv(value: string): string[] {
  return value
    .split(',')
    .map(part => part.trim())
    .filter(Boolean);
}

interface TokenCounts {
  input_tokens?: unknown;
  output_tokens?: unknown;
  cache_creation_input_tokens?: unknown;
  cache_read_input_tokens?: unknown;
}

interface RateLimitInfo {
  status?: unknown;
  resetsAt?: unknown;
  rateLimitType?: unknown;
  utilization?: unknown;
  unifiedWindows?: Record<string, { utilization?: unknown; resetsAt?: unknown; status?: unknown }>;
}

interface StreamJsonEvent {
  type?: string;
  subtype?: string;
  result?: string;
  is_error?: boolean;
  message?: {
    role?: string;
    content?: { type?: string; text?: string }[];
    usage?: TokenCounts;
  };
  usage?: TokenCounts;
  total_cost_usd?: unknown;
  modelUsage?: Record<string, { contextWindow?: unknown }>;
  rate_limit_info?: RateLimitInfo;
}

function parseEvents(lines: string[]): StreamJsonEvent[] {
  const events: StreamJsonEvent[] = [];
  for (const line of lines) {
    const trimmed = line.trim();
    if ('' === trimmed) {
      continue;
    }
    try {
      events.push(JSON.parse(trimmed) as StreamJsonEvent);
    } catch {
      // Stray log lines on stdout should not sink an otherwise-successful run.
    }
  }
  return events;
}

function lastEvent(events: StreamJsonEvent[], matches: (event: StreamJsonEvent) => boolean): StreamJsonEvent | undefined {
  for (let index = events.length - 1; index >= 0; index -= 1) {
    const event = events[index];
    if (event && matches(event)) {
      return event;
    }
  }
  return undefined;
}

const RATE_LIMIT_STATUSES: ReadonlySet<string> = new Set(['allowed', 'allowed_warning', 'rejected']);

/** The CLI has reported utilization both as a 0..1 fraction and as whole percent. */
function normalizeUtilization(value: unknown): number | null {
  const number = finiteNumber(value);
  if (null === number || number < 0) {
    return null;
  }
  return number > 1 ? number / 100 : number;
}

function unixSecondsToIso(value: unknown): string | null {
  const seconds = finiteNumber(value);
  return null === seconds ? null : new Date(seconds * 1000).toISOString();
}

function rateLimitStatus(value: unknown): RateLimitWindow['status'] {
  return 'string' === typeof value && RATE_LIMIT_STATUSES.has(value) ? (value as RateLimitWindow['status']) : 'allowed';
}

/**
 * Folds every `rate_limit_event` into one entry per window, last report winning. Newer
 * CLI builds carry all windows at once under `unifiedWindows`; older ones emit one
 * event per `rateLimitType`, and only some of them include a utilization at all
 * (anthropics/claude-code#78476) — so the percentage is best-effort, the status is not.
 */
function foldRateLimits(events: StreamJsonEvent[]): RateLimitWindow[] | null {
  const windows = new Map<string, RateLimitWindow>();

  for (const event of events) {
    const info = event.rate_limit_info;
    if ('rate_limit_event' !== event.type || !info) {
      continue;
    }

    if (info.unifiedWindows && 'object' === typeof info.unifiedWindows) {
      for (const [window, details] of Object.entries(info.unifiedWindows)) {
        windows.set(window, {
          window,
          status: rateLimitStatus(details.status ?? info.status),
          utilization: normalizeUtilization(details.utilization),
          resetsAt: unixSecondsToIso(details.resetsAt),
        });
      }
      continue;
    }

    const window = 'string' === typeof info.rateLimitType && '' !== info.rateLimitType ? info.rateLimitType : null;
    if (null === window) {
      continue;
    }
    windows.set(window, {
      window,
      status: rateLimitStatus(info.status),
      utilization: normalizeUtilization(info.utilization),
      resetsAt: unixSecondsToIso(info.resetsAt),
    });
  }

  return windows.size > 0 ? [...windows.values()] : null;
}

/** Everything the API charged for a message: fresh input plus what was written to / read from the prompt cache. */
function billedInput(counts: TokenCounts): number | null {
  const input = finiteNumber(counts.input_tokens);
  if (null === input) {
    return null;
  }
  return input + (finiteNumber(counts.cache_creation_input_tokens) ?? 0) + (finiteNumber(counts.cache_read_input_tokens) ?? 0);
}

/**
 * Builds the run's usage report from the same NDJSON extractFinalText reads. The
 * closing `result` event carries the run's token totals, its cost and (per model) the
 * context window size; the last `assistant` message's own usage is the size of the
 * final API call's prompt, i.e. how full the context was when the run ended.
 */
export function extractUsage(lines: string[], observedAt: Date = new Date()): AgentUsage | null {
  const events = parseEvents(lines);
  const result = lastEvent(events, event => 'result' === event.type);
  const lastAssistant = lastEvent(events, event => 'assistant' === event.type && undefined !== event.message?.usage);

  const rateLimits = foldRateLimits(events);

  const input = result?.usage ? billedInput(result.usage) : null;
  const output = finiteNumber(result?.usage?.output_tokens);
  const tokens = null !== input && null !== output ? { input, output } : null;

  const amount = finiteNumber(result?.total_cost_usd);
  const cost = null === amount ? null : { amount, currency: 'USD' };

  const used = lastAssistant?.message?.usage ? billedInput(lastAssistant.message.usage) : null;
  const size = Object.values(result?.modelUsage ?? {})
    .map(model => finiteNumber(model.contextWindow))
    .reduce<number | null>((max, window) => (null === window ? max : Math.max(max ?? 0, window)), null);
  const context = null !== used && null !== size ? { used, size } : null;

  if (null === rateLimits && null === tokens && null === cost && null === context) {
    return null;
  }

  return { observedAt: observedAt.toISOString(), rateLimits, context, tokens, cost };
}

/**
 * Extracts the agent's final text from `claude --output-format stream-json` NDJSON
 * output. The CLI's own closing `type: "result"` event carries the complete final
 * answer in `.result`; if the process exits before emitting one (or on an older CLI
 * that omits it), falls back to concatenating every streamed assistant text block.
 *
 * Non-JSON lines are skipped rather than failing the whole parse — stray log lines on
 * stdout should not sink an otherwise-successful run.
 */
export function extractFinalText(lines: string[]): { output: string; isError: boolean } {
  const assistantTextParts: string[] = [];
  let resultText: string | null = null;
  let isError = false;

  for (const event of parseEvents(lines)) {
    if ('result' === event.type) {
      resultText = event.result ?? resultText;
      isError = event.is_error ?? isError;
      continue;
    }

    if ('assistant' === event.type && 'assistant' === event.message?.role) {
      for (const block of event.message.content ?? []) {
        if ('text' === block.type && block.text) {
          assistantTextParts.push(block.text);
        }
      }
    }
  }

  const output = resultText ?? assistantTextParts.join('');
  return { output, isError };
}

export class ClaudeBackend implements AgentBackend {
  constructor(
    private readonly executablePath: string = 'claude',
    private readonly env: NodeJS.ProcessEnv = process.env,
    private readonly spawnFn: SpawnFn = spawn,
  ) {}

  async execute(prompt: string, opts: ExecOptions): Promise<AgentResult> {
    const args = ['-p', prompt, ...buildClaudeArgs(opts.kind, opts.model, this.env)];

    const { lines, code } = await new Promise<{ lines: string[]; code: number | null }>((resolve, reject) => {
      const collected: string[] = [];
      let buffer = '';

      const child = this.spawnFn(this.executablePath, args, {
        cwd: opts.cwd,
        env: agentEnv(this.env),
        stdio: ['ignore', 'pipe', 'inherit'],
      });

      child.stdout.setEncoding('utf8');
      child.stdout.on('data', (chunk: string) => {
        buffer += chunk;
        const parts = buffer.split('\n');
        buffer = parts.pop() ?? '';
        collected.push(...parts);
      });

      child.on('error', err => {
        reject(new AgentExecutionError(`failed to start claude: ${err.message}`));
      });

      child.on('close', code => {
        if ('' !== buffer) {
          collected.push(buffer);
        }
        resolve({ lines: collected, code });
      });
    });

    const usage = extractUsage(lines);
    if (0 !== code) {
      throw new AgentExecutionError(`claude exited with status ${String(code)}`, usage);
    }

    const { output, isError } = extractFinalText(lines);

    if (isError) {
      throw new AgentExecutionError(output || 'claude reported an error with no message', usage);
    }
    if ('' === output.trim()) {
      throw new AgentExecutionError('claude produced no output', usage);
    }

    return { output, usage };
  }
}
