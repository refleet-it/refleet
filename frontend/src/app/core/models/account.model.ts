export interface AccountListItem {
  id: string;
  email: string;
  role: 'user' | 'administrator';
  status: 'active' | 'inactive';
  createdAt: string;
  updatedAt: string;
}

export interface AccountListPage {
  data: AccountListItem[];
  pagination: {
    hasNextPage: boolean;
    nextCursor: string | null;
  };
}
