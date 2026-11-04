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

## Three kinds of job

**Qualification** decides whether a change applies to a project at all. It is a read-only
question and must not modify the repository — the answer is a score from 1 to 5, and Refleet
counts 4 and 5 as qualified.

**Change** does the actual work and produces edits, which become a merge request — the runner
commits, pushes and opens (or refreshes) the merge request itself, labelled `refleet`, and reports
its URL together with the outcome.

**Pipeline fix** looks at a failed pipeline on a merge request Refleet opened. It is queued by the
API, not by anyone waiting on a runner, once a poll has found the pipeline red — see
[Fixing a failed pipeline](#fixing-a-failed-pipeline).

Every kind has the agent close its answer with one JSON object (`{"score": …, "reasoning": …}`,
`{"summary": …}` or `{"causedByChange": …, "summary": …}`) that the runner parses; the prose around
it is never interpreted.

The distinction is passed through to the engine, because they warrant different settings: a
qualification run can use a cheaper model and a narrower set of tools than a run that rewrites
code — the `CLAUDE_QUALIFICATION_*` variables as against the plain `CLAUDE_*` ones.

Any job is a **single prompt run to completion**. There is no conversation, no resuming a
session and no follow-up turn — the runner claims, runs once, reports.

## Fixing a failed pipeline

Watching a pipeline takes no agent and no runner: the API polls GitLab every few minutes, and a
runner only gets involved once a pipeline has failed.

The runner then reads the failed jobs and their logs from GitLab itself, checks out the merge
request's branch and asks the agent whether the change caused the failure. If it did, the agent's
edits go on as a new commit. If it did not, nothing is pushed and the merge request gets a note
saying why. A new commit means a new pipeline, which is watched in turn, up to three attempts.
A job that fails uses up an attempt too and the pipeline is sent again, because a failed job says
nothing about the pipeline: GitLab may have rejected the push over its commit message, for
example. Without a usable commit subject from the agent, the fix reuses the one already on the
branch, which is known to satisfy the repository's rules.

Refleet stops touching a merge request as soon as a person does. The runner reports the commit it
pushed, and any other head means someone else has moved the branch. The head is checked again
before the agent starts and before pushing, and a fix is never force-pushed, so a commit that
slips in between makes the push fail rather than disappear.

Two things to know before turning it on:

- **Logs go to the agent's provider.** The tail of each failed job's log, up to 20,000 characters
  for at most five jobs, is part of the prompt. GitLab only masks variables marked as masked, so
  anything else a job prints goes along with it. Logs are not stored in Refleet.
- **Logs are untrusted input.** Anyone able to push to a project can influence what its pipeline
  prints. The prompt marks logs as data and says not to follow instructions in them, which lowers
  the risk of prompt injection but does not remove it, so review these commits like any others.

Only runners that ask for `pipeline_fix` are offered these jobs, and an older runner never does.
Until one of yours is updated, the job waits and the target shows **Fixing pipeline**.

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
