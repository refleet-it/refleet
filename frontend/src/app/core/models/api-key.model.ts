export interface ApiKey {
  id: string;
  name: string;
  prefix: string;
  createdAt: string;
  lastUsedAt: string | null;
  revokedAt: string | null;
}

export interface CreatedApiKey {
  id: string;
  name: string;
  prefix: string;
  token: string;
  createdAt: string;
}
