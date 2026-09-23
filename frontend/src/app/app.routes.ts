import { Routes } from '@angular/router';
import { BaseLayoutComponent } from './core/layouts/base-layout/base-layout.component';
import { DashboardLayoutComponent } from './core/layouts/dashboard-layout/dashboard-layout.component';
import {
  adminMenu,
  dashboardMenu,
} from './core/layouts/dashboard-layout/consts/dashboard-menu.const';
import { AuthGuard } from './core/guards/auth.guard';
import { GuestGuard } from './core/guards/guest.guard';
import { OrganizationRequiredGuard } from './core/guards/organization-required.guard';
import { RoleGuard } from './core/guards/role.guard';
import { RootRedirectGuard } from './core/guards/root-redirect.guard';

import { authRoutes } from './features/auth/auth.routes';
import { errorRoutes } from './features/error/error.routes';

export const routes: Routes = [
  {
    path: 'auth',
    loadComponent: () =>
      import('./core/layouts/auth-layout/auth-layout.component').then(m => m.AuthLayoutComponent),
    canActivate: [GuestGuard],
    children: [...authRoutes],
  },
  {
    // Nothing renders here. The marketing site is a separate deployment; this route only
    // decides where an arriving visitor belongs — see RootRedirectGuard.
    path: '',
    pathMatch: 'full',
    canActivate: [RootRedirectGuard],
    children: [],
  },
  {
    path: 'error',
    children: [...errorRoutes],
  },
  {
    // Public: the documentation is the same content GitLab renders from docs/, and readers
    // should reach it without an account.
    path: 'docs',
    component: BaseLayoutComponent,
    loadChildren: () => import('./features/docs/docs.routes').then(m => m.docsRoutes),
  },
  {
    path: 'dashboard',
    component: DashboardLayoutComponent,
    data: { menuItems: dashboardMenu },
    canActivate: [AuthGuard, OrganizationRequiredGuard],
    loadChildren: () =>
      import('./features/dashboard/dashboard.routes').then(m => m.dashboardRoutes),
  },
  {
    // `refleet login` sends the terminal user here; the CLI is polling for the answer
    path: 'cli',
    canActivate: [AuthGuard],
    loadChildren: () => import('./features/cli/cli.routes').then(m => m.cliRoutes),
  },
  {
    path: 'organization',
    canActivate: [AuthGuard],
    loadChildren: () =>
      import('./features/organization/organization.routes').then(m => m.organizationRoutes),
  },
  {
    path: 'admin',
    component: DashboardLayoutComponent,
    data: { role: 'administrator', menuItems: adminMenu },
    canActivate: [AuthGuard, RoleGuard],
    loadChildren: () => import('./features/admin/admin.routes').then(m => m.adminRoutes),
  },
  {
    path: '**',
    redirectTo: 'error/404',
  },
];
