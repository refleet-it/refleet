import { spawn, type ChildProcessByStdio } from 'node:child_process';
import type { Readable, Writable } from 'node:stream';
import type { AgentBackend, AgentResult, AgentUsage, ExecOptions } from './types.js';
import { agentEnv, AgentExecutionError, finiteNumber } from './types.js';

/**
 * Pulls the ACP session id out of a `session/new` (or `session/load`) response.
 * Pure/testable in isolation from the actual JSON-RPC transport.
 */
export function extractSessionId(result: unknown): string | null {
  if (!result || 'object' !== typeof result) {
    return null;
  }
  const value = (result as { sessionId?: unknown }).sessionId;
  return 'string' === typeof value && '' !== value ? value : null;
}

interface PermissionOption {
  optionId: string;
  kind?: string;
}

/**
 * Picks which option to reply with when kiro-cli asks for tool-use permission via a
 * peer-initiated `session/request_permission` request — this runner is headless (no
 * human present to click "allow"), so it always auto-approves, preferring
 * "allow_always" over "allow_once" to avoid re-prompting for the rest of the turn.
 * Falls back to the first offered option if neither is present (schema guarantees at
 * least one), so a run never hangs waiting for a human that isn't there.
 */
export function selectAutoApproveOptionId(options: PermissionOption[]): string | null {
  const allowAlways = options.find(option => 'allow_always' === option.kind);
  if (allowAlways) {
    return allowAlways.optionId;
  }
  const allowOnce = options.find(option => 'allow_once' === option.kind);
  if (allowOnce) {
    return allowOnce.optionId;
  }
  return options[0]?.optionId ?? null;
}

/**
 * Pulls streamed text out of a `session/update` notification. ACP's sessionUpdate
 * carries several variants (agent_message_chunk, agent_thought_chunk, tool_call, ...)
 * — only agent_message_chunk text blocks contribute to the final answer; everything
 * else (tool calls, thinking) is narration we don't need for a one-shot RunnerJob.
 *
 * Best-effort against the publicly documented ACP notification shape — verify against
 * the installed kiro-cli version if this ever comes back empty for a run that clearly
 * produced output.
 */
export function extractUpdateText(params: unknown): string | null {
  const update = updateOf(params);
  if (!update) {
    return null;
  }
  const { sessionUpdate, content } = update;
  if ('agent_message_chunk' !== sessionUpdate || !content || 'object' !== typeof content) {
    return null;
  }
  const { type, text } = content as { type?: unknown; text?: unknown };
  return 'text' === type && 'string' === typeof text ? text : null;
}

function updateOf(params: unknown): Record<string, unknown> | null {
  if (!params || 'object' !== typeof params) {
    return null;
  }
  const update = (params as { update?: unknown }).update;
  return update && 'object' === typeof update ? (update as Record<string, unknown>) : null;
}

export interface AcpUsageUpdate {
  used: number;
  size: number;
  cost: { amount: number; currency: string } | null;
}

/**
 * Pulls ACP's `usage_update` out of a `session/update` notification: the agent's
 * current context occupancy (`used` of `size` tokens) and, when it knows, the
 * cumulative session cost. This is the only usage signal ACP standardises — quotas
 * and rate limits are explicitly out of its scope — so it is all kiro can report.
 */
export function extractUsageUpdate(params: unknown): AcpUsageUpdate | null {
  const update = updateOf(params);
  if (!update || 'usage_update' !== update.sessionUpdate) {
    return null;
  }
  const used = finiteNumber(update.used);
  const size = finiteNumber(update.size);
  if (null === used || null === size) {
    return null;
  }

  const rawCost = update.cost && 'object' === typeof update.cost ? (update.cost as { amount?: unknown; currency?: unknown }) : null;
  const amount = finiteNumber(rawCost?.amount);
  const currency = 'string' === typeof rawCost?.currency && '' !== rawCost.currency ? rawCost.currency : null;

  return { used, size, cost: null !== amount && null !== currency ? { amount, currency } : null };
}

/**
 * Per-turn token counts from a `session/prompt` response's `usage`, where the agent
 * fills it in (codex-acp, for one, leaves it empty — zed-industries/codex-acp#165).
 */
export function extractPromptTokens(result: unknown): { input: number; output: number } | null {
  if (!result || 'object' !== typeof result) {
    return null;
  }
  const usage = (result as { usage?: unknown }).usage;
  if (!usage || 'object' !== typeof usage) {
    return null;
  }
  const { inputTokens, outputTokens } = usage as { inputTokens?: unknown; outputTokens?: unknown };
  const input = finiteNumber(inputTokens);
  const output = finiteNumber(outputTokens);
  return null !== input && null !== output ? { input, output } : null;
}

interface PendingRequest {
  resolve: (value: unknown) => void;
  reject: (error: Error) => void;
}

type AcpChildProcess = ChildProcessByStdio<Writable, Readable, null>;
export type SpawnFn = (executablePath: string, args: string[], options: Record<string, unknown>) => AcpChildProcess;

interface IncomingMessage {
  id?: unknown;
  result?: unknown;
  error?: { code: number; message: string };
  method?: unknown;
  params?: unknown;
}

/**
 * Minimal ACP JSON-RPC 2.0 client over a child process's stdio: newline-delimited
 * JSON in both directions. Three kinds of incoming message: a response to one of our
 * requests (`id` + `result`/`error`), a peer-initiated request we must answer (`id` +
 * `method` — kiro-cli asking something of us, e.g. session/request_permission), or a
 * notification (`method`, no `id`). Mirrors the shape of multica's hermesClient
 * (server/pkg/agent/hermes.go), scoped down to what a single request/session/prompt
 * turn needs.
 */
class AcpConnection {
  private nextId = 1;
  private readonly pending = new Map<number, PendingRequest>();
  private buffer = '';

  constructor(
    private readonly child: AcpChildProcess,
    private readonly onNotification: (method: string, params: unknown) => void,
  ) {
    child.stdout.setEncoding('utf8');
    child.stdout.on('data', (chunk: string) => this.handleData(chunk));
    child.on('close', () => this.rejectAllPending(new AgentExecutionError('kiro-cli process exited')));
    child.on('error', err =>
      this.rejectAllPending(new AgentExecutionError(`kiro-cli process error: ${err.message}`)),
    );
  }

  request(method: string, params: unknown): Promise<unknown> {
    const id = this.nextId++;
    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.send({ jsonrpc: '2.0', id, method, params }, err => {
        if (err) {
          this.pending.delete(id);
          reject(new AgentExecutionError(`failed to write to kiro-cli: ${err.message}`));
        }
      });
    });
  }

  private send(message: unknown, cb?: (err: Error | null | undefined) => void): void {
    this.child.stdin.write(`${JSON.stringify(message)}\n`, cb);
  }

  private handleData(chunk: string): void {
    this.buffer += chunk;
    const lines = this.buffer.split('\n');
    this.buffer = lines.pop() ?? '';
    for (const line of lines) {
      this.handleLine(line);
    }
  }

  private handleLine(line: string): void {
    const trimmed = line.trim();
    if ('' === trimmed) {
      return;
    }

    let message: IncomingMessage;
    try {
      message = JSON.parse(trimmed) as IncomingMessage;
    } catch {
      return;
    }

    const hasId = 'number' === typeof message.id;
    const hasMethod = 'string' === typeof message.method;

    if (hasId && hasMethod) {
      this.handlePeerRequest(message.id as number, message.method as string, message.params);
      return;
    }

    if (hasId) {
      const pending = this.pending.get(message.id as number);
      if (!pending) {
        return;
      }
      this.pending.delete(message.id as number);
      if (message.error) {
        pending.reject(new AgentExecutionError(`kiro-cli error ${message.error.code}: ${message.error.message}`));
      } else {
        pending.resolve(message.result);
      }
      return;
    }

    if (hasMethod) {
      this.onNotification(message.method as string, message.params);
    }
  }

  /**
   * Answers a request kiro-cli sent to us. This runner runs headless with no human to
   * approve anything, so the only peer request it understands — session/request_permission
   * — is always auto-approved (see selectAutoApproveOptionId). Any other peer request
   * gets a JSON-RPC "method not found" error rather than being left unanswered, so
   * kiro-cli never blocks forever waiting for a reply we were never going to send.
   */
  private handlePeerRequest(id: number, method: string, params: unknown): void {
    if ('session/request_permission' === method) {
      const options = (params as { options?: PermissionOption[] } | undefined)?.options ?? [];
      const optionId = selectAutoApproveOptionId(options);
      const outcome = optionId ? { outcome: 'selected', optionId } : { outcome: 'cancelled' };
      this.send({ jsonrpc: '2.0', id, result: { outcome } });
      return;
    }

    this.send({ jsonrpc: '2.0', id, error: { code: -32601, message: `unhandled peer request: ${method}` } });
  }

  private rejectAllPending(error: Error): void {
    for (const pending of this.pending.values()) {
      pending.reject(error);
    }
    this.pending.clear();
  }
}

export class KiroBackend implements AgentBackend {
  constructor(
    private readonly executablePath: string = 'kiro-cli',
    private readonly env: NodeJS.ProcessEnv = process.env,
    private readonly spawnFn: SpawnFn = spawn as SpawnFn,
  ) {}

  async execute(prompt: string, opts: ExecOptions): Promise<AgentResult> {
    // `--trust-all-tools` is documented for kiro-cli's standalone --no-interactive
    // headless mode, not for `acp` — passing an unrecognized flag risks a hard
    // startup failure there. Auto-approval in ACP mode instead goes through the
    // protocol-native session/request_permission (see AcpConnection.handlePeerRequest).
    const child: AcpChildProcess = this.spawnFn(this.executablePath, ['acp'], {
      cwd: opts.cwd,
      env: agentEnv(this.env),
      stdio: ['pipe', 'pipe', 'inherit'],
    });

    const chunks: string[] = [];
    let lastUsageUpdate: AcpUsageUpdate | null = null;
    let promptTokens: { input: number; output: number } | null = null;
    const connection = new AcpConnection(child, (method, params) => {
      if ('session/update' === method) {
        const text = extractUpdateText(params);
        if (text) {
          chunks.push(text);
        }
        lastUsageUpdate = extractUsageUpdate(params) ?? lastUsageUpdate;
      }
    });
    const usage = (): AgentUsage | null => {
      const update: AcpUsageUpdate | null = lastUsageUpdate;
      if (null === update && null === promptTokens) {
        return null;
      }
      return {
        observedAt: new Date().toISOString(),
        rateLimits: null,
        context: update ? { used: update.used, size: update.size } : null,
        tokens: promptTokens,
        cost: update?.cost ?? null,
      };
    };

    try {
      await connection.request('initialize', {
        protocolVersion: 1,
        clientInfo: { name: 'refleet-runner', version: '0.1.0' },
        clientCapabilities: {},
      });

      const sessionResult = await connection.request('session/new', {
        cwd: opts.cwd,
        mcpServers: [],
      });
      const sessionId = extractSessionId(sessionResult);
      if (null === sessionId) {
        throw new AgentExecutionError('kiro-cli session/new returned no session ID');
      }

      if (opts.model) {
        await connection.request('session/set_model', { sessionId, modelId: opts.model });
      }

      const promptResult = await connection.request('session/prompt', {
        sessionId,
        prompt: [{ type: 'text', text: prompt }],
      });
      promptTokens = extractPromptTokens(promptResult);
    } catch (err) {
      if (err instanceof AgentExecutionError) {
        err.usage = usage();
      }
      throw err;
    } finally {
      child.stdin.end();
      child.kill();
    }

    const output = chunks.join('').trim();
    if ('' === output) {
      throw new AgentExecutionError('kiro-cli produced no output', usage());
    }

    return { output, usage: usage() };
  }
}
