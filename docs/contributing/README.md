# Contributing

What the tooling will hold you to, and what it will not tell you.

## The gate

```bash
make quality-check
```

Everything must pass before a commit. Four sections, in order:

- **Security** — a git-history secret scan, a `composer audit`/`npm audit` for each of the
  backend, frontend, MCP server and runner agent, a HIGH-and-up dependency vulnerability scan, and
  `tofu fmt`/`tofu validate` for the Terraform config. No `tofu plan` and no cloud credentials —
  that stays CI's job.
- **Backend** — PHP CS Fixer, Rector, PHPStan at level 9, three PHPArkitect suites, PHPMD,
  `doctrine:schema:validate` for every entity manager, PHPUnit, a live request confirming
  `/api/doc` actually serves the Swagger UI, then a documentation check.
- **Frontend** — Prettier, ESLint, `tsc`, the unit tests, a production build.
- **Runner and MCP server** — each project's own test suite (`node:test`).

About two minutes warm. CI runs a superset of the same checks — its Terraform job additionally
runs the real `tofu plan` against live state, which needs credentials this local gate deliberately
does not have.

It is that quick because almost every tool in it caches: PHPStan keeps its result cache, Angular
keeps `.angular/cache`. Change a dependency and the first run afterwards throws all of that away —
expect several minutes, and do not take a long silence at `Building...` for a hang.

Run it before you think you are finished, not after. Rector and PHP CS Fixer rewrite files in
place, so it is also how the code gets formatted.

Tool configuration lives in `backend/tools/<tool>/`, not at the backend root.

## What is enforced, not suggested

These are PHPArkitect rules. Breaking one fails the gate, so there is no need to remember them —
but knowing them saves a round trip. The full picture, with the reasoning, is in
[the architecture page](../architecture/README.md).

- **A context never imports another context.** Reach one through a port in
  `App\Shared\Domain\Service` or a queued message. No layer is exempt.
- **Domain depends on nothing**, and may only use the folders `Model`, `ValueObject`,
  `Repository`, `Specification`, `Policy`, `Event`, `Exception`, `Enum`, `Interface`, `Service`.
- **Application is CQRS only.** No `Application\Service` namespace, no class named `*Service`, no
  `Application\Model` or `Application\ReadModel` — a DTO belongs beside the controller, query or
  command it serves. Commands, queries and handlers are `readonly`.
- **Infrastructure may use only Domain.**
- Value objects are `final` and `readonly`; policies and events are `readonly` too.

PHPMD adds a limit the others do not: a class in the Application layer may not exceed 500 lines.

## Tests

They live next to the code they cover: `backend/app/<context>/tests/{Unit,Integration,Functional}`.
`backend/tests/` keeps only what is genuinely shared — Shared's own unit tests, the architecture
tests and the helpers.

A test class's path must mirror the production class's path, and a test method is named in
`snake_case` describing what it asserts:

```php
public function rejects_reuse_of_an_already_rotated_refresh_token(): void
```

The suite runs in a random order, so a test that depends on another having run first will fail
eventually rather than immediately. Do not rely on ordering.

## Commits

Conventional commits. What the history actually uses, most common first: `feat`, `refactor`,
`docs`, `chore`, `fix`, `security`, `ci`, `style`.

Write the subject about the change, and use the body for why it was needed rather than what the
diff already shows.

Stage specific files. Never `git add -A`, and check `git status` after staging — this repository
regularly has human-staged work sitting in the index that a careless commit will sweep up. Never
commit `.env`, secrets, `node_modules`, `dist/`, `var/` or tool caches.

## Comments

Default to none. Well-named code says what it does; a comment earns its place only when the *why*
is not obvious — a constraint, an invariant, a workaround for a specific bug, behaviour that would
surprise a reader. Do not describe the current task, the fix or the callers: that belongs in the
commit message and rots as the code moves.

## Wording

One word that has bitten us: **"fleet" on its own means the GitLab projects**, the thing a change
is carried across. That is how the landing page, the settings copy and this documentation all use
it. Runners are a fleet too, so they always take the qualifier — **"the runner fleet"**, never a
bare "fleet" and never "fleet runners".

The rest of the domain vocabulary is the table in
[the architecture page](../architecture/README.md); when a name changes there, it changes in the UI
copy too.

## Documentation

Pages live in `docs/`, are plain Markdown, and are rendered both by GitLab and by the application
at `/docs`. The rules a page follows — the title comes from its opening heading, links point at
the `.md` file, and `docs/README.md` is the navigation — are written down in
[the index itself](../README.md).
