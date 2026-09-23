# 1. Bounded contexts become separate Symfony applications

- **Status:** Accepted
- **Date:** 2026-08-09

## Context

The backend holds eight bounded contexts — `File`, `Identity`, `Notification`, `Organization`,
`Project`, `Qualification`, `Runner`, `Shift` — plus `Shared`. They are already isolated at the
source level: PHPArkitect forbids any context from importing another, and the only legal crossings
are a port interface in `App\Shared\Domain\Service` or a queued message. Each context already owns a
PostgreSQL schema, a Doctrine entity manager and a migration directory.

What they do not have is a boundary at runtime. One kernel boots all eight, one container serves
them all, and `config/services.yaml` carries a block of per-namespace bindings whose only job is to
hand each context the right entity manager:

```yaml
App\Shift\:
    bind:
        Doctrine\ORM\EntityManagerInterface: '@doctrine.orm.shift_entity_manager'
```

That block grows with every context and exists purely because eight entity managers share a
container. The same pressure shows up in `config/services.yaml` autowiring exclusions and in the
`doctrine.yaml` mapping list.

The stated goal is that any context can be extracted into its own deployable service. Today that
extraction would be a research project. We want it to be a configuration change.

Symfony documents this exact shape under
[multiple kernels](https://symfony.com/doc/current/configuration/multiple_kernels.html): one Kernel
class parameterised by an application id, a shared config/src at the root, and per-application
config/src beneath it.

## Decision

### 1. Layout

```text
backend/
├─ app/
│  ├─ identity/{config,src,tests}
│  ├─ file/{config,src,tests}
│  └─ …one directory per context
├─ config/            shared: framework, dbal connections, messenger transports, security base
├─ src/
│  ├─ Kernel.php      the single parameterised kernel
│  └─ Shared/         the Shared context, unchanged
├─ bin/console
└─ public/index.php
```

The Symfony documentation uses `apps/`. We use `backend/app/` because that is the path this project
asked for; the difference is cosmetic and deliberate, so please do not "correct" it.

`Shared` does not become an application. It is a library every application depends on.

### 2. Namespaces do not change

Classes keep their `App\<Context>\<Module>\<Layer>\…` names. Only their location on disk moves, and
`composer.json` remaps PSR-4 accordingly:

```json
"App\\Shared\\": "src/Shared/",
"App\\Identity\\": "app/identity/src/",
"App\\Shift\\": "app/shift/src/"
```

This is the decision that makes the whole migration affordable. Renaming to the documentation's
`Shared\` / `Api\` convention would rewrite every `use` statement, every PHPArkitect rule that
matches on `App\*\Domain\*`, every PHPStan baseline entry and every test namespace — enormous churn
that buys nothing. File moves are reviewable; a global namespace rewrite is not.

### 3. One kernel, parameterised by application id

Per the Symfony documentation: `App\Kernel` takes an `$id`, derives `getCacheDir()` and `getLogDir()`
per application, and merges shared config with `app/<id>/config`. One `public/index.php` and one
`bin/console`, both driven by `APP_ID`, with `bin/console --id=<app>` for one-off commands.

### 4. Each application declares exactly one entity manager

An application maps only its own context and names its entity manager `default`. Plain
`EntityManagerInterface` injection then resolves correctly on its own, and the per-namespace `bind`
block in `config/services.yaml` is deleted rather than extended. Migrations stay in
`backend/migrations/<Context>/`, wired from the owning application's config.

### 5. Tests move with their context

`backend/app/<context>/tests/`, one PHPUnit testsuite per application, plus a `shared` suite for
`backend/tests/`. Each application gets a test case that boots the kernel with its own id. The
PHPArkitect test-correspondence rule follows the same move.

### 6. Runtime topology is deferred, deliberately

Splitting into eight served applications means eight upstreams and a Caddy that routes by path
prefix. Deploy is gated off behind `DEPLOY_ENABLED` and there is currently no CI runner, so such a
change could not be validated — shipping it blind would be worse than not shipping it.

So this phase delivers the *structural* split: each context owns its sources, its tests, its service
wiring and its routing under `app/<context>`. A `monolith` application id imports all of them, which
keeps one entry point and one container working exactly as before. That composite stays the deployed
topology until deploy is verifiable again.

**What this does not yet deliver.** An individual application does not boot on its own. Measured
by running `bin/console --id=<context> cache:warmup` for all eight: every one fails, because three
pieces of shared configuration still name classes from every context.

- `config/packages/messenger.yaml` — each transport declares a serializer owned by a specific
  context, and the routing map lists every cross-context message class. Booting `identity` alone
  dies on a missing `App\Notification\...\NotificationMessageSerializer`. This is the first failure
  each app hits.
- `config/packages/security.yaml` — the user provider and both authenticators are Identity classes,
  so every application other than Identity would need them present.
- `config/packages/doctrine.yaml` — all eight entity managers are declared centrally, so each
  application would carry mappings for contexts it does not use.

Extraction is therefore not yet "point a container at a different `APP_ID`". Making it so means
moving those three per context: a transport set an application actually produces to or consumes
from, its own security configuration, and its own single entity manager. That is real design work —
messenger in particular is inherently a producer/consumer pair, so deciding which side owns a
transport is a modelling decision, not a copy-paste — and it cannot be validated while deploy is
off. It is tracked separately rather than rushed in here.

### 7. Tooling follows the paths

PHPStan, PHPArkitect, PHP CS Fixer, Rector, PHPMD and PHPUnit are all currently pointed at `src/`
and `tests/`. Each must additionally cover `app/*/src` and `app/*/tests`. A context is not migrated
until `make quality-check` is green with the new paths.

## Migration recipe

Established by moving `Notification` first. Each remaining context follows the same steps, one
context per change, with `make quality-check` green before the change lands.

1. `git mv backend/src/<Context> backend/app/<context>/src` — the module directories land directly
   under `src/`, so `App\<Context>\<Module>` resolves without a repeated segment.
2. Add `"App\\<Context>\\": "app/<context>/src/"` to the PSR-4 map in `composer.json`, then
   `composer dump-autoload`. The longest matching prefix wins, so the catch-all `App\` entry stays.
3. Create `app/<context>/config/services.yaml` with everything that context owns: its
   `App\<Context>\` resource, its persistence bindings, its service arguments, and the aliases for
   the `App\Shared\Domain\Service` ports it implements. Paths inside are relative to that file.
4. Create `app/<context>/config/routes.yaml` with the context's attribute route resource.
5. Register both in the monolith: an `imports:` entry in `app/monolith/config/services.yaml` and a
   `resource:` entry in `app/monolith/config/routes.yaml`.
6. Delete the same entries from the shared `config/services.yaml` and `config/routes/framework.yaml`.
7. Point the context's Doctrine mapping `dir` at the new location in `config/packages/doctrine.yaml`.
8. Verify before running the full gate — these catch the common mistakes far faster:
   `bin/console cache:clear` (routing and service references),
   `bin/console debug:router | grep <context>` (routes actually load from the app),
   `bin/console doctrine:schema:validate --em=<context>` (mapping directory is right).

Tooling needed teaching only once, when the first context moved: PHPArkitect's context discovery
and the test-correspondence rule both resolve through the PSR-4 map now, so they pick up each
subsequent context without further edits.

Watch for a context whose module directory repeats its own name — `Notification` contains both a
`Notification` and a `NotificationPreference` module, so its service resources are
`../src/Notification/...` and `../src/NotificationPreference/...`, not `../src/Notification/Notification/...`.

## Consequences

Extraction stops being a hunt through a shared tree: a context's sources, tests, service wiring and
routes are in one directory, and the per-namespace entity manager bindings and autowiring exclusions
no longer grow in a shared file with each new context. What remains before extraction is a
deployment decision rather than a refactor is the shared messenger, security and doctrine
configuration described under decision 6 — the win on boot time and per-application memory arrives
with that step, not this one.

The costs are real. There are now nine config trees instead of one, and a change to shared framework
config has to be considered against every application. Cache and log directories multiply under
`var/`. Anything that legitimately spans contexts — fixtures, the multi-entity-manager purger,
`doctrine:schema:validate` across all managers — needs an explicit "run this for every app" story
rather than getting it for free from a single kernel. `make init` and `make db-recreate` currently
hardcode a list of schemas and entity managers; that list becomes a loop over applications.

The migration also cannot be done in one commit. Contexts move one per iteration, least-coupled
first (`Notification`, then `File`, `Qualification`, `Shift`, `Runner`, `Project`, `Organization`,
and `Identity` last because security touches everything). The repository must be green and bootable
after every single step.

## Alternatives considered

**Leave it as one kernel.** Cheapest, and the source-level isolation would still hold. Rejected
because the runtime coupling is what makes extraction expensive, and that is the thing we said we
wanted to be cheap.

**Separate repositories per context now.** The honest end state for real microservices. Rejected as
premature: it multiplies CI, dependency management and release coordination before we know which
contexts actually need to scale independently, and it cannot be walked back cheaply.

**Adopt the documentation's `Shared\` / `Api\` namespaces.** Rejected under decision 2 — the churn
is enormous and the benefit is conformity with an example, not with a requirement.
