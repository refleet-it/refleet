import { Routes } from '@angular/router';

export const qualificationsRoutes: Routes = [
  {
    path: '',
    title: 'Qualifications',
    loadComponent: () =>
      import('./pages/qualifications-page/qualifications-page.component').then(
        m => m.QualificationsPageComponent
      ),
  },
  {
    path: 'new',
    title: 'New qualification',
    loadComponent: () =>
      import('./pages/qualification-new-page/qualification-new-page.component').then(
        m => m.QualificationNewPageComponent
      ),
  },
  {
    path: 'archive',
    title: 'Archived qualifications',
    data: { archived: true },
    loadComponent: () =>
      import('./pages/qualifications-page/qualifications-page.component').then(
        m => m.QualificationsPageComponent
      ),
  },
  {
    path: ':id',
    title: 'Qualification',
    loadComponent: () =>
      import('./pages/qualification-detail-page/qualification-detail-page.component').then(
        m => m.QualificationDetailPageComponent
      ),
  },
];
