import { provideServerRendering, withRoutes } from '@angular/ssr';
import { ApplicationConfig } from '@angular/core';
import { provideNoopAnimations } from '@angular/platform-browser/animations';
import { serverRoutes } from './app.server.routes';

export const serverConfig: ApplicationConfig = {
  providers: [provideServerRendering(withRoutes(serverRoutes)), provideNoopAnimations()],
};
