# Getting started

Bringing the whole stack up on your own machine: the API, the frontend, a database, object
storage, a mail catcher and a runner.

## What you need

Docker with Compose, and roughly 6 GB of free disk for the images. Nothing else — PHP, Node,
Composer and the database all live inside containers, and every `make` target drives them from
outside.

## First run

```bash
cp .env.dist .env
cp backend/.env.dist backend/.env
make init
```

`make init` generates the JWT signing keypair into both `.env` files (the templates ship it
empty — no private key is committed), builds and starts the containers, installs the Composer
dependencies, drops and re-migrates the database schemas,
loads the fixtures, and warms the test cache that PHPStan reads. It is safe to run again; it
resets the database each time and leaves an existing keypair alone.

When it finishes:

| What                | Where                                             |
| ------------------- | ------------------------------------------------- |
| Frontend            | <http://localhost:4200>                           |
| API                 | <http://localhost/api>                            |
| API documentation   | <http://localhost/api/doc>                        |
| Sent mail           | <http://localhost:8025> (Mailpit — nothing leaves) |
| PostgreSQL          | `localhost:5555`                                  |

## Signing in

The fixtures seed accounts you can use straight away. They all share the password
`password123`:

| Account                     | Role          |
| --------------------------- | ------------- |
| `user@refleet.test`           | user          |
| `admin@refleet.test`          | administrator |
| `owner@acme-robotics.test`  | organisation owner, with projects and runners |

These exist only in local fixtures.

## Day to day

```bash
make start          # bring the stack back up
make quality-check  # the gate everything must pass before a commit
make db-recreate    # reset the database and reload fixtures
make exec           # a shell inside the backend container
make front-exec     # a shell inside the frontend container
make clean          # stop everything and delete the volumes
```

`make quality-check` is the one that matters — everything it runs and how long it takes is in
[Contributing](../contributing/README.md#the-gate).

## When something is wrong

**The database looks stale or migrations conflict.** `make db-recreate` drops every context schema
and rebuilds from migrations and fixtures.

**A container will not start.** `docker compose ps` shows the state and `docker compose logs -f
<service>` shows why. The services are `caddy-gateway`, `backend-<context>`, `worker-<context>`,
`database`, `frontend`, `mail` and `runner`.

**A runner exits immediately.** It needs `REFLEET_API_URL` and `REFLEET_API_KEY` and has no idle
mode. Locally the fixtures seed a fixed API key that `.env.dist` already points at, so this
usually means the fixtures did not load — see [installing a runner](../runner/installation.md).

**A container behaves like it's running old code, or is missing an env var you know is set.**
`make start` does not rebuild images — only `make init` does — so a container built before a
recent Dockerfile or dependency change keeps running on the stale image indefinitely; `docker
compose restart <service>` recreates the container but reuses that same stale image, so it will
not fix this. `docker compose up -d --build <service>` rebuilds and recreates it from current
source. This mostly bites containers nobody restarts often, like the runners.

## Next

- [How the backend is put together](../adr/0001-multiple-kernels.md) — bounded contexts as
  separate applications
- [Installing and updating a runner](../runner/installation.md)
