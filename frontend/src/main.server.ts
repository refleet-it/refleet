import { bootstrapApplication, type BootstrapContext } from '@angular/platform-browser';
import { mergeApplicationConfig } from '@angular/core';
import { AppComponent } from './app/app.component';
import { appConfig } from './app/app.config';
import { serverConfig } from './app/app.config.server';

const serverAppConfig = mergeApplicationConfig(appConfig, serverConfig);
export default (context: BootstrapContext) =>
  bootstrapApplication(AppComponent, serverAppConfig, context);
