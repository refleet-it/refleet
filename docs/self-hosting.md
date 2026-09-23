# Running your own instance

Refleet runs on your own machine with Docker and a hostname. This page covers a whole
instance; for a runner alone — which is all you need if someone else already runs the
application — see [installing a runner](runner/installation.md) instead.

## What you need

- A machine with Docker and Compose, 4 GB of RAM and 20 GB of disk.
- A hostname pointing at it. Caddy gets a certificate for that name automatically, so the DNS
  record has to resolve before you start, and ports 80 and 443 have to be reachable.
- An SMTP transport. Invitations, password resets and finished-run notices go out by email;
  without one they are written to the log and never arrive.
- A GitLab account or a self-managed GitLab, with repositories to work on.
- An API key for whichever agent you plan to run — Claude Code or Kiro — on the machine that
  runs the runner. The application itself never calls an agent; only runners do.

## Starting up

```bash
git clone https://github.com/refleet-it/refleet.git
cd refleet
cp .env.selfhost.dist .env
```

Open `.env` and fill in `APP_DOMAIN`, `MAILER_DSN` and `MAILER_FROM_EMAIL`. Leave the secrets
under "Generated" empty — they are about to be written for you. Then:

```bash
make selfhost
make selfhost-account EMAIL=you@example.com ADMIN=1
```

`make selfhost` generates the secrets that are still empty, pulls the images and brings the
stack up: migrations run to completion first, then the backend, the queue worker, the
server-rendered frontend, PostgreSQL and Caddy. Running it again is safe — it never rotates a
secret that already has a value.

`make selfhost-account` creates an account that can sign in immediately, skipping the
verification email you cannot receive yet. Sign in at `https://<APP_DOMAIN>`, create your
organisation, and invite everyone else from inside the application.

Registration is closed by default, so an instance that is reachable from the internet does not
hand out accounts to whoever finds it. Set `ALLOW_SIGNUP=true` to open it.

## Connecting GitLab

Under **Settings → Connect GitLab**, either paste a personal access token with the `api` scope,
or register an OAuth application and set `GITLAB_OAUTH_CLIENT_ID` and
`GITLAB_OAUTH_CLIENT_SECRET`. Its redirect URI is
`https://<APP_DOMAIN>/dashboard/settings/gitlab/callback`, scope `api`, confidential.

For a self-managed GitLab, point `GITLAB_OAUTH_BASE_URL` at it. The token path works against
any GitLab without that setting; only the OAuth handshake needs to know the host.

## Adding a runner

Nothing happens until a runner claims the work. A runner needs no inbound network access — it
polls — so it can live anywhere that can reach your instance, and it is often better off on a
machine with the agent CLIs already installed.

Create an API key under **Settings → Developer**, then either run one alongside the stack:

```bash
# in .env
REFLEET_API_KEY=…
ANTHROPIC_API_KEY=…

docker compose -f compose.selfhost.yml --profile runner up -d
```

or run it anywhere else, which is the same thing pointed at your instance:

```bash
npm install -g @refleet-it/runner
refleet login --api-url https://<APP_DOMAIN>/api
refleet run
```

See [how a runner works](runner/how-it-works.md) for what it does with a job.

## Backups

Everything that cannot be rebuilt is in two places: the PostgreSQL volume and `.env`.

```bash
docker compose -f compose.selfhost.yml exec -T database \
    pg_dump -U refleet refleet | gzip > refleet-$(date +%F).sql.gz
```

Keep `.env` with the dump and treat it as a secret. A database restored without its original
`JWT_PRIVATE_KEY` signs everybody out, and without its original
`GITLAB_TOKEN_ENCRYPTION_KEY` every stored GitLab connection has to be reconnected by hand —
the tokens are encrypted with it and there is no way back.

The `uploads` volume holds avatars and attachments; losing it costs those files and nothing else.

## Updating

```bash
docker compose -f compose.selfhost.yml pull
make selfhost
```

Migrations run before anything serves traffic, so an update that changes the schema applies
itself. Pin `IMAGE_TAG` to a release instead of `latest` if you would rather choose when that
happens.

Runners update themselves when they are installed from npm, and otherwise want their image
pulled — see [updating a runner](runner/installation.md#updating).

## Behind a proxy you already run

If something else already terminates TLS on that machine, drop the `caddy` service and point
your proxy at the `frontend` container on port 4000, with `/api/*`, `/uploads/*` and
`/notifications/*` going to `backend` on port 8080. Keep `APP_DOMAIN` set to the public
hostname either way: it is what the application builds links with, and what
`TRUSTED_HOSTS` and CORS are derived from.

## How this differs from the production topology

In production Refleet runs one container per bounded context behind a routing gateway — the
shape [ADR 1](adr/0001-multiple-kernels.md) describes. A self-hosted instance runs a single
backend booted with `APP_ID=monolith`, which serves all of them from one process: the ports
contexts use to reach each other resolve in-process instead of over HTTP. Same code, same
migrations, fewer moving parts.
