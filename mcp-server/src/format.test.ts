import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toToolResult } from './format.js';
import type { ExecResult } from './exec.js';

/**
 * isError is the one bit the calling agent actually reacts to — everything else here is
 * formatting. Get it wrong and a failed `make quality-check` reads as success, or a real pass gets
 * reported as a failure and retried forever.
 */
function result(overrides: Partial<ExecResult>): ExecResult {
  return {
    command: 'echo hi',
    exitCode: 0,
    signal: null,
    stdout: '',
    stderr: '',
    timedOut: false,
    durationMs: 12,
    ...overrides,
  };
}

function text(out: ReturnType<typeof toToolResult>): string {
  const [first] = out.content;
  assert.ok(first, 'toToolResult must return at least one content block');

  return first.text;
}

test('flags a zero exit as success, not an error', () => {
  const out = toToolResult(result({ exitCode: 0 }));
  assert.equal(out.isError, false);
  assert.match(text(out), /status: OK/);
});

test('flags a non-zero exit as an error, with the exit code visible', () => {
  const out = toToolResult(result({ exitCode: 1 }));
  assert.equal(out.isError, true);
  assert.match(text(out), /status: FAILED \(exit 1\)/);
});

// A process killed by a signal reports exitCode: null, not 0 — treating null as falsy/success
// would misreport a crash as a clean pass.
test('flags a signal-killed process as an error even though exitCode is null', () => {
  const out = toToolResult(result({ exitCode: null, signal: 'SIGSEGV' }));
  assert.equal(out.isError, true);
  assert.match(text(out), /status: FAILED \(exit null, signal SIGSEGV\)/);
});

// A timeout still carries whatever exitCode the killed process happened to report (often 0 for a
// process torn down before it could set one) — the timedOut flag has to win regardless.
test('flags a timeout as an error even when the killed process reported a zero exit code', () => {
  const out = toToolResult(result({ exitCode: 0, timedOut: true }));
  assert.equal(out.isError, true);
  assert.match(text(out), /status: TIMED OUT/);
});

test('reports "(empty)" for blank stdout/stderr rather than an empty section', () => {
  const out = toToolResult(result({ stdout: '', stderr: '   \n  ' }));
  assert.match(text(out), /--- stdout ---\n\(empty\)/);
  assert.match(text(out), /--- stderr ---\n\(empty\)/);
});
