---
name: Minimal diff
kind: rule
appliesTo: change
default: false
description: Keep the change to exactly what the task needs, so the merge request stays reviewable.
---
Change only what the task requires:

- No drive-by refactors, renames or formatting-only edits outside the lines you need to touch.
- Do not reformat files with a code formatter unless the task is about formatting.
- Do not add, remove or upgrade dependencies the task does not name.
- Leave tests alone unless the change breaks them or the task asks for tests; when you do touch tests, keep them behaviour-preserving.
- If a larger cleanup would help, mention it in the summary instead of doing it.
