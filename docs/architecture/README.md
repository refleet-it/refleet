# Architecture

The backend is nine bounded contexts plus a shared library. Each context owns a slice of the
product, its own database schema and its own Symfony application directory.

| Context        | Owns                                                        |
| -------------- | ----------------------------------------------------------- |
| `Identity`     | accounts, sign-in, JWTs, refresh tokens, API keys            |
| `Organization` | organisations, employees, invitations, the GitLab connection |
| `Project`      | the repositories an organisation has registered              |
| `Qualification`| deciding which projects a change applies to                  |
| `Shift`        | applying a change across the qualified projects              |
| `Playbook`     | reusable prompt fragments (tasks and rules) shifts and qualifications are composed from |
| `Runner`       | the fleet of workers and the jobs they claim                 |
| `File`         | uploads, image processing, storage                           |
| `Notification` | email notifications and per-account preferences              |

## Where the code lives

```text
backend/
├─ app/<context>/          one Symfony application per context
│  ├─ config/              its services.yaml, routes.yaml, doctrine
│  ├─ src/<Module>/<Layer>/…
│  └─ tests/
├─ src/Shared/             the library every context depends on
├─ src/Kernel.php          one kernel, parameterised by APP_ID
└─ migrations/<Context>/
```

Class names do not follow the directories: everything is still `App\<Context>\…`, mapped through
PSR-4. That is deliberate, and the reasoning is in
[ADR 1](../adr/0001-multiple-kernels.md).

Today one process serves all of them (`APP_ID=monolith`). The applications are not yet
individually bootable — the same ADR records exactly what still stands in the way.

## Layers

Inside a context: `<Module>/Domain`, `<Module>/Application`, `<Module>/Infrastructure`. The
direction of dependencies is enforced by PHPArkitect, so these are constraints rather than
conventions — breaking one fails `make quality-check`.

**Domain** depends on nothing else, not even the framework. It may only contain these folders:
`Model`, `ValueObject`, `Repository`, `Specification`, `Policy`, `Event`, `Exception`, `Enum`,
`Interface`, `Service`. Value objects, policies and events are `readonly`.

**Application** may use Domain and Infrastructure, and holds CQRS artefacts only: commands,
queries, handlers and domain listeners. Commands, queries and handlers are `readonly`; a
synchronous command implements `CommandInterface` and its handler `CommandHandlerInterface`. An
`Application\Service` namespace is forbidden, as is any class named `*Service` — if it does not
fit a command or a query, the modelling is wrong. `Application\Model` and `Application\ReadModel`
are forbidden too: a DTO belongs next to the controller, query or command it serves.

**Infrastructure** may use only Domain. Controllers, Doctrine repositories, message handlers and
anything else that talks to the outside world.

## Contexts do not import each other

This is the rule the whole structure rests on, and PHPArkitect enforces it with no exemption for
any layer. A context reaches another in exactly two ways.

### A port in Shared, for reads

The consumer depends on an interface in `App\Shared\Domain\Service`; the owning context
implements it and the wiring lives in that context's `services.yaml`.

| Port                              | Implemented by |
| --------------------------------- | -------------- |
| `UserEmailProviderInterface`       | Identity       |
| `InvitedAccountRegistrarInterface` | Identity       |
| `OrganizationContextProviderInterface` | Organization |
| `GitLabWebhookSecretProviderInterface` | Organization |
| `ProjectCatalogInterface`          | Project        |
| `ProjectRegistryInterface`         | Project        |
| `QualifiedProjectsInterface`       | Qualification  |
| `RunnerDirectoryInterface`         | Runner         |
| `TemplateEmailSenderInterface`     | Notification   |
| `StorageAvailabilityCheckerInterface` | File        |
| `ImageUrlResolverInterface`        | File           |

### A queued message, for writes and notifications

Producer and consumer each keep their own copy of the message class and share only the wire
format. `MappedMessageSerializer` writes `{type, data}`, deriving the type from the message's
folder name, and the consuming context maps that type back onto its own class. Neither side
imports the other.

| From          | To            | Messages |
| ------------- | ------------- | -------- |
| Identity      | Notification  | EmailVerification, PasswordReset, PasswordResetCompleted |
| Identity      | Organization  | AccountMirrored, AccountEmailChanged |
| Qualification | Notification  | QualificationFinished |
| Qualification | Runner        | RunnerJobRequested, OwnerJobsCancellationRequested |
| Shift         | Notification  | ShiftFinished |
| Shift         | Runner        | RunnerJobRequested, OwnerJobsCancellationRequested |
| Runner        | Identity      | RunnerArchived |
| Runner        | Qualification | QualificationTargetResultReported |
| Runner        | Shift         | ShiftTargetResultReported |

Each `to_<context>` queue belongs to the context that reads it.

## Data

Every context has its own PostgreSQL schema, its own Doctrine entity manager and its own
migration directory. A repository injects a plain `EntityManagerInterface` and gets the right one,
because each application binds it in its own `services.yaml`. Nothing crosses schemas: a context
that needs another's data asks through a port or waits for a message.

## Further reading

- [ADR 1 — Bounded contexts become separate Symfony applications](../adr/0001-multiple-kernels.md)
- [Running the stack locally](../getting-started/README.md)
