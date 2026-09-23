import { Routes } from '@angular/router';

export const shiftsRoutes: Routes = [
  {
    path: '',
    title: 'Shifts',
    loadComponent: () =>
      import('./pages/shifts-page/shifts-page.component').then(m => m.ShiftsPageComponent),
  },
  {
    path: 'new',
    title: 'New shift',
    loadComponent: () =>
      import('./pages/shift-new-page/shift-new-page.component').then(m => m.ShiftNewPageComponent),
  },
  {
    path: 'archive',
    title: 'Archived shifts',
    data: { archived: true },
    loadComponent: () =>
      import('./pages/shifts-page/shifts-page.component').then(m => m.ShiftsPageComponent),
  },
  {
    path: ':id',
    title: 'Shift',
    loadComponent: () =>
      import('./pages/shift-detail-page/shift-detail-page.component').then(
        m => m.ShiftDetailPageComponent
      ),
  },
];
