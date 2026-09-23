export type Logger = (message: string) => void;

/** `[runner] <name>: <message>` — the line format the Docker/journal logs have always had. */
export function createLogger(runnerName: string, write: (line: string) => void = line => process.stdout.write(line)): Logger {
  return message => write(`[runner] ${runnerName}: ${message}\n`);
}
