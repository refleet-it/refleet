module.exports = {
  ci: {
    collect: {
      // Audit the production build, not the dev server: unminified bundles with sourcemaps and
      // HMR score nothing like what a user gets, so tuning against them chases an artefact.
      // LHCI serves this directory itself, which is also why no server has to be running.
      staticDistDir: 'dist/front/browser',
      // Headless explicitly: a windowed Chrome that loses focus paints nothing and the whole
      // run fails with NO_FCP.
      chromeFlags: '--headless=new --no-sandbox --disable-gpu',
      // Only the application entry point. Depth 1 keeps the rendered documentation fragments
      // under assets/docs/ out of it — they are HTML without <html>, styles or scripts, so a
      // tool that globs for .html audits them, paints nothing and fails the whole run.
      staticDirFileDiscoveryDepth: 1,
      autodiscoverUrlBlocklist: ['/index.csr.html', '/index-google-fonts.html'],
      // Number of runs to average
      numberOfRuns: 3,
      // Settings for the Lighthouse run
      settings: {
        preset: 'desktop',
        // Emulate desktop
        formFactor: 'desktop',
        screenEmulation: {
          mobile: false,
          width: 1350,
          height: 940,
          deviceScaleFactor: 1,
          disabled: false,
        },
      },
    },
    assert: {
      // Assertions for quality thresholds
      assertions: {
        'categories:performance': ['error', { minScore: 0.9 }],
        'categories:accessibility': ['error', { minScore: 0.95 }],
        'categories:best-practices': ['error', { minScore: 0.9 }],
        'categories:seo': ['error', { minScore: 0.9 }],

        // Specific metrics
        'first-contentful-paint': ['warn', { maxNumericValue: 2000 }],
        'largest-contentful-paint': ['warn', { maxNumericValue: 2500 }],
        'cumulative-layout-shift': ['warn', { maxNumericValue: 0.1 }],
        'total-blocking-time': ['warn', { maxNumericValue: 300 }],
      },
    },
    upload: {
      // Keep reports on the machine that produced them; the default target publishes them to a
      // public Google-hosted URL.
      target: 'filesystem',
      outputDir: '.lighthouseci',
    },
  },
};
