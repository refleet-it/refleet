import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { OrganizationService } from '../services/organization.service';

/**
 * Guards the organization creation screen. Accounts that already belong to an
 * organization are sent to the dashboard instead.
 */
export const NoOrganizationGuard: CanActivateFn = (): Observable<boolean | UrlTree> => {
  const organizationService = inject(OrganizationService);
  const router = inject(Router);

  return organizationService.getMyOrganization().pipe(
    map(overview => {
      if (null !== overview.organization) {
        return router.createUrlTree(['/dashboard']);
      }

      return true;
    }),
    catchError(() => of(true))
  );
};
