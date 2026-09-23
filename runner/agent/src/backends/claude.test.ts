import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { buildClaudeArgs, extractFinalText, extractUsage, ClaudeBackend, type SpawnFn } from './claude.js';
import { agentEnv, AgentExecutionError } from './types.js';

test('buildClaudeArgs defaults to dangerously-skip-permissions and read-only qualification tools', () => {
  const args = buildClaudeArgs('qualification', undefined, {});

  assert.ok(args.includes('--dangerously-skip-permissions'));
  const toolsIndex = args.indexOf('--allowedTools');
  assert.ok(toolsIndex >= 0);
  assert.deepEqual(args.slice(toolsIndex + 1, toolsIndex + 4), ['Read', 'Grep', 'Glob']);
  assert.ok(args.includes('--output-format'));
});

test('buildClaudeArgs lets CLAUDE_SKIP_PERMISSIONS=false opt out', () => {
  const args = buildClaudeArgs('change', undefined, { CLAUDE_SKIP_PERMISSIONS: 'false' });
  assert.ok(!args.includes('--dangerously-skip-permissions'));
});

test('buildClaudeArgs uses CLAUDE_QUALIFICATION_MODEL over CLAUDE_MODEL for qualification jobs', () => {
  const args = buildClaudeArgs('qualification', undefined, {
    CLAUDE_MODEL: 'claude-opus-5',
    CLAUDE_QUALIFICATION_MODEL: 'claude-haiku-4-5-20251001',
  });
  const modelIndex = args.indexOf('--model');
  assert.equal(args[modelIndex + 1], 'claude-haiku-4-5-20251001');
});

test('buildClaudeArgs uses CLAUDE_MODEL for change jobs, ignoring the qualification override', () => {
  const args = buildClaudeArgs('change', undefined, {
    CLAUDE_MODEL: 'claude-opus-5',
    CLAUDE_QUALIFICATION_MODEL: 'claude-haiku-4-5-20251001',
  });
  const modelIndex = args.indexOf('--model');
  assert.equal(args[modelIndex + 1], 'claude-opus-5');
});

test('buildClaudeArgs lets a per-job model override win over every env default', () => {
  const args = buildClaudeArgs('change', 'claude-sonnet-5', { CLAUDE_MODEL: 'claude-opus-5' });
  const modelIndex = args.indexOf('--model');
  assert.equal(args[modelIndex + 1], 'claude-sonnet-5');
  assert.equal(args.indexOf('--model', modelIndex + 1), -1, 'must not emit --model twice');
});

test('extractFinalText prefers the closing result event over streamed assistant text', () => {
  const lines = [
    JSON.stringify({ type: 'assistant', message: { role: 'assistant', content: [{ type: 'text', text: 'draft…' }] } }),
    JSON.stringify({ type: 'result', subtype: 'success', result: 'Final answer.', is_error: false }),
  ];

  assert.deepEqual(extractFinalText(lines), { output: 'Final answer.', isError: false });
});

test('extractFinalText falls back to concatenated assistant text when no result event arrives', () => {
  const lines = [
    JSON.stringify({ type: 'assistant', message: { role: 'assistant', content: [{ type: 'text', text: 'Hello ' }] } }),
    JSON.stringify({ type: 'assistant', message: { role: 'assistant', content: [{ type: 'text', text: 'world.' }] } }),
  ];

  assert.deepEqual(extractFinalText(lines), { output: 'Hello world.', isError: false });
});

test('extractFinalText surfaces is_error and skips unparseable lines', () => {
  const lines = ['not json', JSON.stringify({ type: 'result', result: 'boom', is_error: true })];

  assert.deepEqual(extractFinalText(lines), { output: 'boom', isError: true });
});

test('extractUsage folds rate-limit events per window, last report winning, and reads totals off the result event', () => {
  const lines = [
    JSON.stringify({ type: 'rate_limit_event', rate_limit_info: { status: 'allowed', resetsAt: 1784283600, rateLimitType: 'five_hour', utilization: 0.12 } }),
    JSON.stringify({
      type: 'assistant',
      message: { role: 'assistant', content: [{ type: 'text', text: 'draft' }], usage: { input_tokens: 10, cache_read_input_tokens: 900, output_tokens: 5 } },
    }),
    JSON.stringify({ type: 'rate_limit_event', rate_limit_info: { status: 'allowed_warning', resetsAt: 1784283600, rateLimitType: 'five_hour', utilization: 81 } }),
    JSON.stringify({ type: 'rate_limit_event', rate_limit_info: { status: 'allowed', resetsAt: 1784800000, rateLimitType: 'seven_day' } }),
    JSON.stringify({
      type: 'assistant',
      message: { role: 'assistant', content: [{ type: 'text', text: 'final' }], usage: { input_tokens: 20, cache_creation_input_tokens: 100, cache_read_input_tokens: 1000, output_tokens: 7 } },
    }),
    JSON.stringify({
      type: 'result',
      result: 'Done.',
      is_error: false,
      total_cost_usd: 0.0421,
      usage: { input_tokens: 30, cache_creation_input_tokens: 100, cache_read_input_tokens: 1900, output_tokens: 12 },
      modelUsage: { 'claude-sonnet-5': { inputTokens: 30, outputTokens: 12, contextWindow: 200000 } },
    }),
  ];

  assert.deepEqual(extractUsage(lines, new Date('2026-09-21T10:00:00Z')), {
    observedAt: '2026-09-21T10:00:00.000Z',
    rateLimits: [
      { window: 'five_hour', status: 'allowed_warning', utilization: 0.81, resetsAt: '2026-07-17T10:20:00.000Z' },
      { window: 'seven_day', status: 'allowed', utilization: null, resetsAt: '2026-07-23T09:46:40.000Z' },
    ],
    context: { used: 1120, size: 200000 },
    tokens: { input: 2030, output: 12 },
    cost: { amount: 0.0421, currency: 'USD' },
  });
});

test('extractUsage reads every window at once from a unifiedWindows rate-limit event', () => {
  const lines = [
    JSON.stringify({
      type: 'rate_limit_event',
      rate_limit_info: {
        status: 'allowed',
        rateLimitType: 'five_hour',
        unifiedWindows: { five_hour: { utilization: 0.4, resetsAt: 1784283600 }, seven_day: { utilization: 0.9, resetsAt: 1784800000, status: 'allowed_warning' } },
      },
    }),
  ];

  assert.deepEqual(extractUsage(lines)?.rateLimits, [
    { window: 'five_hour', status: 'allowed', utilization: 0.4, resetsAt: '2026-07-17T10:20:00.000Z' },
    { window: 'seven_day', status: 'allowed_warning', utilization: 0.9, resetsAt: '2026-07-23T09:46:40.000Z' },
  ]);
});

test('extractUsage is null when the stream carried nothing about consumption', () => {
  assert.equal(extractUsage(['not json', JSON.stringify({ type: 'result', result: 'ok' })]), null);
});

function createFakeClaudeChild(): {
  spawnFn: SpawnFn;
  emitLine: (line: string) => void;
  close: (code: number) => void;
} {
  const stdout = new EventEmitter() as unknown as NodeJS.ReadableStream & EventEmitter & { setEncoding: () => void };
  (stdout as unknown as { setEncoding: () => void }).setEncoding = () => {};
  const child = new EventEmitter() as unknown as EventEmitter & { stdout: typeof stdout };
  child.stdout = stdout;

  return {
    spawnFn: (() => child) as unknown as SpawnFn,
    emitLine: (line: string) => stdout.emit('data', `${line}\n`),
    close: (code: number) => child.emit('close', code),
  };
}

test('ClaudeBackend.execute returns the result event text from a successful run', async () => {
  const { spawnFn, emitLine, close } = createFakeClaudeChild();
  const backend = new ClaudeBackend('claude', {}, spawnFn);

  const resultPromise = backend.execute('hello', { cwd: '/repo', kind: 'change' });
  emitLine(JSON.stringify({ type: 'result', result: 'All good.', is_error: false, total_cost_usd: 0.5, usage: { input_tokens: 3, output_tokens: 4 } }));
  close(0);

  const result = await resultPromise;
  assert.equal(result.output, 'All good.');
  assert.deepEqual(result.usage?.tokens, { input: 3, output: 4 });
  assert.deepEqual(result.usage?.cost, { amount: 0.5, currency: 'USD' });
});

test('ClaudeBackend.execute rejects when the process exits non-zero, keeping the usage it saw', async () => {
  const { spawnFn, emitLine, close } = createFakeClaudeChild();
  const backend = new ClaudeBackend('claude', {}, spawnFn);

  const resultPromise = backend.execute('hello', { cwd: '/repo', kind: 'change' });
  emitLine(JSON.stringify({ type: 'rate_limit_event', rate_limit_info: { status: 'rejected', resetsAt: 1784283600, rateLimitType: 'five_hour' } }));
  close(1);

  await assert.rejects(resultPromise, (err: unknown) => {
    assert.ok(err instanceof AgentExecutionError);
    assert.match(err.message, /claude exited with status 1/);
    assert.equal(err.usage?.rateLimits?.[0]?.status, 'rejected');
    return true;
  });
});

test('agentEnv strips the runner credentials but keeps everything else', () => {
  const env = agentEnv({ PATH: '/usr/bin', REFLEET_API_KEY: 'ib_secret', REFLEET_API_URL: 'https://refleet.it/api', ANTHROPIC_API_KEY: 'sk' });

  assert.deepEqual(env, { PATH: '/usr/bin', ANTHROPIC_API_KEY: 'sk' });
});
