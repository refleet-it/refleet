import type { ExecOptions } from './backends/types.js';
import { resolveBackend } from './fleet/execute.js';

interface CliArgs {
  engine: 'claude' | 'kiro';
  workdir: string;
  kind: 'qualification' | 'change';
  model?: string;
}

/**
 * `node run.js --engine <claude|kiro> --workdir <dir> --kind <qualification|change>
 * [--model <id>]`, prompt on stdin, final agent text on stdout, non-zero exit on
 * failure. The fleet loop calls the backends in-process (see fleet/execute.ts); this
 * standalone form is kept for running a single prompt by hand against a checkout.
 */
export function parseArgs(argv: string[]): CliArgs {
  let engine: string | undefined;
  let workdir: string | undefined;
  let kind: string | undefined;
  let model: string | undefined;

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    switch (arg) {
      case '--engine':
        engine = argv[++i];
        break;
      case '--workdir':
        workdir = argv[++i];
        break;
      case '--kind':
        kind = argv[++i];
        break;
      case '--model':
        model = argv[++i];
        break;
      default:
        throw new Error(`unknown argument: ${String(arg)}`);
    }
  }

  if ('claude' !== engine && 'kiro' !== engine) {
    throw new Error(`--engine must be "claude" or "kiro", got: ${String(engine)}`);
  }
  if (!workdir) {
    throw new Error('--workdir is required');
  }
  if ('qualification' !== kind && 'change' !== kind) {
    throw new Error(`--kind must be "qualification" or "change", got: ${String(kind)}`);
  }

  return { engine, workdir, kind, model };
}

function readStdin(): Promise<string> {
  return new Promise((resolve, reject) => {
    let data = '';
    process.stdin.setEncoding('utf8');
    process.stdin.on('data', (chunk: string) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
    process.stdin.on('error', reject);
  });
}

async function main(): Promise<void> {
  const args = parseArgs(process.argv.slice(2));
  const prompt = (await readStdin()).trim();

  if ('' === prompt) {
    process.stderr.write('run: no prompt provided on stdin\n');
    process.exitCode = 1;
    return;
  }

  const backend = resolveBackend(args.engine);
  const opts: ExecOptions = { cwd: args.workdir, kind: args.kind, model: args.model };

  try {
    const result = await backend.execute(prompt, opts);
    process.stdout.write(result.output);
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    process.stderr.write(`run: ${args.engine} execution failed: ${message}\n`);
    process.exitCode = 1;
  }
}

function isMainModule(): boolean {
  return undefined !== process.argv[1] && import.meta.url === `file://${process.argv[1]}`;
}

if (isMainModule()) {
  void main();
}
