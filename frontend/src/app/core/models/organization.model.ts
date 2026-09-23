import { PageInfo } from './pagination.model';

export interface Organization {
  id: string;
  name: string;
}

export interface Employee {
  accountId: string;
  email: string;
  role: 'owner' | 'user';
  joinedAt: string | null;
}

export interface EmployeeList {
  employees: Employee[];
  pagination: PageInfo;
}

export interface OrganizationOverview {
  organization: Organization | null;
  role?: 'owner' | 'user';
}

export interface CreatedOrganization {
  id: string;
  name: string;
  role: 'owner' | 'user';
}

export interface SentInvitation {
  id: string;
  email: string;
  expiresAt: string;
}

export interface PendingInvitation {
  id: string;
  email: string;
  createdAt: string;
  expiresAt: string;
}

export interface PendingInvitationList {
  invitations: PendingInvitation[];
  pagination: PageInfo;
}
