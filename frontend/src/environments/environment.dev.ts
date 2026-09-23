// apiUrl is relative on purpose. The browser bundle is the same file for every instance —
// the hosted one and every self-hosted one — so a hostname baked in here would point all of
// them at ours. Same origin works because the proxy in front serves /api/* from the backend
// (deploy/selfhost/Caddyfile, infra/caddy/Caddyfile.gateway.prod). On the server there is no
// origin to be relative to, so ssrUrlRewriterInterceptor swaps this prefix for BACKEND_API_URL.
export const environment = {
  production: true,
  apiUrl: '/api',
  appName: 'Refleet DEV',
  version: '1.0.0',
  enableDebugInfo: true,
  logLevel: 'debug',
  buildHash: 'dev',
};
