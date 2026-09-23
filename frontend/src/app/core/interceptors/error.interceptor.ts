import {
  HttpInterceptorFn,
  HttpRequest,
  HttpHandlerFn,
  HttpErrorResponse,
  HttpStatusCode,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { ErrorHandlerService } from '../services/error-handler.service';
import { ApiError, ValidationError } from '../models/error.model';

const SILENT_404_PATTERNS: string[] = [];

const SILENT_ALL_ERRORS_PATTERNS = ['/files/upload', '/resend', '/identity/refresh'];

const DEFAULT_ERROR_MESSAGE = 'An error occurred';

const DEFAULT_UNEXPECTED_ERROR_MESSAGE = 'An unexpected error occurred';

const normalizeCode = (status: number): string => {
  if (status === HttpStatusCode.Unauthorized || status === HttpStatusCode.Forbidden)
    return 'forbidden';
  if (status === HttpStatusCode.NotFound) return 'not_found';
  if (status === HttpStatusCode.BadRequest) return 'validation_failed';
  if (status === HttpStatusCode.Conflict) return 'conflict';
  if (status === HttpStatusCode.UnprocessableEntity) return 'unprocessable_entity';
  if (status >= HttpStatusCode.InternalServerError) return 'internal_error';
  return 'unknown_error';
};

export const ErrorInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn
) => {
  const errorHandler = inject(ErrorHandlerService);

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // Skip 401 errors - handled by AuthInterceptor (token refresh or redirect to /auth)
      if (error.status === HttpStatusCode.Unauthorized) {
        return throwError(() => error);
      }

      // Skip error handling for expected 404s (e.g., no active menu)
      if (
        error.status === HttpStatusCode.NotFound &&
        SILENT_404_PATTERNS.some(pattern => req.url.includes(pattern))
      ) {
        return throwError(() => error);
      }

      // Skip error handling for endpoints that manage their own error toasts
      if (SILENT_ALL_ERRORS_PATTERNS.some(pattern => req.url.includes(pattern))) {
        return throwError(() => error);
      }

      // A failed GET is a screen that could not load, and every screen says so in place —
      // app-list-error on the lists, loadError on the detail pages, a *Failed branch in the
      // pickers. Toasting as well reported one outage twice, in two different wordings. A toast
      // now means an action the user took did not go through.
      if ('GET' === req.method) {
        return throwError(() => error);
      }

      if (error.error && typeof error.error === 'object') {
        const rawCode = (error.error as { error?: unknown }).error;
        const errorCode = typeof rawCode === 'string' ? rawCode.toLowerCase() : null;

        const apiError: ApiError = {
          error: errorCode || normalizeCode(error.status),
          message:
            ((error.error as { message?: unknown }).message as string) || DEFAULT_ERROR_MESSAGE,
          details: ((error.error as { details?: unknown }).details ?? {}) as Record<
            string,
            unknown
          >,
          field: ((error.error as { field?: unknown }).field as string | null) || null,
        };

        if (apiError.error === 'validation_failed' && apiError.details) {
          errorHandler.handleValidationErrors(
            apiError.details as Record<string, ValidationError | ValidationError[]>
          );
        } else {
          errorHandler.handleError(apiError, req.url);
        }
      } else {
        errorHandler.handleError(
          {
            error: normalizeCode(error.status),
            message: error.message || DEFAULT_UNEXPECTED_ERROR_MESSAGE,
            details: {} as Record<string, unknown>,
            field: null,
          },
          req.url
        );
      }

      return throwError(() => error);
    })
  );
};
