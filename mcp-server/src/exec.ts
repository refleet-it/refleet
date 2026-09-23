import { spawn } from 'node:child_process';

export interface ExecResult {
  readonly command: string;
  readonly exitCode: number | null;
  readonly signal: NodeJS.Signals | null;
  readonly stdout: string;
  readonly stderr: string;
  readonly timedOut: boolean;
  readonly durationMs: number;
}

const MAX_OUTPUT_CHARS = 20_000;

function truncate(output: string): string {
  if (output.length <= MAX_OUTPUT_CHARS) {
    return output;
  }

  const half = MAX_OUTPUT_CHARS / 2;
  const omitted = output.length - MAX_OUTPUT_CHARS;

  return `${output.slice(0, half)}\n\n… [truncated ${omitted} chars] …\n\n${output.slice(-half)}`;
}

/**
 * Runs `cmd` with `args` via spawn (never a shell), so shell metacharacters in
 * arguments are inert rather than a command-injection vector. Kills the child
 * (SIGTERM, then SIGKILL after a grace period) if it exceeds `timeoutMs`.
 */
export function run(cmd: string, args: readonly string[], opts: { cwd: string; timeoutMs: number }): Promise<ExecResult> {
  return new Promise(resolve => {
    const start = Date.now();
    const child = spawn(cmd, args, { cwd: opts.cwd, shell: false, stdio: ['ignore', 'pipe', 'pipe'] });

    let stdout = '';
    let stderr = '';
    let timedOut = false;
    let settled = false;

    const killTimer = setTimeout(() => {
      timedOut = true;
      child.kill('SIGTERM');
      setTimeout(() => child.kill('SIGKILL'), 5_000).unref();
    }, opts.timeoutMs);
    killTimer.unref();

    child.stdout?.on('data', (chunk: Buffer) => {
      stdout += chunk.toString('utf8');
    });
    child.stderr?.on('data', (chunk: Buffer) => {
      stderr += chunk.toString('utf8');
    });

    const finish = (exitCode: number | null, signal: NodeJS.Signals | null, errorSuffix?: string) => {
      if (settled) {
        return;
      }
      settled = true;
      clearTimeout(killTimer);
      resolve({
        command: `${cmd} ${args.join(' ')}`,
        exitCode,
        signal,
        stdout: truncate(stdout),
        stderr: truncate(errorSuffix ? `${stderr}\n${errorSuffix}` : stderr),
        timedOut,
        durationMs: Date.now() - start,
      });
    };

    child.on('close', (code, signal) => {
      finish(code, signal);
    });
    child.on('error', err => {
      finish(null, null, String(err));
    });
  });
}
