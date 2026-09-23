export interface ApiError {
  error: string;
  message: string;
  details: Record<string, unknown>;
  field?: string | null;
}

export interface ValidationError {
  field: string;
  message: string;
  code: string;
}

export type ErrorTranslation = Record<
  string,
  {
    title: string;
    message: string;
    action?: string;
  }
>;
