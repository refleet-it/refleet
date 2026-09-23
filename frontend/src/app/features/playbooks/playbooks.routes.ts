import { Routes } from '@angular/router';

export const playbooksRoutes: Routes = [
  {
    path: '',
    title: 'Playbooks',
    loadComponent: () =>
      import('./pages/playbooks-page/playbooks-page.component').then(m => m.PlaybooksPageComponent),
  },
  {
    path: 'new',
    title: 'New playbook',
    loadComponent: () =>
      import('./pages/playbook-edit-page/playbook-edit-page.component').then(
        m => m.PlaybookEditPageComponent
      ),
  },
  {
    path: ':id',
    title: 'Playbook',
    loadComponent: () =>
      import('./pages/playbook-edit-page/playbook-edit-page.component').then(
        m => m.PlaybookEditPageComponent
      ),
  },
];
