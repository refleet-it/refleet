# Installing and updating a runner

A runner is a worker that claims jobs from the Refleet API, clones the target repository and
modernises it, doing the actual work with Claude Code or Kiro — whichever it detects. Run as many
as you like; each registers under its own `RUNNER_NAME`.

The whole loop — heartbeat, claim, checkout cache, agent run, branch push, merge request, report —
is the TypeScript in `runner/agent/src/fleet/`, started with `refleet run`. The Docker image
and the npm package run exactly that code; the image only adds the two agent CLIs on top.

## Credentials

Every runner needs two values:

- `REFLEET_API_URL` — e.g. `https://refleet.it/api`
- `REFLEET_API_KEY` — an API key created through `POST /api/identity/api-keys`

There are two ways to supply them, and the runner prefers the first:

1. **Set them directly.** Anything non-interactive — Docker, a server, CI — should do this.
2. **`refleet login`.** Opens `refleet.it` in your browser, where you approve the login while
   signed in — no password ever goes through the terminal. The CLI then receives a fresh
   long-lived API key and stores it in `~/.config/refleet/runner.json`. Re-running it revokes
   the previous local key, so it does not litter your account. `run` falls back to this only
   when neither variable is set.

   `refleet login` alone talks to production. `--api-url <url>` (or `REFLEET_API_URL`) points
   it at another instance, e.g. `--api-url http://localhost/api` for a local stack;
   `--no-browser` only prints the link, for SSH sessions and machines without a desktop — open
   it from any device that is signed in.

It additionally needs `ANTHROPIC_API_KEY`, `KIRO_API_KEY`, or both — it registers whichever agents
it can actually detect and use at startup.

## As a local CLI (npm)

Published to npmjs as [`@refleet-it/runner`](https://www.npmjs.com/package/@refleet-it/runner). Needs
Node 22 or newer, `git`, and at least one agent CLI on `PATH` — you supply the agent yourself; the
Docker image is the only thing that bakes in Claude Code and Kiro.

```bash
npm install -g @refleet-it/runner
refleet login
refleet run
```

Cloned repositories are cached under `~/.cache/refleet/workspace` (override with
`WORKSPACE_CACHE_DIR`). The package README carries a minimal systemd unit for keeping it running
on a server — and a supervisor is what makes updates automatic, see [Updating](#updating).

## From the container registry

The image is `ghcr.io/refleet-it/refleet-runner`, tagged `latest` and with the
commit SHA it was built from. Pin the SHA in anything you care about; `latest` moves under you.

```bash
docker run -d --restart unless-stopped \
  -e REFLEET_API_URL=https://refleet.it/api \
  -e REFLEET_API_KEY=… \
  -e RUNNER_NAME=prod-runner-01 \
  -e ANTHROPIC_API_KEY=… \
  -v /srv/refleet-runner:/workspace \
  ghcr.io/refleet-it/refleet-runner:latest
```

The workspace volume is a cache; losing it costs a re-clone, nothing more. The image's entrypoint
is the CLI itself, so `docker run --rm … runner-agent whoami` works for a quick credentials
check. Updating means pulling the new tag and recreating the container — by hand, or with
[Watchtower](https://containrrr.dev/watchtower/) watching the `latest` tag; see
[Updating](#updating).

## From a checkout

`runner/agent/` ships a standalone compose file that builds from the repository:

```bash
cd runner/agent
cp .env.dist .env        # then fill in REFLEET_API_URL, REFLEET_API_KEY and any agent keys
docker compose up --build
```

The build context is `runner/agent/` itself; by hand that is `docker build .` from there. The
whole local stack — backend, frontend and a runner — comes up with `make init` from the
repository root instead.

## Configuration

| Variable                                                         | Default                                                 | Meaning                                                          |
| ------------------------------------------------------------------ | ------------------------------------------------------- | ----------------------------------------------------------------- |
| `RUNNER_NAME`                                                     | the hostname                                            | Prefix for this runner's name in Refleet (`<name>-<engine>`)     |
| `REFLEET_API_URL`                                                 | from `login`                                            | Required                                                           |
| `REFLEET_API_KEY`                                                 | from `login`                                            | Required                                                           |
| `HEARTBEAT_INTERVAL_SECONDS`                                      | `30`                                                    | How often it reports itself alive                                  |
| `POLL_INTERVAL_SECONDS`                                           | `10`                                                    | How often it asks for work                                        |
| `WORKSPACE_CACHE_DIR`                                             | `/workspace/cache` in Docker, `~/.cache/refleet/workspace` otherwise | Where cloned repositories are cached                  |
| `WORKSPACE_CACHE_MAX_SIZE_MB`                                     | `5120`                                                  | Cache is trimmed past this, least recently used first             |
| `ANTHROPIC_API_KEY`, `KIRO_API_KEY`                               | —                                                       | Credentials for whichever agents this runner should offer          |
| `CLAUDE_AVAILABLE_MODELS`, `KIRO_AVAILABLE_MODELS`                | —                                                       | Comma-separated model ids offered in the shift/qualification model dropdown |
| `CLAUDE_MODEL`, `CLAUDE_QUALIFICATION_MODEL`                      | —                                                       | Override the model, generally and for qualification runs           |
| `CLAUDE_ALLOWED_TOOLS`, `CLAUDE_QUALIFICATION_ALLOWED_TOOLS`      | —                                                       | Restrict the tools the agent may use                               |
| `CLAUDE_PERMISSION_MODE`, `CLAUDE_EXTRA_ARGS`, `CLAUDE_ADD_DIRS`  | —                                                       | Passed through to the Claude CLI                                   |
| `CLAUDE_SKIP_PERMISSIONS`                                         | `true`                                                  | Headless runs have nobody to approve prompts                       |
| `REFLEET_CLAUDE_PATH`, `REFLEET_KIRO_PATH`                        | —                                                       | Pin an explicit executable instead of looking it up on `PATH`      |
| `REFLEET_AUTO_UPDATE`                                             | `npm` when npm-installed, `off` otherwise               | `npm`: reinstall and restart when behind; `exit`: only restart; `off`: stay put — see [Updating](#updating) |

GitLab needs no configuration on the runner: before each job it fetches the organisation's GitLab
URL and token from the API and passes the token per git invocation, never writing it into the
cached checkout. Merge requests are opened through the GitLab REST API from the same token; a
retried job that force-pushes its branch again reuses the merge request that is already open.

## Updating

Every heartbeat carries the runner's version, and the answer carries the newest one published
to npm (the API reads the package's `latest` tag and caches it for ten minutes). The runner page
and the fleet list show both, with an **Update available** badge when the runner is behind.

What happens next depends on how the runner was installed:

- **npm** (`npm install -g`) — automatic. When behind, the runner runs
  `npm install -g @refleet-it/runner@<latest>` itself, finishes the jobs in progress and exits
  with code 75; the supervisor's restart (`Restart=always` in the systemd unit from the package
  README) runs the new version. A failed install is logged and retried an hour later, and the
  runner keeps working meanwhile. Nothing is ever cut short: the decision is made between jobs,
  and a host running both agents lets the other engine's job finish too.
- **Docker** — the container cannot replace its own image, so pair it with
  [Watchtower](https://containrrr.dev/watchtower/) on the `latest` tag, or pull and recreate by
  hand. The image reports the same `<major>.<minor>.<pipeline>` version the npm package of that
  pipeline carries, so the dashboard shows it as outdated exactly when a newer image exists.
  Inside the image `REFLEET_AUTO_UPDATE` is effectively `off`; set it to `exit` only if
  something outside recreates the container on every restart.
- **Checkout** — `off`; rebuild and restart yourself.

The **Update** button on a runner's page forces the matter regardless of mode: the request is
delivered on the runner's next heartbeat (within `HEARTBEAT_INTERVAL_SECONDS`), the runner
installs first where it can, finishes its jobs, and exits for the supervisor to restart. It is
delivered once — a supervisor that brings back the same version does not put the runner into a
restart loop, the button just needs pressing again after the underlying install is fixed. An
`Update requested` note stays on the page until the runner has picked it up.

`REFLEET_AUTO_UPDATE=off` pins a version deliberately; the dashboard still shows it as outdated.

## When it will not start

The runner exits immediately with `REFLEET_API_URL and REFLEET_API_KEY are both required` when
neither the environment nor a stored login provides them — it has no idle mode and will not sit
there waiting to be configured. Check what it thinks it has with `refleet whoami`. It
exits the same way when no agent CLI is found on `PATH`.

`SIGINT`/`SIGTERM` let a job in progress finish, then stop the loop. An unexpected error inside
the loop exits non-zero on purpose, so Docker's restart policy or systemd brings the whole runner
back rather than leaving it half-registered.

## Releasing

Nothing is released by hand. Every push to `main` publishes both the npm package and the
container image (`.github/workflows/release.yml`).

The version is `<major>.<minor>.<run number>`: the first two come from `package.json`, the
patch is the workflow run's own number, so versions increase strictly without anyone touching
the version field. Open a new series by bumping the minor in `package.json`. Publishing is
skipped rather than failed when that version already exists, so re-running a workflow is safe.

The image is pushed to the container registry under the same commit, tagged `latest` and with
the commit SHA.
