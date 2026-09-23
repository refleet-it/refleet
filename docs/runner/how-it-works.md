# How a runner works

A runner is a worker you run yourself. It asks the API for jobs, does them against a checked-out
repository and reports back. Nothing pushes work at it — it polls — so it needs no inbound
network access and you can run as many as you like.

For getting one running, see [installing a runner](installation.md).

## The loop

Two intervals, both configurable:

- every `HEARTBEAT_INTERVAL_SECONDS` (30 by default) it tells the API it is alive — and which
  version it runs; the answer says whether a newer one is published, or whether someone pressed
  **Update** on its page, and the runner stops between jobs for its supervisor to restart it
  (see [Updating](installation.md#updating))
- every `POLL_INTERVAL_SECONDS` (10 by default) it tries to claim a job

Claiming is how work is distributed: the API hands out one job at a time to whoever asks, so two
runners never take the same job and adding a runner just means work drains faster.

## Two kinds of job

**Qualification** decides whether a change applies to a project at all. It is a read-only
question and must not modify the repository — the answer is a score from 1 to 5, and Refleet
counts 4 and 5 as qualified.

**Change** does the actual work and produces edits, which become a merge request — the runner
commits, pushes and opens (or refreshes) the merge request itself, labelled `refleet`, and reports
its URL together with the outcome.

Either way the agent closes its answer with one JSON object (`{"score": …, "reasoning": …}` or
`{"summary": …}`) that the runner parses; the prose around it is never interpreted.

The distinction is passed through to the engine, because they warrant different settings: a
qualification run can use a cheaper model and a narrower set of tools than a run that rewrites
code — the `CLAUDE_QUALIFICATION_*` variables as against the plain `CLAUDE_*` ones.

Either way a job is a **single prompt run to completion**. There is no conversation, no resuming a
session and no follow-up turn — the runner claims, runs once, reports.

## Engines, and why one host can be two runners

The runner supports two engines:

| Engine  | Command    | Pin an explicit path with |
| ------- | ---------- | ------------------------- |
| `claude` | `claude`   | `REFLEET_CLAUDE_PATH`     |
| `kiro`   | `kiro-cli` | `REFLEET_KIRO_PATH`       |

At startup it resolves each command the way a shell would — used as-is if it contains a path
separator, otherwise looked up along `PATH` — and keeps the ones that are actually executable. If
neither is, it exits rather than sitting there claiming nothing.

Where it gets interesting: **it starts one loop per engine it found, and each registers as its own
runner**, named `<RUNNER_NAME>-<engine>` and advertising only that engine. A host with both
installed appears as two independent runners rather than one that quietly mixes them, and the API
only offers each of them jobs it can actually run. Each also reports its engine on every
heartbeat, so its detail page in the dashboard shows which agent it has access to.

If any one of those loops dies the whole process exits, so Docker's restart policy brings
everything back together. Running on with fewer engines than were detected would look healthy
while quietly doing less work.

Detecting `claude` without `ANTHROPIC_API_KEY` set is not fatal — it warns at startup, and
claude-engine jobs fail when they arrive.

## The workspace

Cloned repositories are cached under `WORKSPACE_CACHE_DIR` and trimmed once past
`WORKSPACE_CACHE_MAX_SIZE_MB`. It is only a cache: deleting it costs a re-clone and nothing else,
which is why the Docker instructions mount it as an ordinary volume without ceremony.

## Related

- [Installing and updating a runner](installation.md)
- [Authenticating against the API](../api/README.md) — how a runner's API key is created
