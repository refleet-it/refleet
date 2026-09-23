# API

Everything is served under `/api` and speaks JSON. An interactive reference generated from the
code lives at `/api/doc` in development; this page covers the parts that reference cannot tell
you — how you authenticate, and how the resources relate.

## Authenticating

Two credentials are accepted, both as `Authorization: Bearer <token>`:

- **A JWT**, for people. Short-lived — an hour — and obtained by signing in.
- **An API key**, for runners and other machines. Long-lived, created through the API and
  revocable.

The firewall tries the API key authenticator first, then the JWT one, so a request carries only
one of them.

### Signing in

```http
POST /api/identity/login
{ "email": "…", "password": "…" }
```

The response body carries the JWT. The same request also sets two httponly cookies, which is what
the frontend actually uses:

| Cookie          | Path             | Lifetime |
| --------------- | ---------------- | -------- |
| `access_token`  | `/api`           | 1 hour   |
| `refresh_token` | `/api/identity`  | 7 days   |

`POST /api/identity/refresh` rotates the refresh token and issues a new access token; a refresh
token that has already been used is rejected. `POST /api/identity/logout` clears both.

### API keys

```http
POST /api/identity/api-keys      # create — returns the token once
GET  /api/identity/api-keys      # list — never returns the tokens again
DELETE /api/identity/api-keys/{apiKeyId}
```

Creating one requires a signed-in session; using one does not. This is how a runner authenticates.

### CLI login

`refleet login` never sees a password. It follows the OAuth device-flow shape:

```http
POST /api/identity/cli-authorizations              # public — the CLI starts, gets a URL + device secret
GET  /api/identity/cli-authorizations/{userCode}   # signed in — what the approval page shows
POST /api/identity/cli-authorizations/{userCode}/approve
POST /api/identity/cli-authorizations/{userCode}/deny
POST /api/identity/cli-authorizations/claim        # public — the CLI polls with its device secret
```

The URL the CLI prints carries only the user code; the device secret stays in the CLI and is
stored hashed. `claim` answers `pending`, `denied`, `expired`, or `approved` together with a
freshly minted API key — once; the authorization is deleted right after, and unfinished ones
expire after ten minutes. See [installing a runner](../runner/installation.md).

### Open without a token

`/api/health`, `/api/metrics`, `/api/client-errors`, `/api/doc`, the GitLab webhook, invitation
acceptance, the two CLI-login endpoints above, and the sign-in, registration, password-reset and
email-verification endpoints under `/api/identity`. Everything else under `/api` requires a fully authenticated request.

## The resources

**Identity** — `/api/identity/*` for sign-in, registration, password reset, email verification,
API keys and impersonation; `/api/account/me` for the current account.

**Organizations** — `/api/organizations` and, beneath it, `employees` (including ownership
transfer) and `invitations` (send, list, cancel, accept). `/api/organizations/me` returns the
caller's organisation, or null if they have none.

**GitLab connection** — `/api/gitlab/connection` connects (with a pasted access token), syncs
and disconnects an organisation's GitLab account; `/api/gitlab/connection/oauth/start` and
`/oauth/complete` do the same through gitlab.com's OAuth authorization-code flow, the default in
the UI. `/api/webhooks/gitlab/{organizationId}` receives merge request events.

**Projects** — `/api/projects` lists and registers the repositories an organisation works on.

**Qualifications** — `/api/qualifications` decides which projects a change applies to: create,
start, cancel, archive, and inspect `targets` individually, with an override per target. The
list shows live qualifications; `?archived=true` shows the archived ones instead.

**Shifts** — `/api/shifts` applies a change across the qualified projects: create, define the
change, start it, cancel it, archive it, and report a merge request per target. The list splits
the same way: `?archived=true` for the archive.

**Playbooks** — `/api/playbooks` lists, creates, updates and deletes the organisation's reusable
prompt fragments, alongside the read-only built-in ones (`builtin:<name>`); `/api/playbooks/compose`
turns a selection into text. `/api/shifts/prompt-preview` and `/api/qualifications/prompt-preview`
render the full prompt a job would carry for given fields.

**Runners** — `/api/runners` lists, inspects and archives runners and their jobs.
`/api/runner/heartbeat`, `/api/runner/jobs/claim` and `/api/runner/jobs/{jobId}/report` are the
endpoints a runner itself calls, along with `/api/runner/gitlab-credentials`, which hands out a
currently valid GitLab access token (refreshed first when an OAuth one is about to expire) and
answers only to API-key callers — a browser session gets 403.

**Files** — `/api/files` lists, `/api/files/upload` accepts an upload, `/api/files/{fileId}`
deletes, and `/api/storage/{bucket}/{path}` proxies a download with an ownership check.

**Notifications** — `/api/notifications/preferences` reads and updates per-account notification
preferences.

## Errors

Failures come back as JSON with the relevant status.

| Status | When |
| ------ | ---- |
| `401`  | no bearer token, or one that is not valid |
| `404`  | the resource does not exist, or the caller may not see it |
| `409`  | the request conflicts with the current state of the resource |
| `422`  | the payload failed validation |

Note that a rejected sign-in is a `422`, not a `401`: the credentials are part of the payload, so
failing to match them is a validation failure rather than a missing authentication.
