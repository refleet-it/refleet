import {
  HttpInterceptorFn,
  HttpRequest,
  HttpHandlerFn,
  HttpEvent,
  HttpResponse,
  HttpErrorResponse,
} from '@angular/common/http';
import { inject, PLATFORM_ID } from '@angular/core';
import { isPlatformServer } from '@angular/common';
import { Observable, throwError, of } from 'rxjs';
import { tap, catchError } from 'rxjs/operators';

interface CacheEntry {
  etag: string;
  response: HttpResponse<unknown>;
}

const etagCache = new Map<string, CacheEntry>();

export const EtagInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn
): Observable<HttpEvent<unknown>> => {
  // etagCache is module state, so on the server it outlives a render and is shared by every
  // visitor the process serves: a 304 answered against someone else's ETag would replay their
  // body. A render fetches each URL once, and provideClientHydration already carries those
  // responses to the browser, so there is nothing to win here anyway.
  if (isPlatformServer(inject(PLATFORM_ID))) {
    return next(req);
  }

  if (req.method !== 'GET' || !req.url.includes('/api/')) {
    return next(req);
  }

  const cacheKey = req.urlWithParams;
  const cached = etagCache.get(cacheKey);

  const modifiedReq = cached ? req.clone({ setHeaders: { 'If-None-Match': cached.etag } }) : req;

  return next(modifiedReq).pipe(
    tap(event => {
      if (event instanceof HttpResponse && event.status === 200) {
        const etag = event.headers.get('ETag');
        if (etag) {
          etagCache.set(cacheKey, { etag, response: event });
        }
      }
    }),
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 304) {
        const entry = etagCache.get(cacheKey);
        if (entry) {
          return of(entry.response);
        }
      }
      return throwError(() => error);
    })
  );
};
