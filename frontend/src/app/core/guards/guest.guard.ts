import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Prevents authenticated users from accessing guest-only routes (e.g. login, register).
 * Redirects them to the dashboard instead.
 */
export const GuestGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  const token = authService.getJwtToken();

  if (!token || authService.isTokenExpired()) {
    return true;
  }

  router.navigate(['/']);
  return false;
};
