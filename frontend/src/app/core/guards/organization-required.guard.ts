import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { OrganizationService } from '../services/organization.service';

/**
 * Guards routes that require the current account to already belong to an
 * organization. Accounts without one are redirected to the creation screen.
 */
export const OrganizationRequiredGuard: CanActivateFn = (): Observable<boolean | UrlTree> => {
  const organizationService = inject(OrganizationService);
  const router = inject(Router);

  return organizationService.getMyOrganization().pipe(
    map(overview => {
      if (null === overview.organization) {
        return router.createUrlTree(['/organization/create']);
      }

      return true;
    }),
    catchError(() => of(true))
  );
};
