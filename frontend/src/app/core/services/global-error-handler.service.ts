import { ErrorHandler, Injectable, inject, PLATFORM_ID } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { environment } from '../../../environments/environment';

/**
 * Catches uncaught exceptions (template rendering bugs, RxJS errors with no error
 * callback, plain JS TypeErrors, ...) that would otherwise only ever show up in a
 * user's own browser console. Reports them to /api/client-errors so they land in the
 * same Loki/Grafana pipeline as backend errors - production JS crashes are invisible
 * to the team without this.
 *
 * HttpErrorResponse is deliberately NOT reported here: it's already shown to the user
 * via ErrorInterceptor/ErrorHandlerService and already logged server-side (it's a
 * response the backend itself produced), so re-reporting it would just be noise.
 */
@Injectable()
export class GlobalErrorHandler implements ErrorHandler {
  private static readonly REPORT_ENDPOINT = `${environment.apiUrl}/client-errors`;

  private platformId = inject(PLATFORM_ID);

  handleError(error: unknown): void {
    console.error(error);

    if (!isPlatformBrowser(this.platformId)) return;
    if (error instanceof HttpErrorResponse) return;

    this.report(error);
  }

  private report(error: unknown): void {
    const body = JSON.stringify({
      message: this.extractMessage(error),
      stack: this.extractStack(error),
      url: window.location.href,
    });

    try {
      const beacon = navigator.sendBeacon?.(
        GlobalErrorHandler.REPORT_ENDPOINT,
        new Blob([body], { type: 'application/json' })
      );
      if (beacon) return;
    } catch {
      // fall through to fetch
    }

    fetch(GlobalErrorHandler.REPORT_ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body,
      keepalive: true,
    }).catch(() => {
      // Reporting the error must never itself throw and re-enter the error handler.
    });
  }

  private extractMessage(error: unknown): string {
    if (error instanceof Error) return error.message || error.name;
    if (typeof error === 'string') return error;
    try {
      return JSON.stringify(error).slice(0, 2000);
    } catch {
      return 'Unknown client error';
    }
  }

  private extractStack(error: unknown): string | undefined {
    return error instanceof Error ? error.stack : undefined;
  }
}
