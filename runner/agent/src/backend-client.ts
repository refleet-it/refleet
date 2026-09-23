export type FetchFn = typeof fetch;

export class BackendRequestError extends Error {
  constructor(
    message: string,
    readonly status?: number,
  ) {
    super(message);
  }
}

function baseUrl(apiUrl: string): string {
  return apiUrl.replace(/\/+$/, '');
}

export interface StartedCliAuthorization {
  userCode: string;
  deviceSecret: string;
  verificationUrl: string;
  expiresAt: string;
  pollIntervalSeconds: number;
}

/**
 * POST /identity/cli-authorizations — opens a browser-approved login (OAuth device-flow
 * shape). Public: the CLI has nothing to authenticate with yet. The device secret is
 * the CLI's half; the URL carries only the user code the browser needs.
 */
export async function startCliAuthorization(
  apiUrl: string,
  runnerName: string,
  fetchFn: FetchFn = fetch,
): Promise<StartedCliAuthorization> {
  const response = await fetchFn(`${baseUrl(apiUrl)}/identity/cli-authorizations`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ runnerName }),
  });

  if (!response.ok) {
    throw new BackendRequestError(`could not start a login (status ${String(response.status)})`, response.status);
  }

  return (await response.json()) as StartedCliAuthorization;
}

export interface CreatedApiKey {
  id: string;
  name: string;
  prefix: string;
  token: string;
  createdAt: string;
}

export type ClaimedCliAuthorization =
  | { status: 'pending' | 'denied' | 'expired' }
  | { status: 'approved'; accountEmail: string; apiKey: CreatedApiKey };

/**
 * POST /identity/cli-authorizations/claim — one poll. The secret travels in the body so
 * it never lands in an access log. `approved` comes with the key exactly once; the
 * backend forgets the authorization right after, so a replay is a 404.
 */
export async function claimCliAuthorization(
  apiUrl: string,
  deviceSecret: string,
  apiKeyName: string,
  fetchFn: FetchFn = fetch,
): Promise<ClaimedCliAuthorization> {
  const response = await fetchFn(`${baseUrl(apiUrl)}/identity/cli-authorizations/claim`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ deviceSecret, apiKeyName }),
  });

  if (404 === response.status) {
    throw new BackendRequestError('this login request is no longer known to the server', response.status);
  }
  if (!response.ok) {
    throw new BackendRequestError(`polling the login failed (status ${String(response.status)})`, response.status);
  }

  return (await response.json()) as ClaimedCliAuthorization;
}

/**
 * DELETE /identity/api-keys/{id}. Accepts the API key itself as auth (Security
 * resolves ApiKeyAuthenticator and JwtAuthenticator to the same AccountUser), so
 * `logout` never needs to re-prompt for a password just to revoke its own key.
 * A 404 (already revoked/deleted) is treated as success so this stays idempotent.
 */
export async function revokeApiKey(
  apiUrl: string,
  apiKey: string,
  apiKeyId: string,
  fetchFn: FetchFn = fetch,
): Promise<void> {
  const response = await fetchFn(`${baseUrl(apiUrl)}/identity/api-keys/${apiKeyId}`, {
    method: 'DELETE',
    headers: { Authorization: `Bearer ${apiKey}` },
  });

  if (!response.ok && 404 !== response.status) {
    throw new BackendRequestError(`failed to revoke the API key (status ${String(response.status)})`, response.status);
  }
}

export interface ApiKeySummary {
  id: string;
  name: string;
  prefix: string;
  createdAt: string;
  lastUsedAt: string | null;
  revokedAt: string | null;
}

/** GET /identity/api-keys — used by `whoami` as a live validity/connectivity check. */
export async function listApiKeys(apiUrl: string, apiKey: string, fetchFn: FetchFn = fetch): Promise<ApiKeySummary[]> {
  const response = await fetchFn(`${baseUrl(apiUrl)}/identity/api-keys`, {
    headers: { Authorization: `Bearer ${apiKey}` },
  });

  if (!response.ok) {
    throw new BackendRequestError(`failed to list API keys (status ${String(response.status)})`, response.status);
  }

  const body = (await response.json()) as { apiKeys: ApiKeySummary[] };
  return body.apiKeys;
}
