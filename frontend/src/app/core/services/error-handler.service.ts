import { Injectable, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { ToastService } from '../../shared/services/toast.service';
import { ApiError, ErrorTranslation, ValidationError } from '../models/error.model';

const API_ERROR_MESSAGES: ErrorTranslation = {
  account_not_active: {
    title: 'Account inactive',
    message: 'Your account is not active.',
    action: 'Contact the administrator',
  },
  bad_request: {
    title: 'Bad request',
    message: 'The request contains invalid data.',
    action: 'Check the data and try again',
  },
  conflict: {
    title: 'Data conflict',
    message: 'The provided data is already in use. Please try using different values.',
    action: 'Change data and try again',
  },
  email_already_used: {
    title: 'Email already in use',
    message:
      'This email address is already registered in the system. If this is your account, please log in or reset your password.',
    action: 'Use a different email or log in',
  },
  email_not_verified: {
    title: 'Account not verified',
    message:
      'Your email address has not been confirmed yet. Click the activation link we sent to your inbox.',
    action: 'Check your email (including the spam folder)',
  },
  forbidden: {
    title: 'Access denied',
    message: 'You do not have permission to perform this operation.',
    action: 'Contact the administrator',
  },
  internal_error: {
    title: 'Server error',
    message: 'An unexpected error occurred. Please try again later.',
    action: 'Refresh the page',
  },
  invalid_credentials: {
    title: 'Invalid credentials',
    message: 'Email or password is incorrect.',
    action: 'Check your data and try again',
  },
  invalid_current_password: {
    title: 'Incorrect password',
    message: 'Your current password is incorrect.',
    action: 'Check your current password and try again',
  },
  not_found: {
    title: 'Not found',
    message: 'The requested resource was not found.',
    action: 'Check the address',
  },
  unknown_error: {
    title: 'Unknown error',
    message: 'An unexpected problem occurred. Please contact the administrator.',
    action: 'Try again',
  },
  unprocessable_entity: {
    title: 'Processing error',
    message: 'The request cannot be processed. Please check the correctness of the data.',
    action: 'Check data and try again',
  },
  validation_failed: {
    title: 'Validation errors',
    message: 'Please check the correctness of the entered data.',
    action: 'Fix errors in the form',
  },
};

const VALIDATION_FIELD_LABELS: Record<string, string> = {
  description: 'Description',
  email: 'Email',
  name: 'Name',
  type: 'Type',
  url: 'URL',
};

@Injectable({
  providedIn: 'root',
})
export class ErrorHandlerService {
  private static readonly ERROR_TOAST_LIFE_MS = 8000;
  private static readonly VALIDATION_TOAST_LIFE_MS = 6000;
  private static readonly UNKNOWN_ERROR_KEY = 'unknown_error';

  private toastService = inject(ToastService);
  private platformId = inject(PLATFORM_ID);

  handleError(error: ApiError, context?: string): void {
    if (!isPlatformBrowser(this.platformId)) return;
    if (context) {
      console.error(`API error at ${context}:`, error);
    }
    const errorKey = error.error || ErrorHandlerService.UNKNOWN_ERROR_KEY;

    const title = this.getMessageOrFallback(errorKey, 'title');
    const message = this.getMessageOrFallback(errorKey, 'message');

    this.toastService.add({
      severity: 'error',
      summary: title,
      detail: message,
      life: ErrorHandlerService.ERROR_TOAST_LIFE_MS,
    });
  }

  handleValidationErrors(errors: Record<string, ValidationError | ValidationError[]>): void {
    if (!isPlatformBrowser(this.platformId)) return;

    Object.entries(errors).forEach(([field, fieldErrors]) => {
      const errorsArray: ValidationError[] = Array.isArray(fieldErrors)
        ? fieldErrors
        : [fieldErrors as ValidationError];

      errorsArray.forEach(error => {
        this.toastService.add({
          severity: 'error',
          summary: `Error: ${this.getFieldLabel(field)}`,
          detail: error.message,
          life: ErrorHandlerService.VALIDATION_TOAST_LIFE_MS,
        });
      });
    });
  }

  private getMessageOrFallback(errorKey: string, field: 'title' | 'message' | 'action'): string {
    const entry = API_ERROR_MESSAGES[errorKey];
    const value = entry?.[field];
    return value ?? API_ERROR_MESSAGES[ErrorHandlerService.UNKNOWN_ERROR_KEY][field] ?? '';
  }

  private getFieldLabel(field: string): string {
    return VALIDATION_FIELD_LABELS[field] ?? field;
  }

  clearErrors(): void {
    this.toastService.clear();
  }
}
