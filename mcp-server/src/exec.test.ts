import { test } from 'node:test';
import assert from 'node:assert/strict';
import { run } from './exec.js';

/**
 * `run` is the one function every tool in this server funnels through, and its whole safety
 * argument rests on never touching a shell. These cases hold that claim rather than assume it, and
 * pin the two other behaviours a caller relies on: output gets capped, and a hung process gets
 * killed rather than left running.
 */
test('shell metacharacters in an argument reach the child literally, never expanded', async () => {
  const dangerous = '$(touch /tmp/refleet-mcp-injection-proof); `id`; echo pwned';

  const res = await run('node', ['-e', 'process.stdout.write(process.argv[1])', dangerous], {
    cwd: process.cwd(),
    timeoutMs: 5_000,
  });

  assert.equal(res.exitCode, 0);
  assert.equal(res.stdout, dangerous);
});

test('a semicolon does not chain a second command', async () => {
  const res = await run('node', ['-e', 'process.stdout.write("one")', ';', 'node', '-e', 'process.stdout.write("two")'], {
    cwd: process.cwd(),
    timeoutMs: 5_000,
  });

  // Everything after the first script is just more argv to that same node invocation, which
  // ignores extra args. A shell splitting on `;` would run a second `node -e ...` and "two" would
  // show up somewhere in the output; it never does.
  assert.equal(res.stdout, 'one');
  assert.equal(res.exitCode, 0);
});

test('caps stdout instead of buffering an unbounded amount', async () => {
  const res = await run('node', ['-e', 'process.stdout.write("x".repeat(50_000))'], {
    cwd: process.cwd(),
    timeoutMs: 5_000,
  });

  assert.ok(res.stdout.length < 50_000);
  assert.match(res.stdout, /truncated \d+ chars/);
});

test('kills a process that outruns its timeout', async () => {
  const res = await run('node', ['-e', 'setTimeout(() => {}, 10_000)'], {
    cwd: process.cwd(),
    timeoutMs: 200,
  });

  assert.equal(res.timedOut, true);
  assert.notEqual(res.exitCode, 0);
});

test('reports the real exit code of a failing command', async () => {
  const res = await run('node', ['-e', 'process.exit(7)'], {
    cwd: process.cwd(),
    timeoutMs: 5_000,
  });

  assert.equal(res.exitCode, 7);
  assert.equal(res.timedOut, false);
});
