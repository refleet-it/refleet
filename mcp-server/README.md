# refleet-mcp-server

An [MCP](https://modelcontextprotocol.io) server that gives an MCP client (Claude Code, etc.) a
whitelisted, dev-workflow view of the refleet monorepo: running quality/test `make` targets and
controlling the local docker compose stack. It does not read or write application data.

## Design principles

- **Allow-list, not deny-list.** Every tool exposes a fixed, named set of operations (a `make`
  target enum, a docker compose service enum, a curated exec-command catalog). There is no
  "run arbitrary command" tool.
- **No shell.** Every process is spawned via `child_process.spawn` with an argv array
  (`shell: false`). Shell metacharacters in any input are inert, not a command-injection vector.
- **No database access.** `database` and `mail` are excluded from
  `docker_compose_exec`'s allowed services, and no whitelisted command touches
  doctrine migrations, schema, or fixtures. Reading/writing application data is out of scope for
  this server by design.
- **Nothing irreversibly destructive.** `make clean`, `make init`, and `make db-recreate` (which
  drop database schemas and/or wipe docker volumes) are not exposed. `docker_compose_down` never
  passes `--volumes`, so the Postgres data volume always survives it.
- **Bounded execution.** Every call has a timeout (killed with SIGTERM, then SIGKILL) and output is
  capped at 20k characters (head + tail kept, middle elided) so a runaway or noisy command can't
  hang or flood the calling agent's context.

## Tools

| Tool | What it does |
| --- | --- |
| `make_run` | Runs one whitelisted `make <target>`: `lint`, `quality-check`, `phpcsfixer`, `phpstan`, `rector`, `test`, `test-coverage`, `lighthouse`, `start`, `xdebug-on`, `xdebug-off`. |
| `docker_compose_status` | `docker compose ps` (optionally `-a` for stopped containers). |
| `docker_compose_logs` | Tails the last N lines (default 200, max 2000) of one service's logs. Never follows. |
| `docker_compose_up` | Starts services in the background (`--remove-orphans`, optional `--build`). Omit `services` for all. |
| `docker_compose_down` | Stops and removes containers/networks. Never passes `--volumes`. |
| `docker_compose_restart` | Restarts one or more services in place. |
| `docker_compose_exec` | Runs one command from a fixed catalog inside `backend` or `frontend` (composer install/validate/outdated, cache:clear, cache:warmup --env=test, debug:router, php -v, npm ci/outdated, ng version). |

Run any tool once for its full input schema and description - `registerTool`'s Zod schemas are the
source of truth, this table is a summary.

## Requirements

- Node.js >= 20 on the host running the MCP client (this server shells out to the host's `make`
  and `docker` binaries, not a container).
- Docker + the refleet `compose.yml`/`compose.override.yml` stack available at the repo root.

## Setup

```bash
cd mcp-server
npm install
npm run build
```

This project is registered in the repo root's `.mcp.json` under the `refleet-devtools` server,
so any MCP-aware client opened at the repo root picks it up automatically once it's built. To
rebuild after pulling changes to `mcp-server/src`, rerun `npm run build`.

## Development

```bash
npm run dev        # run directly from TypeScript via tsx, no build step
npm run typecheck   # tsc --noEmit
npm run build        # emit dist/
```

## Extending

To add a new tool, follow the existing pattern in `src/tools/`: define an explicit Zod schema for
its inputs (prefer `z.enum` over free-form strings wherever the set of valid values is known),
build the argv array yourself (never string-interpolate user input into a shell string), and run it
through `run()` in `src/exec.ts` so timeouts/output-capping/error-formatting stay consistent. If a
new tool could plausibly touch the database or another destructive path, default to *not* exposing
it here - keep that as a manual, human-run operation.

## Known audit findings

`npm audit` reports a moderate advisory in `@hono/node-server` (a transitive dependency of
`@modelcontextprotocol/sdk`'s optional HTTP transport, `serve-static` path traversal via encoded
backslash on Windows). This server only uses `StdioServerTransport`, never the HTTP transport, so
the vulnerable code path is not reachable here. Revisit the SDK version if that changes.
