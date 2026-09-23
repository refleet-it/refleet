import { HttpInterceptorFn } from '@angular/common/http';
import { inject, PLATFORM_ID, REQUEST } from '@angular/core';
import { isPlatformServer } from '@angular/common';
import { BACKEND_API_URL } from '../tokens/backend-api-url.token';
import { environment } from '../../../environments/environment';

export const ssrUrlRewriterInterceptor: HttpInterceptorFn = (req, next) => {
  const platformId = inject(PLATFORM_ID);
  const internalApiUrl = inject(BACKEND_API_URL, { optional: true });

  if (!isPlatformServer(platformId) || !internalApiUrl || !req.url.startsWith(environment.apiUrl)) {
    return next(req);
  }

  const rewrittenUrl = internalApiUrl + req.url.slice(environment.apiUrl.length);
  let cloneOptions: Parameters<typeof req.clone>[0] = { url: rewrittenUrl };

  const incomingRequest = inject(REQUEST, { optional: true });
  if (incomingRequest && req.url.includes('/api/')) {
    const cookieHeader =
      incomingRequest.headers.get?.('cookie') ??
      (incomingRequest.headers as unknown as Record<string, string>)['cookie'] ??
      '';
    const accessToken = parseCookieValue(cookieHeader, 'access_token');
    if (accessToken && !req.headers.has('Authorization')) {
      cloneOptions = {
        ...cloneOptions,
        setHeaders: { Authorization: `Bearer ${accessToken}` },
      };
    }
  }

  return next(req.clone(cloneOptions));
};

function parseCookieValue(cookieHeader: string, name: string): string | null {
  const match = cookieHeader.match(new RegExp(`(?:^|;\\s*)${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}
