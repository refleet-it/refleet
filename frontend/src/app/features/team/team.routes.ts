import { Routes } from '@angular/router';

export const teamRoutes: Routes = [
  {
    path: '',
    title: 'Team',
    loadComponent: () =>
      import('./pages/team-page/team-page.component').then(m => m.TeamPageComponent),
  },
  {
    path: 'new',
    title: 'Invite teammate',
    loadComponent: () =>
      import('./pages/team-new-page/team-new-page.component').then(m => m.TeamNewPageComponent),
  },
];
