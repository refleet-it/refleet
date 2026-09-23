# Using Refleet

Refleet takes one change and carries it across every repository it applies to, ending in a merge
request per project. This page describes that path from the product's side. For how a worker
actually executes the work, see [how a runner works](../runner/how-it-works.md).

## Connect a GitLab group

Under **Settings**, give the group path and **Continue with GitLab**: gitlab.com asks you to
authorize Refleet, and sends you back connected. Nothing to copy — GitLab hands Refleet a
short-lived access token and a refresh token, both stored encrypted; the access token is refreshed
on demand and only ever handed to your runners, and you can revoke the whole thing from your
GitLab profile under *Applications* at any time. Reconnecting (or connecting from another owner
account) replaces the tokens; the group's webhook secret is kept so hooks you already configured
keep working.

For self-managed GitLab, use **Self-hosted GitLab? Use an access token instead**: create a group
access token with the Developer role and the `api` scope and paste it together with your instance
URL. That token is stored encrypted and used as-is until you reconnect, so rotate it in GitLab and
reconnect when it expires.

Either way, Refleet syncs that group's projects into your fleet, and **Sync now** re-runs the sync
at any time to pick up projects that were added or removed.

The synced projects are what everything below operates on; you can see them under **Projects**.

Every sync also makes sure the group has a `refleet` label (black, "Merge requests opened by
Refleet"): every merge request a runner opens carries it, so Refleet's work is one filter away
from your own. Managing group labels needs at least the Reporter role, so a token below that
leaves the sync marked as failed with that reason, even though the projects themselves synced.

## Two steps, deliberately separate

Deciding *where* a change belongs and *making* the change are different questions, so they are
different objects:

| | Answers | Produces |
| --- | --- | --- |
| **Qualification** | Which projects does this change apply to? | A verdict per project |
| **Shift** | Apply the change to those projects | A merge request per project |

Keeping them apart means you can look at the verdict before anything writes to a repository, and
reuse one qualification for any number of shifts.

A shift does not require a qualification — see [picking the targets](#picking-the-targets).

## Qualification

A qualification is a question asked of every project in the fleet, answered one project at a time.
The question is a prompt: an agent (Claude Code or Kiro) reads the repository and grades how well
it matches, on a scale from 1 (clearly does not) to 5 (clearly does).

It moves through **Draft** (created, not started), **Running** (runners are working through the
projects) and **Completed**. **Cancelled** is the alternative ending if you stop it.

Each project it checks is a target, and each target ends up **Qualified** (a score of 4 or 5) or
**Not Qualified** (1 to 3) — or **Failed** if the check itself could not be completed, in which
case it can be retried. The score is shown next to the verdict, so a borderline 3 and a confident
5 read differently. A verdict is not final: you can override any target by hand, which is the
escape hatch for the cases the criteria get wrong.

## Shift

A shift applies a change and opens the merge requests, each labelled `refleet`.

### Picking the targets

Three ways, and the difference matters:

1. **From a qualification** — every project that qualification currently marks as Qualified.
2. **From a qualification, with an explicit selection** — the projects you name, whatever their
   verdict. This overrides the qualification at the moment the shift is created.
3. **No qualification at all** — any projects you pick from the fleet, skipping qualification.

### Defining the change

A prompt describing what to do, run by Claude Code or Kiro against each project. The change stays
editable while the shift is a draft.

Two fields make up the prompt: **Rules**, standing instructions the agent follows on every
project, and the **AI Prompt**, the change itself. Both can be typed by hand or composed from
[playbooks](#playbooks). Whatever is in those fields is what runs — **Show full prompt** renders
the exact text the agent receives, including the framing Refleet adds around it (do not commit,
answer with a JSON summary and the proposed commit/merge request wording).

The agent proposes the commit subject and body and the merge request title and description; the
runner performs the commit, push and merge request itself, in code. A proposal that is missing or
malformed (a blank or multi-line subject, or one over 72 characters) falls back to the shift title
and the agent's summary.

### Trying it on one project first

Before starting the whole shift, run the change on a single target (the play button next to it).
The shift stays a draft, the other targets stay untouched, and you get a real merge request to
judge the prompt by. Adjust the prompt and run that target again — the same merge request is
updated rather than a second one opened — until it does what you meant, then start the shift.

Any settled target can be run again the same way, at any point: after a failure, to redo an open
or rejected merge request with a better prompt, or when the agent found nothing to change. Only a
merged target is final.

### What happens then

The shift goes **Draft** → **Applying Change** → **Completed**, or **Cancelled** if you stop it.
Per project it runs further than you might expect: Refleet does not stop at opening the merge
request, it keeps watching it.

| Target status | Meaning |
| --- | --- |
| Pending | Waiting for the change to start |
| Changing | A runner is applying the change |
| Change Failed | Applying the change failed |
| No Changes | The agent found nothing to change, so no merge request was opened |
| MR Open | Applied, and a merge request is open for review |
| Completed | The merge request was merged — this project is done |
| MR Closed | The merge request was closed without merging |
| Cancelled | The target will not be processed |

A shift is complete once every target has settled, whichever way it settled. "Completed" is
therefore a statement about the work being finished, not about every merge request being merged.

## Playbooks

A playbook is a reusable piece of a prompt, kept per organization under **Playbooks**. It comes in
two kinds:

- A **rule** is a standing instruction — commit conventions, "touch only what the task needs",
  "base the score on files you actually opened". Any number of rules go in front of a change or
  criteria, concatenated under their own headings. A rule marked **default** is pre-selected
  whenever a new shift or qualification is defined; it can always be unticked.
- A **task** is the change or criteria itself, with `{{parameters}}` filled in when it is picked:
  "Upgrade `{{package}}` to `{{version}}`" is one task, not one per package. A task may name a
  preferred agent and model, which the shift starts from.

Each playbook applies to shifts, qualifications or both. Refleet ships a few **built-in** ones —
among them *Git conventions*, on by default for every change — which are read-only; **Duplicate**
turns any of them into an editable copy for the organization.

Playbooks only ever produce text. Picking them fills the Rules and AI Prompt fields of the shift or
qualification, and those fields stay yours: edit them freely, and later changes to the playbook
selection stop overwriting a field you edited by hand until you ask to replace it. A shift stores
the text it was defined with, so editing or deleting a playbook afterwards changes nothing that is
already running — the names of the playbooks it was composed from are kept only as a label.

Rules are for how the organization wants the fleet worked on, not for how a given project is
built: knowledge about one repository belongs in that repository's own `CLAUDE.md`, steering files
or contributing guide, which the agent reads anyway.

## Runners do the work

Nothing above happens inside Refleet itself. Runners are workers you run yourself; they claim jobs,
check out the repository, do the work and report back. **Runners** shows the runner fleet and
whether each one is working, idle or offline.

You need at least one runner before a qualification or a shift can make progress — see
[installing a runner](../runner/installation.md).

## Team and roles

Invite teammates by email from **Team**. Within an organization an account is either the **owner**
or a **member**: the owner can invite and remove teammates and can hand ownership to someone else.
