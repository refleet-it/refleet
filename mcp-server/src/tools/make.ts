import type { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { DEFAULT_TIMEOUT_MS, MAKE_TARGETS, REPO_ROOT } from '../config.js';
import { run } from '../exec.js';
import { toToolResult } from '../format.js';

export function registerMakeTools(server: McpServer): void {
  server.registerTool(
    'make_run',
    {
      title: 'make <target>',
      description:
        'Run one whitelisted `make <target>` from the refleet repo root ' +
        `(${MAKE_TARGETS.join(', ')}). Destructive targets that drop database schemas or wipe docker ` +
        'volumes (clean, init, db-recreate) are intentionally not exposed here - run those manually.',
      inputSchema: {
        target: z.enum(MAKE_TARGETS).describe('The Makefile target to run'),
        timeoutSeconds: z
          .number()
          .int()
          .min(10)
          .max(1800)
          .optional()
          .describe('Override the default timeout in seconds (default 900s; quality-check can take several minutes)'),
      },
      annotations: {
        title: 'Run make target',
        readOnlyHint: false,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: false,
      },
    },
    async ({ target, timeoutSeconds }) => {
      const result = await run('make', [target], {
        cwd: REPO_ROOT,
        timeoutMs: timeoutSeconds ? timeoutSeconds * 1000 : DEFAULT_TIMEOUT_MS,
      });
      return toToolResult(result);
    }
  );
}
