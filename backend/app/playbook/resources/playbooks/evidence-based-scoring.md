---
name: Evidence-based scoring
kind: rule
appliesTo: qualification
default: true
description: Make every score traceable to files the agent actually opened.
---
Base the score only on evidence from this checkout:

- Open the files that can settle the question (dependency manifests, lock files, CI configuration, source) before deciding; never infer from the project's name, path or README claims alone.
- In the reasoning, name the files that decided the score (e.g. `composer.json`, `.gitlab-ci.yml`).
- If the evidence is contradictory or incomplete, say which part is missing and score 3 rather than guessing high or low.
