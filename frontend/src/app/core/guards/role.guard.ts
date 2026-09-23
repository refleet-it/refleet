import { inject } from '@angular/core';
import { ActivatedRouteSnapshot, CanActivateFn, Router } from '@angular/router';
import { catchError, filter, map, Observable, of, take, timeout } from 'rxjs';
import { AuthService, User } from '../services/auth.service';
import { AUTH_TIMEOUT_MS } from '../config/timings';

const AUTH_ROUTE = '/auth';

/**
 * Restricts a route to accounts whose `role` matches `route.data['role']`.
 * Mismatched roles are sent back to `/`, where RootRedirectGuard routes them
 * to wherever their own role belongs.
 */
export const RoleGuard: CanActivateFn = (
  route: ActivatedRouteSnapshot
): Observable<boolean> | boolean => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const currentUser = authService.getCurrentUser();
  if (currentUser) {
    return checkRole(currentUser, route, router);
  }

  return authService.currentUser$.pipe(
    filter((user): user is User => user !== null),
    take(1),
    timeout(AUTH_TIMEOUT_MS),
    map(user => checkRole(user, route, router)),
    catchError(() => {
      router.navigate([AUTH_ROUTE]);
      return of(false);
    })
  );
};

function checkRole(user: User, route: ActivatedRouteSnapshot, router: Router): boolean {
  const requiredRole = route.data['role'];
  if (requiredRole && user.role !== requiredRole) {
    router.navigate(['/']);
    return false;
  }
  return true;
}
