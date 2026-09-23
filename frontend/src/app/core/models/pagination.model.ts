export interface PageInfo {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  hasNextPage: boolean;
  hasPreviousPage: boolean;
}

export interface Page<T> {
  items: T[];
  pagination: PageInfo;
}
