import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { extractSessionId, extractUpdateText, extractUsageUpdate, KiroBackend, selectAutoApproveOptionId, type SpawnFn } from './kiro.js';

test('selectAutoApproveOptionId prefers allow_always over allow_once', () => {
  const options = [
    { optionId: 'once', kind: 'allow_once' },
    { optionId: 'always', kind: 'allow_always' },
    { optionId: 'reject', kind: 'reject_always' },
  ];
  assert.equal(selectAutoApproveOptionId(options), 'always');
});

test('selectAutoApproveOptionId falls back to allow_once when allow_always is absent', () => {
  const options = [
    { optionId: 'reject', kind: 'reject_once' },
    { optionId: 'once', kind: 'allow_once' },
  ];
  assert.equal(selectAutoApproveOptionId(options), 'once');
});

test('selectAutoApproveOptionId falls back to the first option when no allow kind is present', () => {
  assert.equal(selectAutoApproveOptionId([{ optionId: 'only', kind: 'reject_once' }]), 'only');
});

test('selectAutoApproveOptionId returns null for an empty option list', () => {
  assert.equal(selectAutoApproveOptionId([]), null);
});

test('extractSessionId reads a valid sessionId', () => {
  assert.equal(extractSessionId({ sessionId: 'sess-1' }), 'sess-1');
});

test('extractSessionId returns null for missing/malformed input', () => {
  assert.equal(extractSessionId(null), null);
  assert.equal(extractSessionId({}), null);
  assert.equal(extractSessionId({ sessionId: 42 }), null);
});

test('extractUpdateText reads an agent_message_chunk text block', () => {
  const params = {
    update: { sessionUpdate: 'agent_message_chunk', content: { type: 'text', text: 'Hello.' } },
  };
  assert.equal(extractUpdateText(params), 'Hello.');
});

test('extractUpdateText ignores non-text-chunk update kinds', () => {
  assert.equal(extractUpdateText({ update: { sessionUpdate: 'tool_call', content: {} } }), null);
  assert.equal(extractUpdateText(null), null);
  assert.equal(extractUpdateText({ update: { sessionUpdate: 'agent_message_chunk', content: { type: 'image' } } }), null);
});

interface FakeChild extends EventEmitter {
  stdout: EventEmitter;
  stdin: { write: (data: string, cb?: (err?: Error) => void) => boolean; end: () => void };
  kill: () => void;
}

function createFakeKiroChild(
  respond: (message: { id: number; method: string; params: unknown }, stdout: EventEmitter) => void,
): { spawnFn: SpawnFn; child: FakeChild } {
  const stdout = new EventEmitter() as EventEmitter & { setEncoding: () => void };
  stdout.setEncoding = () => {};
  const child = new EventEmitter() as FakeChild;
  child.stdout = stdout;
  child.kill = () => {};
  child.stdin = {
    write: (data: string, cb?: (err?: Error) => void) => {
      const message = JSON.parse(data) as { id: number; method: string; params: unknown };
      queueMicrotask(() => respond(message, stdout));
      cb?.();
      return true;
    },
    end: () => {},
  };

  return { spawnFn: (() => child) as unknown as SpawnFn, child };
}

test('KiroBackend.execute drives the ACP handshake and returns streamed text', async () => {
  const { spawnFn } = createFakeKiroChild((message, stdout) => {
    if ('initialize' === message.method) {
      stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: {} })}\n`);
    } else if ('session/new' === message.method) {
      stdout.emit(
        'data',
        `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: { sessionId: 'sess-1' } })}\n`,
      );
    } else if ('session/prompt' === message.method) {
      stdout.emit(
        'data',
        `${JSON.stringify({
          jsonrpc: '2.0',
          method: 'session/update',
          params: {
            sessionId: 'sess-1',
            update: { sessionUpdate: 'agent_message_chunk', content: { type: 'text', text: 'Hi there.' } },
          },
        })}\n`,
      );
      stdout.emit(
        'data',
        `${JSON.stringify({
          jsonrpc: '2.0',
          method: 'session/update',
          params: { sessionId: 'sess-1', update: { sessionUpdate: 'usage_update', used: 12000, size: 200000, cost: { amount: 0.3, currency: 'USD' } } },
        })}\n`,
      );
      stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: { stopReason: 'end_turn', usage: { inputTokens: 11000, outputTokens: 400 } } })}\n`);
    }
  });

  const backend = new KiroBackend('kiro-cli', {}, spawnFn);
  const result = await backend.execute('Do the thing', { cwd: '/repo', kind: 'change' });

  assert.equal(result.output, 'Hi there.');
  assert.equal(result.usage?.rateLimits, null);
  assert.deepEqual(result.usage?.context, { used: 12000, size: 200000 });
  assert.deepEqual(result.usage?.tokens, { input: 11000, output: 400 });
  assert.deepEqual(result.usage?.cost, { amount: 0.3, currency: 'USD' });
});

test('extractUsageUpdate reads ACP usage_update and ignores every other session update', () => {
  assert.deepEqual(extractUsageUpdate({ update: { sessionUpdate: 'usage_update', used: 5, size: 10 } }), { used: 5, size: 10, cost: null });
  assert.equal(extractUsageUpdate({ update: { sessionUpdate: 'usage_update', used: '5', size: 10 } }), null);
  assert.equal(extractUsageUpdate({ update: { sessionUpdate: 'agent_message_chunk', content: { type: 'text', text: 'x' } } }), null);
});

test('KiroBackend.execute fails when session/new returns no session id', async () => {
  const { spawnFn } = createFakeKiroChild((message, stdout) => {
    if ('initialize' === message.method) {
      stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: {} })}\n`);
    } else if ('session/new' === message.method) {
      stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: {} })}\n`);
    }
  });

  const backend = new KiroBackend('kiro-cli', {}, spawnFn);

  await assert.rejects(
    backend.execute('Do the thing', { cwd: '/repo', kind: 'change' }),
    /session\/new returned no session ID/,
  );
});

test('KiroBackend.execute surfaces a JSON-RPC error response', async () => {
  const { spawnFn } = createFakeKiroChild((message, stdout) => {
    stdout.emit(
      'data',
      `${JSON.stringify({ jsonrpc: '2.0', id: message.id, error: { code: -32601, message: 'method not found' } })}\n`,
    );
  });

  const backend = new KiroBackend('kiro-cli', {}, spawnFn);

  await assert.rejects(backend.execute('Do the thing', { cwd: '/repo', kind: 'change' }), /method not found/);
});

test('KiroBackend.execute auto-approves a peer-initiated session/request_permission', async () => {
  const written: { id: number; method?: string; result?: unknown }[] = [];
  const stdout = new EventEmitter() as EventEmitter & { setEncoding: () => void };
  stdout.setEncoding = () => {};
  const child = new EventEmitter() as FakeChild;
  child.stdout = stdout;
  child.kill = () => {};
  child.stdin = {
    write: (data: string, cb?: (err?: Error) => void) => {
      const message = JSON.parse(data) as { id: number; method?: string; result?: unknown };
      written.push(message);
      cb?.();

      queueMicrotask(() => {
        if ('initialize' === message.method) {
          stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: {} })}\n`);
        } else if ('session/new' === message.method) {
          stdout.emit(
            'data',
            `${JSON.stringify({ jsonrpc: '2.0', id: message.id, result: { sessionId: 'sess-1' } })}\n`,
          );
        } else if ('session/prompt' === message.method) {
          // kiro-cli pauses the turn to ask permission for a tool call before it can finish.
          stdout.emit(
            'data',
            `${JSON.stringify({
              jsonrpc: '2.0',
              id: 999,
              method: 'session/request_permission',
              params: {
                sessionId: 'sess-1',
                toolCall: { toolCallId: 'call-1' },
                options: [
                  { optionId: 'reject-once', kind: 'reject_once' },
                  { optionId: 'allow-always', kind: 'allow_always' },
                ],
              },
            })}\n`,
          );
        }
      });

      return true;
    },
    end: () => {},
  };

  const spawnFn = (() => child) as unknown as SpawnFn;
  const backend = new KiroBackend('kiro-cli', {}, spawnFn);

  const resultPromise = backend.execute('Do the thing', { cwd: '/repo', kind: 'change' });

  // Let the queued microtasks (session/prompt's write -> stdout "request_permission" ->
  // handlePeerRequest -> our reply write) actually run before inspecting `written`.
  await new Promise(resolve => setTimeout(resolve, 10));

  const permissionReply = written.find(m => 999 === m.id);
  assert.deepEqual(permissionReply?.result, { outcome: { outcome: 'selected', optionId: 'allow-always' } });

  stdout.emit('data', `${JSON.stringify({ jsonrpc: '2.0', id: 3, result: {} })}\n`);
  await assert.rejects(resultPromise, /kiro-cli produced no output/);
});
