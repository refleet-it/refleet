import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Rendered on the server so the pages are readable without JavaScript and indexable.
  // Not prerendered: the content is fetched over HTTP from this same process, which needs
  // a running server — making it prerenderable means delivering the pages a different way.
  { path: 'docs', renderMode: RenderMode.Server },
  { path: 'docs/**', renderMode: RenderMode.Server },
  { path: '**', renderMode: RenderMode.Client },
];
