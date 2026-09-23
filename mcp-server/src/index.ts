#!/usr/bin/env node
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { registerDockerTools } from './tools/docker.js';
import { registerMakeTools } from './tools/make.js';

const server = new McpServer({
  name: 'refleet-devtools',
  version: '0.1.0',
});

registerMakeTools(server);
registerDockerTools(server);

async function main(): Promise<void> {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  // MCP uses stdout for JSON-RPC framing; all diagnostic output must go to stderr.
  console.error('refleet-devtools MCP server running on stdio');
}

main().catch((err: unknown) => {
  console.error('Fatal error starting refleet-devtools MCP server:', err);
  process.exit(1);
});
