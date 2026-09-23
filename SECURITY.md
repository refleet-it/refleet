# Security

## Reporting a vulnerability

Email **security@refleet.it**. Please do not open a public issue, and please do not test
against the hosted instance at refleet.it.

Tell us what you found, how to reproduce it, and what an attacker gets. You will get an
acknowledgement within a few working days and an honest answer about the timeline — this is
a small project, and pretending otherwise would waste your time.

If you would like credit in the release notes that fix it, say so.

## What is in scope

The code in this repository: the API, the frontend, the runner, and the self-hosting
configuration under `deploy/` and `compose.selfhost.yml`.

Worth knowing before you report:

- **A runner executes a coding agent against a checked-out repository.** That is what it is
  for. Code execution *by the agent, inside the runner's workspace* is the feature, not a
  vulnerability. Escaping that workspace, reaching the host, or reading credentials the
  runner holds — those are vulnerabilities.
- **Registration is closed by default** (`ALLOW_SIGNUP`). An instance whose operator opened
  it and was then flooded with accounts is a configuration choice, not a finding.
- **Fixtures seed known accounts with known passwords.** They exist only in the local
  development environment and are documented as such.

## What a self-hosted instance holds

So you know what is worth protecting: GitLab access tokens, encrypted at rest with
`GITLAB_TOKEN_ENCRYPTION_KEY`; the JWT signing key; and the API keys runners authenticate
with. Agent API keys are never stored by the server — they live only on the machines running
runners.
