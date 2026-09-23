import {
  HttpInterceptorFn,
  HttpRequest,
  HttpHandlerFn,
  HttpErrorResponse,
  HttpEvent,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { Observable, throwError, switchMap, catchError } from 'rxjs';
import { AuthService } from '../services/auth.service';

export const AuthInterceptor: HttpInterceptorFn = (
  req: HttpRequest<unknown>,
  next: HttpHandlerFn
): Observable<HttpEvent<unknown>> => {
  const authService = inject(AuthService);

  if (req.url.includes('/api/')) {
    const token = authService.getJwtToken();
    const shouldAddAuth =
      token &&
      !req.url.includes('/identity/login') &&
      !req.url.includes('/identity/register') &&
      !req.url.includes('/identity/refresh') &&
      !req.url.includes('/identity/logout');

    req = req.clone({
      withCredentials: true,
      ...(shouldAddAuth ? { setHeaders: { Authorization: `Bearer ${token}` } } : {}),
    });
  }

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      if (
        error.status === 401 &&
        !req.url.includes('/identity/refresh') &&
        authService.getJwtToken()
      ) {
        return authService.refreshToken().pipe(
          switchMap(tokens => {
            const newReq = req.clone({
              withCredentials: true,
              setHeaders: {
                Authorization: `Bearer ${tokens.jwtToken}`,
              },
            });
            return next(newReq);
          }),
          catchError(refreshError => {
            return throwError(() => refreshError);
          })
        );
      }

      return throwError(() => error);
    })
  );
};
