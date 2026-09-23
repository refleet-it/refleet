---
name: Git conventions
kind: rule
appliesTo: change
default: true
description: How the commit and merge request the runner opens for each change should read.
---
When proposing the commit and merge request wording:

- Commit subject: Conventional Commits (`feat`, `fix`, `chore`, `refactor`, `docs`, `ci`, `test`), imperative mood, English, at most 72 characters. Use the top-level module or directory you touched as the scope, e.g. `fix(billing): …`; omit the scope when the change spans the whole repository.
- Commit body: only when the subject cannot carry the "why"; one short paragraph, no bullet list of files.
- Merge request title: identical to the commit subject.
- Merge request description: what changed and why in two or three sentences, then a `## Testing` section saying what you ran (or that nothing could be run and why).
- If the repository has its own commit or merge request conventions (a `CLAUDE.md`, `CONTRIBUTING.md`, commitlint config, merge request template), those win over the above.
