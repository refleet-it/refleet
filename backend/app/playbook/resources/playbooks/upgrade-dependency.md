---
name: Upgrade a dependency
kind: task
appliesTo: change
description: Bump one package to a target version and make the project pass with it.
parameters:
  - name: package
    label: Package name
    required: true
  - name: version
    label: Target version constraint
    required: true
---
Upgrade the dependency `{{package}}` to `{{version}}`.

1. Find where it is declared (`composer.json`, `package.json`, `pyproject.toml`, `go.mod`, `Gemfile` or similar) and change the constraint there. Update the lock file with the package manager's own upgrade command for that one package; do not upgrade anything else.
2. Read the package's changelog or upgrade notes for the versions between the old and the new one and apply the code changes they require (renamed APIs, removed options, new configuration keys).
3. Run the project's test suite or the closest thing it has (linter, type checker, build) and fix what the upgrade broke.
4. If the project does not use `{{package}}`, change nothing and say so in the summary.
