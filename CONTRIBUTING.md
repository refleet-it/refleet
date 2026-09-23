# Contributing

Thanks for looking. This is a young project and the useful contributions are large ones —
GitHub support, another agent engine, a Helm chart — as much as small ones.

## Before a large change, open an issue

Not bureaucracy: the architecture below has hard rules that are enforced by static analysis,
and it is miserable to discover one of them after writing a feature. A short issue saying
what you want to do saves that.

Small fixes — a bug, a typo, a wrong default — need no issue. Just send the pull request.

## Getting set up

```bash
cp .env.dist .env && cp backend/.env.dist backend/.env
make init
```

Everything runs in Docker; you need no PHP, Node or PostgreSQL on the host. `make init`
generates a JWT keypair, builds the containers, migrates every schema and loads fixtures.
The accounts it seeds are in [docs/getting-started](docs/getting-started/README.md).

## The gate

```bash
make quality-check
```

This is what CI runs, and it has to pass. It takes a few minutes and covers a security
section (secret scan, dependency audits, Trivy), then php-cs-fixer, Rector, PHPStan, PHPMD,
three PHPArkitect suites, `doctrine:schema:validate` for every entity manager, PHPUnit, then
Prettier, ESLint, `tsc`, the frontend tests and production build, and finally the runner's
tests and the MCP server's.

Narrower targets while you work: `make test`, `make test-runner`, `make phpstan`,
`make phpcsfixer`.

## The rules that are not style preferences

These are enforced by PHPArkitect and will fail the build.

**Layers.** `Domain` depends on nothing else and may only use the folders `Model`,
`ValueObject`, `Repository`, `Specification`, `Policy`, `Event`, `Exception`, `Enum`,
`Interface`, `Service`. `Application` may use Domain and Infrastructure, is CQRS only —
commands, queries, handlers, domain listeners — and has no `*Service` classes.
`Infrastructure` may use only Domain.

**A context never imports another context.** Each of the nine is meant to survive being
extracted into its own service. There are exactly two legal ways across a boundary:

1. a port interface in `App\Shared\Domain\Service`, implemented by the owning context and
   aliased in its `config/services.yaml` — for synchronous reads;
2. a queued message, where producer and consumer each keep their own copy of the message
   class and share only the wire contract — for writes and notifications.

If a change seems to need a third way, that is worth an issue rather than a workaround.

**Server-side rendering is real.** Frontend code must not assume a browser. Check before
touching `window` or `document`.

## Conventions

- Conventional commits: `feat:`, `fix:`, `refactor:`, `docs:`, `ci:`, `chore:`.
- Comments explain a non-obvious *why*. Do not write comments that restate the code.
- Tests live beside the context they cover, under `app/<context>/tests/`.

## Licence and your contribution

Refleet is under the [Refleet License](LICENSE) — Apache 2.0 plus conditions on hosted
services, commercial embedding and the name. `runner/agent/` is the exception and stays MIT.
There is no CLA: by opening a pull request you contribute your work under whichever of the
two covers the files you touched.

## Security

Do not open an issue for a vulnerability. See [SECURITY.md](SECURITY.md).
