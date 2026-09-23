import { execFile } from 'node:child_process';

export interface GitResult {
  stdout: string;
  stderr: string;
}

export class GitError extends Error {
  constructor(
    readonly args: string[],
    readonly exitCode: number | null,
    readonly stderr: string,
  ) {
    super(`git ${args.join(' ')} exited with ${String(exitCode)}: ${stderr.trim()}`);
  }
}

export type GitEnv = Record<string, string>;

export type GitRunner = (args: string[], cwd?: string, env?: GitEnv) => Promise<GitResult>;

/**
 * GitLab's git-http backend (unlike its REST API) does not accept a bearer/private token
 * header — it only honors HTTP Basic auth, so the token is sent as a Basic credential
 * (the `oauth2` username works for both OAuth and personal/group access tokens).
 * Handed to git through GIT_CONFIG_* environment variables rather than `-c` on the
 * command line, so it is never written into the cached checkout's git config, never
 * visible in `ps`, and never part of the argv a GitError echoes back into logs and job
 * failure reports.
 */
export function gitAuthEnv(accessToken: string): GitEnv {
  const basic = Buffer.from(`oauth2:${accessToken}`).toString('base64');
  return {
    GIT_CONFIG_COUNT: '1',
    GIT_CONFIG_KEY_0: 'http.extraHeader',
    GIT_CONFIG_VALUE_0: `Authorization: Basic ${basic}`,
  };
}

export function createGitRunner(execFileFn: typeof execFile = execFile): GitRunner {
  return (args, cwd, env = {}) =>
    new Promise((resolve, reject) => {
      execFileFn(
        'git',
        args,
        { cwd, maxBuffer: 64 * 1024 * 1024, env: { ...process.env, GIT_TERMINAL_PROMPT: '0', ...env } },
        (error, stdout, stderr) => {
          if (error) {
            const code = 'code' in error && 'number' === typeof error.code ? error.code : null;
            reject(new GitError(args, code, String(stderr)));
            return;
          }
          resolve({ stdout: String(stdout), stderr: String(stderr) });
        },
      );
    });
}
