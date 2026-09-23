import { inject } from '@angular/core';
import { CanActivateFn, Router, RouterStateSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';

export const AuthGuard: CanActivateFn = (
  _route,
  state: RouterStateSnapshot
): Observable<boolean> => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isTokenExpired()) {
    return of(true);
  }

  // JWT missing or expired — try to refresh using HttpOnly cookie
  return authService.refreshToken().pipe(
    map(() => true),
    catchError(() => {
      // The login page sends the visitor back here once they have signed in
      router.navigate(['/auth'], { queryParams: { returnUrl: state.url } });
      return of(false);
    })
  );
};
