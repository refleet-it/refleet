import type { ExecResult } from './exec.js';

/** Renders an ExecResult as a single MCP tool-call result, flagging non-zero/timeout as isError. */
export function toToolResult(result: ExecResult) {
  const status = result.timedOut
    ? 'TIMED OUT'
    : result.exitCode === 0
      ? 'OK'
      : `FAILED (exit ${result.exitCode ?? 'null'}${result.signal ? `, signal ${result.signal}` : ''})`;

  const text = [
    `$ ${result.command}`,
    `status: ${status} (${result.durationMs}ms)`,
    '',
    '--- stdout ---',
    result.stdout.trim() || '(empty)',
    '',
    '--- stderr ---',
    result.stderr.trim() || '(empty)',
  ].join('\n');

  return {
    content: [{ type: 'text' as const, text }],
    isError: result.timedOut || result.exitCode !== 0,
  };
}
