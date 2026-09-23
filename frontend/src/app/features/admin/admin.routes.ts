import { Routes } from '@angular/router';

export const adminRoutes: Routes = [
  {
    path: '',
    title: 'Admin panel',
    loadComponent: () =>
      import('./pages/admin-main-page/admin-main-page.component').then(
        m => m.AdminMainPageComponent
      ),
  },
];
