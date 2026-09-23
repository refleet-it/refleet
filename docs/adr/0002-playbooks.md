# ADR 2 — Playbooks compose prompts; the answer contract stays in the framing

**Status:** accepted, 2026-09-22

## Context

Every shift and qualification carried one free-form prompt. With dozens of projects in a fleet,
three things kept being retyped: standing instructions that should hold for every change (how
commits and merge requests are worded, what not to touch), the change itself when it recurs with
one detail different (upgrade *this* package to *that* version), and the fact that the runner
committed with the shift title as the only commit message, whatever the repository's conventions.

The obvious alternatives were a "skills" tab mirroring Claude Code's skills at the organization
level, and a hard commit-message template applied by the runner. The first competes with the
repository's own `CLAUDE.md`/skills — two sources of truth for how a project works, resolved
differently by Claude Code and Kiro. The second cannot honour per-repository conventions the agent
already sees in the checkout.

The product's non-negotiable is that a person writes the prompt and a person reviews the merge
request. Anything that composes prompts must keep the composed text visible and editable.

## Decision

1. **One primitive, two roles.** A playbook is a named prompt fragment owned by the organization:
   a *task* (the change or criteria, with `{{parameters}}`, optionally a preferred engine/model,
   one per prompt) or a *rule* (a standing instruction, any number per prompt, optionally on by
   default). Each applies to shifts, qualifications or both.
2. **Playbooks produce text, and only text.** Composing them (`POST /api/playbooks/compose`)
   returns rules text, rendered task text and the sources used; the frontend pastes those into
   the shift's or qualification's editable fields. A Shift/Qualification stores the composed text
   (and the source names, purely for display) — never a reference resolved at run time. The
   Playbook context therefore has no port into Shift or Qualification, and editing or deleting a
   playbook cannot affect a running shift.
3. **The answer contract lives in the framing, not in a playbook.** `ShiftJobPayloadFactory` and
   `QualificationJobPayloadFactory` wrap the user's text and ask for one closing JSON object. For
   changes that object now carries `commit` and `mergeRequest` proposals besides the summary. A
   *Git conventions* rule tells the agent *how* to word them; the framing says *that* they exist
   and the runner validates and applies them, falling back to the shift title. The agent never
   commits, pushes or opens the merge request itself.
4. **Built-in playbooks ship with Refleet** as markdown files with YAML front matter under
   `backend/app/playbook/resources/playbooks/`, read-only and listed in every organization with
   ids prefixed `builtin:`; *Git conventions* is a default change rule. "Duplicate" makes an
   editable organization copy. Customising a built-in in place (shadowing by key) is deferred.
5. **The full prompt is always one click away.** `POST /api/shifts/prompt-preview` and
   `POST /api/qualifications/prompt-preview` render, through the same factories the jobs use,
   exactly what the agent will receive for the fields as they are.

## Consequences

- Playbook is its own bounded context (`backend/app/playbook`, schema `playbook`, container
  `backend-playbook`, route `/api/playbooks*`), following ADR 1. It has no queued messages.
- Shift and Qualification each gained a nullable rules column and a JSON sources column; the
  prompt column is unchanged, so existing records behave exactly as before.
- The runner's change result parser accepts the new fields but does not require them, so a
  runner and backend of different versions keep working — an older runner ignores the proposal
  and commits with the shift title as it always did.
- Deferred, deliberately: playbooks synced from a git repository (the markdown format is chosen
  so that this needs no new format), recurring shifts built on a playbook plus a schedule, and
  any automatic start that bypasses the trial run and a person pressing *start*.
