import { APP_BASE_HREF } from '@angular/common';
import { CommonEngine } from '@angular/ssr/node';
import express from 'express';
import { fileURLToPath } from 'node:url';
import { dirname, join, resolve } from 'node:path';
import bootstrap from './main.server';
import { BACKEND_API_URL } from './app/core/tokens/backend-api-url.token';
import { DOCS_ASSETS_BASE } from './app/core/tokens/docs-assets-base.token';
import { REQUEST } from '@angular/core';

const serverDistFolder = dirname(fileURLToPath(import.meta.url));
const browserDistFolder = resolve(serverDistFolder, '../browser');
const BUILD_HASH = process.env['BUILD_HASH'] || `dev-${Date.now()}`;

const app = express();
// Behind the production gateway the Host header is the public domain; the engine refuses
// (with a 500, not a fallback) any host not listed here. compose.prod.yml sets the list.
const commonEngine = new CommonEngine({
  allowedHosts: (process.env['NG_ALLOWED_HOSTS'] ?? 'localhost,127.0.0.1')
    .split(',')
    .map(host => host.trim())
    .filter(Boolean),
});

app.use(
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false,
  })
);

// The sign-in surfaces and the documentation are public and indexable; everything else is a
// private, per-user context and must not be indexed. `/` is not listed: it renders nothing
// and redirects, and the page a visitor should find instead is the marketing site.
// With a relative environment.apiUrl there is nothing for HttpClient to resolve against on
// the server, so ssrUrlRewriterInterceptor needs this to point at the API from inside the
// network. Missing, every server-rendered page would fail on its first request with a message
// about absolute URLs that says nothing about the cause.
const backendApiUrl = process.env['BACKEND_API_URL'];
if (!backendApiUrl) {
  throw new Error('BACKEND_API_URL is required: the server renders pages by calling the API.');
}

const PUBLIC_PATH_PREFIXES = ['/auth', '/error', '/docs'];
const PUBLIC_EXACT_PATHS = new Set(['/docs']);

app.use((req, res, next) => {
  const isPublicPath =
    PUBLIC_EXACT_PATHS.has(req.path) ||
    PUBLIC_PATH_PREFIXES.some(p => req.path.startsWith(p + '/'));
  if (!isPublicPath) {
    res.setHeader('X-Robots-Tag', 'noindex, nofollow');
  }
  next();
});

app.use((req, res, next) => {
  const { protocol, originalUrl, headers } = req;
  commonEngine
    .render({
      bootstrap,
      documentFilePath: join(browserDistFolder, 'index.csr.html'),
      url: `${protocol}://${headers.host}${originalUrl}`,
      publicPath: browserDistFolder,
      providers: [
        { provide: APP_BASE_HREF, useValue: req.baseUrl },
        {
          provide: BACKEND_API_URL,
          useValue: backendApiUrl,
        },
        { provide: REQUEST, useValue: req },
        {
          // Relative URLs mean nothing to HttpClient on the server, and the documentation
          // assets are served by this very process — hand it its own origin.
          provide: DOCS_ASSETS_BASE,
          useValue: `${protocol}://${headers.host}/assets/docs`,
        },
      ],
    })
    .then(html => {
      res.setHeader('Cache-Control', 'no-cache');
      res.setHeader('ETag', `"${BUILD_HASH}"`);
      res.send(html);
    })
    .catch(err => next(err));
});

// Must be registered last and keep all 4 params (err, req, res, next) - that's how
// Express recognizes error-handling middleware. Without this, an SSR render failure
// falls through to Express's default handler, which echoes the stack trace in the
// response body unless NODE_ENV=production is set - this is a second, explicit layer
// so a leak doesn't depend solely on that env var being set correctly everywhere.
app.use(
  (err: unknown, req: express.Request, res: express.Response, _next: express.NextFunction) => {
    console.error(`[SSR] Unhandled render error for ${req.originalUrl}`, err);
    res.status(500).send('Internal Server Error');
  }
);

const port = process.env['PORT'] || 4000;
app.listen(port, () => {
  console.log(`SSR server running on port ${port}`);
});
