import { Routes } from '@angular/router';

export const cliRoutes: Routes = [
  {
    path: 'authorize/:code',
    title: 'Authorize the Refleet CLI',
    loadComponent: () =>
      import('./pages/cli-authorize-page/cli-authorize-page.component').then(
        m => m.CliAuthorizePageComponent
      ),
  },
];
