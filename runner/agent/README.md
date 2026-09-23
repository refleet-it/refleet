# @refleet-it/runner

A runner is a worker that claims code-modernisation jobs from the [Refleet](https://refleet.it)
API, checks out the target GitLab repository, lets an AI coding agent do the work and opens the
result as a merge request. This package runs that loop on your own machine, with an agent you
already have installed.

## Requirements

- Node.js 22 or newer
- `git` on `PATH`
- at least one supported agent CLI, authenticated:
  - [Claude Code](https://docs.claude.com/en/docs/claude-code) (`claude`) — needs `ANTHROPIC_API_KEY`
  - [Kiro CLI](https://kiro.dev/docs/cli/) (`kiro-cli`) — needs `KIRO_API_KEY`

The runner registers one Runner per agent it detects at startup, so a machine with both shows up
twice in Refleet.

## Install

```bash
npm install -g @refleet-it/runner
```

The command is `refleet`; `refleet-runner` still works as an alias for scripts written
against earlier releases.

## Connect and run

```bash
refleet login    # opens refleet.it, you approve in the browser → an API key is stored locally
refleet run
```

`login` never asks for a password: it prints a link (and opens it), you approve the request
while signed in, and the CLI collects a fresh API key. `--api-url <url>` targets another
instance (`REFLEET_API_URL` works too); `--no-browser` only prints the link, for headless boxes
and SSH sessions.

`login` keeps its API key in `~/.config/refleet/runner.json` (`XDG_CONFIG_HOME` is honoured).
Anything headless — a server, a container, CI — should instead set `REFLEET_API_URL` and
`REFLEET_API_KEY` directly; when both are set they take precedence over the stored login.

```bash
refleet whoami   # which credentials are in effect, and whether they still work
refleet logout   # revokes the stored key on the server and forgets it locally
```

`run` has no idle mode: without credentials it exits immediately, and a job in progress is
finished before `SIGINT`/`SIGTERM` stop it. Keep it alive with whatever supervisor you already use;
a minimal systemd unit:

```ini
[Unit]
Description=Refleet runner
After=network-online.target

[Service]
ExecStart=/usr/bin/env refleet run
Environment=REFLEET_API_URL=https://refleet.it/api
Environment=REFLEET_API_KEY=…
Environment=ANTHROPIC_API_KEY=…
Restart=always
RestartSec=10

[Install]
WantedBy=default.target
```

## Updating

An npm-installed runner keeps itself current. Every heartbeat tells it the newest published
version; when it is behind, it runs `npm install -g @refleet-it/runner@<latest>` itself, finishes
the jobs in progress and exits with code 75 — the supervisor's restart then runs the new code,
which is why the unit above says `Restart=always`. A failed install (no write access to the
global prefix, say) is logged and retried an hour later; the runner keeps working meanwhile.
`refleet version` prints what is installed.

`REFLEET_AUTO_UPDATE` changes that: `off` keeps the version pinned (Refleet still shows the
runner as outdated), `exit` skips the install and only stops — for supervisors that resolve the
version on their own, such as `ExecStart=npx -y @refleet-it/runner@latest run`. It defaults to
`npm` under an npm install and `off` anywhere else, so a checkout or the Docker image never
tries to reinstall itself.

The runner page in Refleet also has an **Update** button. It delivers a one-off stop on the
runner's next heartbeat, whatever the mode: the runner installs first where it can, finishes its
jobs and exits, and the supervisor brings it back.

## Configuration

| Variable                                                        | Default                    | Meaning                                                         |
| --------------------------------------------------------------- | -------------------------- | --------------------------------------------------------------- |
| `RUNNER_NAME`                                                   | the machine's hostname     | Prefix for this runner's name in Refleet (`<name>-<engine>`)   |
| `REFLEET_API_URL`, `REFLEET_API_KEY`                            | from `login`               | Where and as whom to poll                                       |
| `HEARTBEAT_INTERVAL_SECONDS`                                    | `30`                       | How often it reports itself alive                               |
| `POLL_INTERVAL_SECONDS`                                         | `10`                       | How often it asks for work                                      |
| `WORKSPACE_CACHE_DIR`                                           | `~/.cache/refleet/workspace` | Where cloned repositories are cached                          |
| `WORKSPACE_CACHE_MAX_SIZE_MB`                                   | `5120`                     | Least-recently-used checkouts are evicted past this             |
| `ANTHROPIC_API_KEY`, `KIRO_API_KEY`                             | —                          | Credentials for the agents this runner should offer             |
| `CLAUDE_AVAILABLE_MODELS`, `KIRO_AVAILABLE_MODELS`              | —                          | Comma-separated model ids offered in Refleet's model dropdown   |
| `CLAUDE_MODEL`, `CLAUDE_QUALIFICATION_MODEL`                    | —                          | Override the model, generally and for qualification runs        |
| `CLAUDE_ALLOWED_TOOLS`, `CLAUDE_QUALIFICATION_ALLOWED_TOOLS`    | —                          | Restrict the tools the agent may use                            |
| `CLAUDE_PERMISSION_MODE`, `CLAUDE_EXTRA_ARGS`, `CLAUDE_ADD_DIRS` | —                         | Passed through to the Claude CLI                                |
| `CLAUDE_SKIP_PERMISSIONS`                                       | `true`                     | Headless runs have nobody to approve prompts                    |
| `REFLEET_CLAUDE_PATH`, `REFLEET_KIRO_PATH`                      | —                          | Pin an explicit executable instead of looking it up on `PATH`   |
| `REFLEET_AUTO_UPDATE`                                           | `npm` when npm-installed, else `off` | `npm`: reinstall and restart when behind; `exit`: only restart; `off`: stay put |

GitLab access needs no configuration here: before each job the runner fetches the organisation's
GitLab URL and token from Refleet (set up once in Settings → Connect GitLab) and passes the token
per git invocation, never writing it into the cached checkout.

## What a job reports

The prompt Refleet builds for every job ends with an instruction to close the response with one
JSON object, and the runner reads that object rather than the prose around it:

- **qualification** — `{"score": 1-5, "reasoning": "…"}`. The score goes back as-is; Refleet
  treats 4 and 5 as qualified. A response without a valid score is reported as a failed run (so
  it can be retried), never as "not qualified".
- **change** — `{"summary": "…", "commit": {"subject": "…", "body": "…"}, "mergeRequest":
  {"title": "…", "description": "…"}}`. The runner commits whatever the agent changed,
  force-pushes it to `refleet/change-<target id>` and opens a merge request with the `refleet`
  label attached in code. The agent only proposes the wording: a commit subject that is blank,
  multi-line or longer than 72 characters is dropped and the shift title is used instead; a
  missing merge request title falls back to the commit subject and a missing description to the
  summary — which is what every change carried before the agent had a say. Running the same
  target again updates that merge request instead of opening another one. The branch, the merge
  request URL and its IID travel in the same report as the outcome.

## Docker

The same loop ships as a container image with both agents pre-installed —
`ghcr.io/refleet-it/refleet-runner`. See the
[runner installation guide](https://github.com/refleet-it/refleet/blob/main/docs/runner/installation.md).

## License

MIT
