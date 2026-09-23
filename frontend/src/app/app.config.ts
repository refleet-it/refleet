import {
  ApplicationConfig,
  ErrorHandler,
  provideZoneChangeDetection,
  LOCALE_ID,
} from '@angular/core';
import {
  provideRouter,
  withPreloading,
  NoPreloading,
  withInMemoryScrolling,
  TitleStrategy,
} from '@angular/router';
import { provideHttpClient, withInterceptors, withFetch } from '@angular/common/http';
import { registerLocaleData } from '@angular/common';
import localeEn from '@angular/common/locales/en';

import { APP_INITIALIZER } from '@angular/core';
import { routes } from './app.routes';
import { AuthService } from './core/services/auth.service';
import { GlobalErrorHandler } from './core/services/global-error-handler.service';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideClientHydration } from '@angular/platform-browser';
import { provideSpartanHlm } from '@spartan-ng/helm/utils';
import { AuthInterceptor } from './core/interceptors/auth.interceptor';
import { EtagInterceptor } from './core/interceptors/etag.interceptor';
import { ErrorInterceptor } from './core/interceptors/error.interceptor';
import { ssrUrlRewriterInterceptor } from './core/interceptors/ssr-url-rewriter.interceptor';
import { AppTitleStrategy } from './core/title-strategy';

registerLocaleData(localeEn);

export const appConfig: ApplicationConfig = {
  providers: [
    { provide: ErrorHandler, useClass: GlobalErrorHandler },
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(
      routes,
      withPreloading(NoPreloading),
      withInMemoryScrolling({ scrollPositionRestoration: 'top' })
    ),
    {
      provide: APP_INITIALIZER,
      useFactory: (auth: AuthService) => () => auth.initializeAuth(),
      deps: [AuthService],
      multi: true,
    },
    provideHttpClient(
      withFetch(),
      withInterceptors([
        AuthInterceptor,
        ErrorInterceptor,
        EtagInterceptor,
        ssrUrlRewriterInterceptor,
      ])
    ),
    provideClientHydration(),
    provideAnimations(),
    provideSpartanHlm(),
    { provide: TitleStrategy, useClass: AppTitleStrategy },
    { provide: LOCALE_ID, useValue: 'en' },
  ],
};
