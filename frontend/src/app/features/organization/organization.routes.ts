import { Routes } from '@angular/router';
import { NoOrganizationGuard } from '../../core/guards/no-organization.guard';

export const organizationRoutes: Routes = [
  {
    path: 'create',
    title: 'Create organization',
    canActivate: [NoOrganizationGuard],
    loadComponent: () =>
      import('./pages/create-organization-page/create-organization-page.component').then(
        m => m.CreateOrganizationPageComponent
      ),
  },
];
