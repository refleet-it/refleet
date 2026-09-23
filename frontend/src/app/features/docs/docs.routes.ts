import { Routes } from '@angular/router';

/**
 * A slug is a path — `runner/installation` — so the wildcard catches nested pages and the
 * component reads the segments back out of the route.
 */
export const docsRoutes: Routes = [
  {
    path: '**',
    loadComponent: () =>
      import('./pages/docs-page/docs-page.component').then(m => m.DocsPageComponent),
  },
];
