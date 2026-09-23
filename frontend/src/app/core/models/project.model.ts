import { PageInfo } from './pagination.model';

export interface ProjectOverview {
  id: string;
  name: string;
  externalId: string;
  path: string;
  webUrl: string | null;
  defaultBranch: string | null;
  description: string | null;
  createdAt: string;
  lastSyncedAt: string | null;
}

export interface ProjectList {
  projects: ProjectOverview[];
  pagination: PageInfo;
}
