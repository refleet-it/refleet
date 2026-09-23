import { InjectionToken } from '@angular/core';

/**
 * Where the rendered documentation assets live. The browser fetches them relatively; the SSR
 * server has no notion of a relative URL, so it provides its own absolute origin instead.
 */
export const DOCS_ASSETS_BASE = new InjectionToken<string>('DOCS_ASSETS_BASE', {
  providedIn: 'root',
  factory: () => '/assets/docs',
});
