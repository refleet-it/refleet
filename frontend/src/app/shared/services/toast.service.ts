import { Injectable } from '@angular/core';
import { toast } from '@spartan-ng/brain/sonner';

export type ToastSeverity = 'success' | 'info' | 'warn' | 'error';

export interface ToastMessage {
  severity: ToastSeverity;
  summary?: string;
  detail?: string;
  /** Duration in milliseconds. */
  life?: number;
  data?: unknown;
}

/**
 * Thin wrapper around the spartan/ui Sonner `toast()` function, exposing an API
 * compatible with PrimeNG's `MessageService` (`add()` / `clear()`) to minimize
 * changes in call sites during the PrimeNG -> Spartan migration.
 */
@Injectable({
  providedIn: 'root',
})
export class ToastService {
  add(message: ToastMessage): void {
    const { severity, summary, detail, life } = message;
    const options = { description: detail, duration: life };

    switch (severity) {
      case 'success':
        toast.success(summary ?? '', options);
        break;
      case 'error':
        toast.error(summary ?? '', options);
        break;
      case 'warn':
        toast.warning(summary ?? '', options);
        break;
      case 'info':
      default:
        toast.info(summary ?? '', options);
        break;
    }
  }

  clear(): void {
    toast.dismiss();
  }
}
