import { Routes } from '@angular/router';

export const dashboardRoutes: Routes = [
  {
    path: '',
    title: 'Dashboard',
    loadComponent: () =>
      import('./pages/dashboard-page/dashboard-page.component').then(m => m.DashboardPageComponent),
  },
  {
    path: 'projects',
    title: 'Projects',
    loadComponent: () =>
      import('./pages/projects-page/projects-page.component').then(m => m.ProjectsPageComponent),
  },
  {
    path: 'qualifications',
    loadChildren: () =>
      import('../qualifications/qualifications.routes').then(m => m.qualificationsRoutes),
  },
  {
    path: 'shifts',
    loadChildren: () => import('../shifts/shifts.routes').then(m => m.shiftsRoutes),
  },
  {
    path: 'playbooks',
    loadChildren: () => import('../playbooks/playbooks.routes').then(m => m.playbooksRoutes),
  },
  {
    path: 'runners',
    title: 'Runners',
    loadComponent: () =>
      import('./pages/runners-page/runners-page.component').then(m => m.RunnersPageComponent),
  },
  {
    path: 'runners/archive',
    title: 'Archived runners',
    data: { archived: true },
    loadComponent: () =>
      import('./pages/runners-page/runners-page.component').then(m => m.RunnersPageComponent),
  },
  {
    path: 'runners/:id',
    title: 'Runner',
    loadComponent: () =>
      import('./pages/runner-detail-page/runner-detail-page.component').then(
        m => m.RunnerDetailPageComponent
      ),
  },
  {
    path: 'team',
    loadChildren: () => import('../team/team.routes').then(m => m.teamRoutes),
  },
  {
    path: 'settings',
    title: 'Settings',
    loadComponent: () =>
      import('../organization-settings/pages/organization-settings-page/organization-settings-page.component').then(
        m => m.OrganizationSettingsPageComponent
      ),
  },
  {
    // Must match GitLabOAuthClient::CALLBACK_PATH on the backend — it is the redirect URI
    // registered with the gitlab.com OAuth application.
    path: 'settings/gitlab/callback',
    title: 'Connecting GitLab',
    loadComponent: () =>
      import('../organization-settings/pages/gitlab-oauth-callback-page/gitlab-oauth-callback-page.component').then(
        m => m.GitLabOAuthCallbackPageComponent
      ),
  },
];
