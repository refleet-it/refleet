---
name: Find a dependency
kind: task
appliesTo: qualification
description: Does this project depend on a given package, directly or transitively?
parameters:
  - name: package
    label: Package name
    required: true
  - name: versionRange
    label: Version range of interest (optional)
    required: false
---
Does this repository depend on `{{package}}`? Version range of interest: {{versionRange}} (ignore this line when empty).

Check the dependency manifests and lock files for every package manager the project uses. A direct dependency in the manifest scores 5; a transitive one visible only in the lock file scores 4; a mention only in documentation, comments or vendored copies scores 2; no trace scores 1.
