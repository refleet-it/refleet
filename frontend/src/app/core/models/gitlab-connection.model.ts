import { StatusBadgeVariant } from './status-badge.model';

export type GitLabSyncStatus = 'never_synced' | 'success' | 'failed';

export const GITLAB_SYNC_STATUS_LABELS: Record<GitLabSyncStatus, string> = {
  never_synced: 'Never synced',
  success: 'Synced',
  failed: 'Sync failed',
};

export const GITLAB_SYNC_STATUS_BADGE_VARIANTS: Record<GitLabSyncStatus, StatusBadgeVariant> = {
  never_synced: 'outline',
  success: 'success',
  failed: 'destructive',
};

export type GitLabAuthMethod = 'oauth' | 'access_token';

export interface GitLabConnectionOverview {
  connected: boolean;
  /** Whether this server is registered as an OAuth application on gitlab.com. */
  oauthAvailable: boolean;
  authMethod?: GitLabAuthMethod;
  baseUrl?: string;
  groupPath?: string;
  groupName?: string;
  connectedAt?: string;
  lastSyncedAt?: string | null;
  lastSyncStatus?: GitLabSyncStatus;
  lastSyncError?: string | null;
  lastSyncProjectCount?: number | null;
}

export interface ConnectGitLabPayload {
  groupPath: string;
  accessToken: string;
  baseUrl?: string;
}

export interface StartGitLabOAuthPayload {
  groupPath: string;
}

export interface StartGitLabOAuthResult {
  url: string;
}

export interface CompleteGitLabOAuthPayload {
  code: string;
  state: string;
}

export interface ConnectedGitLabConnection {
  connected: true;
  baseUrl: string;
  groupPath: string;
  groupName: string;
  connectedAt: string;
}

export interface SyncGitLabResult {
  syncedCount: number;
  failedCount: number;
  archivedCount: number;
  lastSyncedAt: string;
}
