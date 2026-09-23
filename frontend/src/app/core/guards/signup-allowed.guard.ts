import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map, Observable } from 'rxjs';
import { InstanceConfigService } from '../services/instance-config.service';

const AUTH_ROUTE = '/auth';

/**
 * Keeps the registration form off instances that do not accept registrations — the backend
 * refuses the request anyway, and a form that always fails reads as a broken instance.
 */
export const SignupAllowedGuard: CanActivateFn = (): Observable<boolean> => {
  const instance = inject(InstanceConfigService);
  const router = inject(Router);

  return instance.allowsSignup().pipe(
    map(allowed => {
      if (!allowed) {
        void router.navigate([AUTH_ROUTE]);
      }

      return allowed;
    })
  );
};
