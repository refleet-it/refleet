# CLAUDE.md

Refleet — Symfony API + Angular SSR frontend + containerised code-modernisation
runners. Runners clone a customer repo, run an agent (Claude or Kiro) against it, and report
results back to the API.

## Layout

```
backend/     Symfony 7.4 / PHP 8.5 API, DDD bounded contexts
frontend/    Angular 21 (SSR + prerender), Tailwind 4, spartan-ng
runner/      agent (TypeScript); workspace/ is runtime-only
mcp-server/  MCP server (TypeScript) exposing whitelisted make/docker compose dev tooling
deploy/      self-hosting assets; compose.selfhost.yml lives at the root
```

## Commands

Everything runs through Docker Compose; the containers must be up first.

```bash
make init            # build, start, migrate, load fixtures — first run
make start           # just start the stack
make quality-check   # THE gate: all backend + frontend checks. Must pass before any commit.
make test            # PHPUnit only
make db-recreate     # drop schemas, re-migrate, reload fixtures
make exec            # shell into backend
make selfhost        # bring up a self-hosted instance (see docs/self-hosting.md)
```

Services: `caddy-gateway` (the one public entrypoint, routes `/api/*` to the right context —
see `infra/caddy/Caddyfile.gateway`), `backend-<context>` and `worker-<context>` per bounded
context (one container each, `APP_ID=<context>` — see docs/adr/0001-multiple-kernels.md),
`backend` (APP_ID=monolith, not served publicly — the workhorse `make` targets exec into for
anything inherently cross-context: the full test suite, migrations, fixtures), `database`,
`frontend`, `mail`, `runner`.

`make quality-check` runs a security section (secret scan, dependency audits, a Trivy scan,
`tofu fmt`/`validate`), then php-cs-fixer, Rector, PHPStan, PHPMD, three PHPArkitect suites,
`doctrine:schema:validate` for every entity manager, PHPUnit, then Prettier, ESLint, `tsc`,
frontend tests and the production build, then the runner agent's and MCP server's own test
suites. See [Contributing](docs/contributing/README.md#the-gate) for the full breakdown and
timing. Tool configs live in `backend/tools/<tool>/`, not at the backend root.

## Backend architecture

Each bounded context is its own Symfony application under `backend/app/<context>/`, holding
`src/`, `tests/` and `config/` (its `services.yaml` and `routes.yaml`). Inside `src/` the shape is
`<Module>/<Layer>/...`, e.g. `app/shift/src/Shift/Domain/Shift/Model/Shift.php`. Class names did not
move with the files — everything is still `App\<Context>\...`, mapped through PSR-4 in
`composer.json`. Contexts: `File`, `Identity`, `Notification`, `Organization`, `Project`,
`Playbook`, `Qualification`, `Runner`, `Shift`. `Shared` stays a library in `backend/src/Shared`.

One kernel serves them all, parameterised by `APP_ID` (default `monolith`, which imports every
context's config). Individual applications do not boot standalone yet — messenger, security and
doctrine configuration is still shared. See `docs/adr/0001-multiple-kernels.md`.

Layers are enforced by PHPArkitect, so these are hard constraints, not style preferences:

- **Domain** depends on nothing else. Only these folders are allowed: `Model`, `ValueObject`,
  `Repository`, `Specification`, `Policy`, `Event`, `Exception`, `Enum`, `Interface`, `Service`.
- **Application** may use Domain and Infrastructure. CQRS only — commands, queries, handlers and
  domain listeners. No `*Service` classes and no `Service` namespace here. Commands, queries,
  handlers, events and policies are `readonly`.
- **Infrastructure** may use only Domain.

### Context isolation

**A context never imports another context.** Each one is meant to survive being extracted into its
own service. There are exactly two legal ways across a boundary:

1. A port interface in `App\Shared\Domain\Service`, implemented by the owning context and wired
   through an alias in `config/services.yaml` — for synchronous reads.
2. A queued message, where producer and consumer each keep their own copy of the message class and
   share only the wire contract — for writes and notifications.

Every context has its own Doctrine entity manager, PostgreSQL schema and migration directory
(`backend/migrations/<Context>/`).

## Frontend

`src/app/core/` (guards, interceptors, layouts, services), `src/app/features/<feature>/`,
`src/app/shared/`. Routes are lazy-loaded per feature from `app.routes.ts`. SSR is real — code must
not assume a browser; check before touching `window`/`document`.


## Conventions

- Conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `ci:`, `chore:`).
- Stage specific files; never `git add -A`. Check `git status` after staging — this repo often has
  human-staged work sitting in the index that a careless commit will sweep up.
- Never commit `.env`, secrets, `node_modules`, `dist/`, `var/` or tool caches.
- Write no comments explaining what code does. Comment only a non-obvious *why*.
