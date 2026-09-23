import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Guards `/`, which renders nothing: the marketing site is a separate deployment. Guests are
 * sent to sign in, administrators to the admin panel, everyone else to their dashboard.
 */
export const RootRedirectGuard: CanActivateFn = (): UrlTree => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isAuthenticated()) {
    return router.createUrlTree(['/auth']);
  }

  if (authService.getCurrentUser()?.role === 'administrator') {
    return router.createUrlTree(['/admin']);
  }

  return router.createUrlTree(['/dashboard']);
};
