import {
  HttpErrorResponse,
  HttpEvent,
  HttpHandlerFn,
  HttpRequest,
  HttpResponse,
} from '@angular/common/http';
import { PLATFORM_ID, REQUEST } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Observable, of, throwError } from 'rxjs';
import { describe, expect, it, vi } from 'vitest';
import { AuthService } from '../services/auth.service';
import { ErrorHandlerService } from '../services/error-handler.service';
import { AuthInterceptor } from './auth.interceptor';
import { ErrorInterceptor } from './error.interceptor';
import { EtagInterceptor } from './etag.interceptor';
import { ssrUrlRewriterInterceptor } from './ssr-url-rewriter.interceptor';
import { BACKEND_API_URL } from '../tokens/backend-api-url.token';

const API = 'http://localhost/api';

function get(url: string): HttpRequest<unknown> {
  return new HttpRequest('GET', url);
}

function post(url: string): HttpRequest<unknown> {
  return new HttpRequest('POST', url, {});
}

/** Captures what the interceptor chain passed on, so assertions can look at the outgoing request. */
function handlerReturning(
  response: Observable<HttpEvent<unknown>>
): HttpHandlerFn & { seen: HttpRequest<unknown>[] } {
  const seen: HttpRequest<unknown>[] = [];
  const handler = ((req: HttpRequest<unknown>) => {
    seen.push(req);
    return response;
  }) as HttpHandlerFn & { seen: HttpRequest<unknown>[] };
  handler.seen = seen;

  return handler;
}

function withAuth(auth: Partial<AuthService>) {
  TestBed.configureTestingModule({ providers: [{ provide: AuthService, useValue: auth }] });
}

describe('AuthInterceptor', () => {
  it('attaches the token to API calls', () => {
    withAuth({ getJwtToken: () => 'token-123' });
    const next = handlerReturning(of(new HttpResponse()));

    TestBed.runInInjectionContext(() =>
      AuthInterceptor(get(`${API}/organizations/me`), next)
    ).subscribe();

    expect(next.seen[0].headers.get('Authorization')).toBe('Bearer token-123');
    expect(next.seen[0].withCredentials).toBe(true);
  });

  // Sending the old token to these would be at best pointless and at worst confusing: they are the
  // endpoints that issue tokens in the first place.
  it.each(['/identity/login', '/identity/register', '/identity/refresh', '/identity/logout'])(
    'leaves %s unauthenticated',
    path => {
      withAuth({ getJwtToken: () => 'token-123' });
      const next = handlerReturning(of(new HttpResponse()));

      TestBed.runInInjectionContext(() => AuthInterceptor(get(`${API}${path}`), next)).subscribe();

      expect(next.seen[0].headers.has('Authorization')).toBe(false);
    }
  );

  it('leaves non-API calls alone entirely', () => {
    withAuth({ getJwtToken: () => 'token-123' });
    const next = handlerReturning(of(new HttpResponse()));

    TestBed.runInInjectionContext(() =>
      AuthInterceptor(get('http://localhost/assets/docs/index.html'), next)
    ).subscribe();

    expect(next.seen[0].headers.has('Authorization')).toBe(false);
    expect(next.seen[0].withCredentials).toBe(false);
  });

  it('refreshes on a 401 and replays the request with the new token', () => {
    const refreshToken = vi.fn(() => of({ jwtToken: 'fresh' }));
    withAuth({ getJwtToken: () => 'stale', refreshToken } as Partial<AuthService>);

    let attempt = 0;
    const next = ((req: HttpRequest<unknown>) => {
      attempt += 1;
      if (1 === attempt) {
        return throwError(() => new HttpErrorResponse({ status: 401 }));
      }
      expect(req.headers.get('Authorization')).toBe('Bearer fresh');

      return of(new HttpResponse({ status: 200 }));
    }) as HttpHandlerFn;

    let status: number | undefined;
    TestBed.runInInjectionContext(() =>
      AuthInterceptor(get(`${API}/organizations/me`), next)
    ).subscribe(event => {
      status = (event as HttpResponse<unknown>).status;
    });

    expect(refreshToken).toHaveBeenCalled();
    expect(status).toBe(200);
  });

  it('does not try to refresh when the refresh call itself is rejected', () => {
    const refreshToken = vi.fn(() => of({ jwtToken: 'fresh' }));
    withAuth({ getJwtToken: () => 'stale', refreshToken } as Partial<AuthService>);
    const next = handlerReturning(throwError(() => new HttpErrorResponse({ status: 401 })));

    TestBed.runInInjectionContext(() =>
      AuthInterceptor(get(`${API}/identity/refresh`), next)
    ).subscribe({ error: () => undefined });

    expect(refreshToken).not.toHaveBeenCalled();
  });
});

describe('ErrorInterceptor', () => {
  function withHandler() {
    const handleError = vi.fn();
    const handleValidationErrors = vi.fn();
    TestBed.configureTestingModule({
      providers: [
        { provide: ErrorHandlerService, useValue: { handleError, handleValidationErrors } },
      ],
    });

    return { handleError, handleValidationErrors };
  }

  it('reports a server error and still rethrows it', () => {
    const { handleError } = withHandler();
    const next = handlerReturning(
      throwError(() => new HttpErrorResponse({ status: 500, error: { message: 'Boom' } }))
    );

    let rethrown: HttpErrorResponse | undefined;
    TestBed.runInInjectionContext(() => ErrorInterceptor(post(`${API}/shifts`), next)).subscribe({
      error: (error: HttpErrorResponse) => (rethrown = error),
    });

    expect(handleError).toHaveBeenCalledOnce();
    expect(handleError.mock.calls[0][0]).toMatchObject({
      error: 'internal_error',
      message: 'Boom',
    });
    expect(rethrown?.status).toBe(500);
  });

  it('routes field errors to the validation path instead of a toast', () => {
    const { handleError, handleValidationErrors } = withHandler();
    const next = handlerReturning(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 400,
            error: { error: 'validation_failed', details: { name: { message: 'Required' } } },
          })
      )
    );

    TestBed.runInInjectionContext(() => ErrorInterceptor(post(`${API}/shifts`), next)).subscribe({
      error: () => undefined,
    });

    expect(handleValidationErrors).toHaveBeenCalledOnce();
    expect(handleError).not.toHaveBeenCalled();
  });

  // 401 belongs to AuthInterceptor, which refreshes the token; a toast here would fire during a
  // recovery the user never needed to know about.
  it('stays out of the way on a 401', () => {
    const { handleError } = withHandler();
    const next = handlerReturning(throwError(() => new HttpErrorResponse({ status: 401 })));

    TestBed.runInInjectionContext(() => ErrorInterceptor(post(`${API}/shifts`), next)).subscribe({
      error: () => undefined,
    });

    expect(handleError).not.toHaveBeenCalled();
  });

  // The screen that issued the GET already reports the failure itself; a toast on top said the
  // same thing twice, in different words.
  it('leaves a failed GET to the screen that asked for it', () => {
    const { handleError } = withHandler();
    const next = handlerReturning(throwError(() => new HttpErrorResponse({ status: 500 })));

    let rethrown: HttpErrorResponse | undefined;
    TestBed.runInInjectionContext(() => ErrorInterceptor(get(`${API}/shifts`), next)).subscribe({
      error: (error: HttpErrorResponse) => (rethrown = error),
    });

    expect(handleError).not.toHaveBeenCalled();
    expect(rethrown?.status).toBe(500);
  });

  it.each(['/files/upload', '/resend', '/identity/refresh'])(
    'leaves %s to report its own failures',
    path => {
      const { handleError } = withHandler();
      const next = handlerReturning(throwError(() => new HttpErrorResponse({ status: 500 })));

      TestBed.runInInjectionContext(() => ErrorInterceptor(post(`${API}${path}`), next)).subscribe({
        error: () => undefined,
      });

      expect(handleError).not.toHaveBeenCalled();
    }
  );
});

describe('EtagInterceptor', () => {
  it('replays the cached body when the server answers 304', () => {
    const url = `${API}/etag-probe-${Math.random()}`;
    const cached = new HttpResponse({ status: 200, body: { name: 'first' } });

    const first = handlerReturning(
      of(
        new HttpResponse({
          status: 200,
          body: { name: 'first' },
          headers: cached.headers.set('ETag', '"v1"'),
        })
      )
    );
    TestBed.runInInjectionContext(() => EtagInterceptor(get(url), first)).subscribe();

    const second = handlerReturning(throwError(() => new HttpErrorResponse({ status: 304 })));
    let body: unknown;
    TestBed.runInInjectionContext(() => EtagInterceptor(get(url), second)).subscribe(event => {
      body = (event as HttpResponse<unknown>).body;
    });

    expect(second.seen[0].headers.get('If-None-Match')).toBe('"v1"');
    expect(body).toEqual({ name: 'first' });
  });

  it('ignores anything that is not a GET on the API', () => {
    const next = handlerReturning(of(new HttpResponse()));

    TestBed.runInInjectionContext(() =>
      EtagInterceptor(new HttpRequest('POST', `${API}/shifts`, {}), next)
    ).subscribe();

    expect(next.seen[0].headers.has('If-None-Match')).toBe(false);
  });

  // The cache is module state and the server process serves every visitor, so caching there would
  // let one person's response be replayed to the next one asking for the same URL.
  it('caches nothing on the server, even for an entry it already holds', () => {
    const url = `${API}/etag-probe-${Math.random()}`;

    const seeded = handlerReturning(
      of(
        new HttpResponse({
          status: 200,
          body: { name: 'someone else' },
          headers: new HttpResponse().headers.set('ETag', '"v1"'),
        })
      )
    );
    TestBed.runInInjectionContext(() => EtagInterceptor(get(url), seeded)).subscribe();

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({ providers: [{ provide: PLATFORM_ID, useValue: 'server' }] });

    const onServer = handlerReturning(throwError(() => new HttpErrorResponse({ status: 304 })));
    let failed = false;
    TestBed.runInInjectionContext(() => EtagInterceptor(get(url), onServer)).subscribe({
      error: () => (failed = true),
    });

    expect(onServer.seen[0].headers.has('If-None-Match')).toBe(false);
    expect(failed).toBe(true);
  });
});

/**
 * Server-side rendering talks to the backend over an internal address the browser cannot reach, and
 * carries the visitor's session cookie across as a bearer token. Both halves fail quietly: rewrite
 * on the browser and requests go to a host that does not resolve; forward the cookie too widely and
 * a visitor's token leaves with a request that was never meant to carry it.
 */
describe('ssrUrlRewriterInterceptor', () => {
  const INTERNAL = 'http://backend/api';

  function configure(platform: 'server' | 'browser', internalUrl?: string, cookie?: string) {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        { provide: PLATFORM_ID, useValue: platform },
        { provide: BACKEND_API_URL, useValue: internalUrl },
        ...(cookie === undefined
          ? []
          : [{ provide: REQUEST, useValue: { headers: new Headers({ cookie }) } }]),
      ],
    });
  }

  function run(req: HttpRequest<unknown>) {
    const next = handlerReturning(of(new HttpResponse()));
    TestBed.runInInjectionContext(() => ssrUrlRewriterInterceptor(req, next)).subscribe();

    return next.seen[0];
  }

  it('rewrites the public API address to the internal one, keeping the path', () => {
    configure('server', INTERNAL);

    expect(run(get(`${API}/shifts?page=2`)).url).toBe(`${INTERNAL}/shifts?page=2`);
  });

  // The browser has no route to the internal host, so rewriting there would break every request
  // the server-rendered page makes once it is handed over.
  it('leaves the address alone in the browser even when an internal one is configured', () => {
    configure('browser', INTERNAL);

    expect(run(get(`${API}/shifts`)).url).toBe(`${API}/shifts`);
  });

  it('leaves the address alone on the server when no internal one is configured', () => {
    configure('server', undefined);

    expect(run(get(`${API}/shifts`)).url).toBe(`${API}/shifts`);
  });

  it('carries the session cookie across as a bearer token while rendering', () => {
    configure('server', INTERNAL, 'other=1; access_token=token-123; another=2');

    expect(run(get(`${API}/shifts`)).headers.get('Authorization')).toBe('Bearer token-123');
  });

  // A request that already carries an identity was given one deliberately; replacing it with
  // whatever the cookie holds would silently act as the wrong account.
  it('never replaces an Authorization header the request already carries', () => {
    configure('server', INTERNAL, 'access_token=cookie-token');
    const req = new HttpRequest('GET', `${API}/shifts`, {
      headers: new HttpRequest('GET', '/').headers.set('Authorization', 'Bearer explicit-token'),
    });

    expect(run(req).headers.get('Authorization')).toBe('Bearer explicit-token');
  });

  it('does not touch a request aimed anywhere but the API', () => {
    configure('server', INTERNAL, 'access_token=token-123');
    const outgoing = run(get('http://localhost/assets/docs/index.html'));

    expect(outgoing.url).toBe('http://localhost/assets/docs/index.html');
    expect(outgoing.headers.has('Authorization')).toBe(false);
  });
});
