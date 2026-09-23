import { Routes } from '@angular/router';

import { SignupAllowedGuard } from '../../core/guards/signup-allowed.guard';

export const authRoutes: Routes = [
  {
    path: '',
    title: 'Log in',
    loadComponent: () =>
      import('./pages/auth-main-page/auth-main-page.component').then(m => m.AuthMainPageComponent),
  },
  {
    path: 'register',
    title: 'Register',
    canActivate: [SignupAllowedGuard],
    loadComponent: () =>
      import('./pages/register-page/register-page.component').then(m => m.RegisterPageComponent),
  },
  {
    path: 'registration-pending',
    title: 'Check your inbox',
    loadComponent: () =>
      import('./pages/registration-pending-page/registration-pending-page.component').then(
        m => m.RegistrationPendingPageComponent
      ),
  },
  {
    path: 'forgot-password',
    title: 'Reset password',
    loadComponent: () =>
      import('./pages/forgot-password-page/forgot-password-page.component').then(
        m => m.ForgotPasswordPageComponent
      ),
  },
  {
    path: 'reset-password',
    title: 'Set a new password',
    loadComponent: () =>
      import('./pages/reset-password-page/reset-password-page.component').then(
        m => m.ResetPasswordPageComponent
      ),
  },
  {
    path: 'verify-email',
    title: 'Verify email',
    loadComponent: () =>
      import('./pages/verify-email-page/verify-email-page.component').then(
        m => m.VerifyEmailPageComponent
      ),
  },
];
