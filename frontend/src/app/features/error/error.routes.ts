import { Routes } from '@angular/router';

export const errorRoutes: Routes = [
  {
    path: '404',
    title: 'Page not found',
    loadComponent: () =>
      import('./pages/not-found-page/not-found-page.component').then(m => m.NotFoundPageComponent),
  },
  {
    path: '500',
    title: 'Server error',
    loadComponent: () =>
      import('./pages/server-error-page/server-error-page.component').then(
        m => m.ServerErrorPageComponent
      ),
  },
  {
    path: 'server-error',
    title: 'Server error',
    loadComponent: () =>
      import('./pages/server-error-page/server-error-page.component').then(
        m => m.ServerErrorPageComponent
      ),
  },
  {
    path: 'not-found',
    title: 'Page not found',
    loadComponent: () =>
      import('./pages/not-found-page/not-found-page.component').then(m => m.NotFoundPageComponent),
  },
];
