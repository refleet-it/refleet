import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { DEFAULT_TIMEOUT_MS, EXEC_SERVICES, MEDIUM_TIMEOUT_MS, REPO_ROOT, SERVICES, SHORT_TIMEOUT_MS } from '../config.js';
import type { ExecService } from '../config.js';
import { run } from '../exec.js';
import { toToolResult } from '../format.js';

/**
 * Fixed catalog of exec commands. This intentionally trades flexibility for
 * safety: rather than accepting an arbitrary binary/argv from the caller, each
 * entry is a hardcoded argv template. Nothing here touches doctrine migrations,
 * schema, or the database service - that stays out of this server's scope.
 */
const EXEC_COMMANDS = {
  'composer-install': { service: 'backend', argv: ['composer', 'install'] },
  'composer-validate': { service: 'backend', argv: ['composer', 'validate', '--strict'] },
  'composer-outdated': { service: 'backend', argv: ['composer', 'outdated', '--direct'] },
  'cache-clear': { service: 'backend', argv: ['php', 'bin/console', 'cache:clear'] },
  'cache-warmup-test': { service: 'backend', argv: ['php', 'bin/console', 'cache:warmup', '--env=test'] },
  'debug-router': { service: 'backend', argv: ['php', 'bin/console', 'debug:router'] },
  'php-version': { service: 'backend', argv: ['php', '-v'] },
  'npm-ci': { service: 'frontend', argv: ['npm', 'ci'] },
  'npm-outdated': { service: 'frontend', argv: ['npm', 'outdated'] },
  'ng-version': { service: 'frontend', argv: ['npx', 'ng', 'version'] },
} as const satisfies Record<string, { service: ExecService; argv: readonly string[] }>;

type ExecCommandName = keyof typeof EXEC_COMMANDS;
const EXEC_COMMAND_NAMES = Object.keys(EXEC_COMMANDS) as [ExecCommandName, ...ExecCommandName[]];

export function registerDockerTools(server: McpServer): void {
  server.registerTool(
    'docker_compose_status',
    {
      title: 'docker compose ps',
      description: "Shows the status of refleet's docker compose services.",
      inputSchema: {
        all: z.boolean().optional().describe('Include stopped containers (-a)'),
      },
      annotations: { title: 'Container status', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ all }) => {
      const args = ['compose', 'ps', ...(all ? ['-a'] : [])];
      const result = await run('docker', args, { cwd: REPO_ROOT, timeoutMs: SHORT_TIMEOUT_MS });
      return toToolResult(result);
    }
  );

  server.registerTool(
    'docker_compose_logs',
    {
      title: 'docker compose logs',
      description: 'Fetches the last N log lines for one refleet service. A single snapshot, never follows (-f).',
      inputSchema: {
        service: z.enum(SERVICES).describe('Which service to read logs from'),
        tail: z.number().int().min(1).max(2000).optional().describe('Number of lines from the end (default 200)'),
      },
      annotations: { title: 'Tail service logs', readOnlyHint: true, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ service, tail }) => {
      const args = ['compose', 'logs', '--no-color', '--tail', String(tail ?? 200), service];
      const result = await run('docker', args, { cwd: REPO_ROOT, timeoutMs: SHORT_TIMEOUT_MS });
      return toToolResult(result);
    }
  );

  server.registerTool(
    'docker_compose_up',
    {
      title: 'docker compose up -d',
      description:
        'Starts docker compose services in the background (--remove-orphans). Omit `services` to start all of them.',
      inputSchema: {
        services: z.array(z.enum(SERVICES)).optional().describe('Specific services to start; omit for all'),
        build: z.boolean().optional().describe('Rebuild images first (--build)'),
      },
      annotations: { title: 'Start services', readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ services, build }) => {
      const args = ['compose', 'up', '-d', '--remove-orphans', ...(build ? ['--build'] : []), ...(services ?? [])];
      const result = await run('docker', args, { cwd: REPO_ROOT, timeoutMs: DEFAULT_TIMEOUT_MS });
      return toToolResult(result);
    }
  );

  server.registerTool(
    'docker_compose_down',
    {
      title: 'docker compose down',
      description:
        'Stops and removes refleet containers/networks (--remove-orphans). Never passes --volumes, so the ' +
        'Postgres data volume is always preserved - this cannot wipe the database.',
      inputSchema: {},
      annotations: { title: 'Stop all services', readOnlyHint: false, destructiveHint: true, idempotentHint: true, openWorldHint: false },
    },
    async () => {
      const result = await run('docker', ['compose', 'down', '--remove-orphans'], { cwd: REPO_ROOT, timeoutMs: MEDIUM_TIMEOUT_MS });
      return toToolResult(result);
    }
  );

  server.registerTool(
    'docker_compose_restart',
    {
      title: 'docker compose restart',
      description: 'Restarts one or more refleet services in place (e.g. to pick up a config change).',
      inputSchema: {
        services: z.array(z.enum(SERVICES)).min(1).describe('Services to restart'),
      },
      annotations: { title: 'Restart services', readOnlyHint: false, destructiveHint: false, idempotentHint: true, openWorldHint: false },
    },
    async ({ services }) => {
      const result = await run('docker', ['compose', 'restart', ...services], { cwd: REPO_ROOT, timeoutMs: MEDIUM_TIMEOUT_MS });
      return toToolResult(result);
    }
  );

  server.registerTool(
    'docker_compose_exec',
    {
      title: 'Run a whitelisted in-container command',
      description:
        'Runs one command from a fixed catalog inside the backend or frontend container - never database or mail, ' +
        `and nothing that touches doctrine migrations/schema. Available commands: ${EXEC_COMMAND_NAMES.join(', ')}.`,
      inputSchema: {
        command: z.enum(EXEC_COMMAND_NAMES).describe('Which whitelisted command to run'),
      },
      annotations: { title: 'Exec whitelisted command', readOnlyHint: false, destructiveHint: false, idempotentHint: false, openWorldHint: false },
    },
    async ({ command }) => {
      const def = EXEC_COMMANDS[command];
      if (!EXEC_SERVICES.includes(def.service)) {
        // Defense in depth: this should be unreachable given EXEC_COMMANDS' type, but never exec into
        // a non-whitelisted service even if that invariant is ever broken by a future edit.
        throw new Error(`internal: service "${def.service}" is not in EXEC_SERVICES`);
      }

      const args = ['compose', 'exec', '-T', def.service, ...def.argv];
      const result = await run('docker', args, { cwd: REPO_ROOT, timeoutMs: DEFAULT_TIMEOUT_MS });
      return toToolResult(result);
    }
  );
}
