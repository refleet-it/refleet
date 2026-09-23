#!/usr/bin/env node
// Fixes a bug in @angular/platform-server 19.2.x where validateAllowedHosts
// throws when allowedHosts is not passed (e.g. from @angular/ssr's renderApplication call),
// breaking SSR prerendering. The fix: skip validation when allowedHosts is undefined/empty
// (backward-compatible behaviour — only enforce when explicitly configured).
// Upstream bug: @angular/ssr does not propagate manifest.allowedHosts to renderApplication.

const fs = require('fs');
const path = require('path');

const filePath = path.join(
  __dirname,
  '../node_modules/@angular/platform-server/fesm2022/platform-server.mjs',
);

if (!fs.existsSync(filePath)) {
  console.warn('patch-angular-platform-server: file not found, skipping');
  process.exit(0);
}

let content = fs.readFileSync(filePath, 'utf8');

// Matched with a regex so the patch survives indentation/formatting changes
// across Angular versions (4-space in 19.x, 2-space in 21.x).
const alreadyApplied =
  /function validateAllowedHosts\(url, allowedHosts\) \{\n\s*if \(!allowedHosts \|\| !allowedHosts\.length\) \{ return; \}/;
const broken =
  /(function validateAllowedHosts\(url, allowedHosts\) \{\n)(\s*)(if \(typeof url === 'string'\) \{)/;

if (alreadyApplied.test(content)) {
  console.log('patch-angular-platform-server: already applied');
  process.exit(0);
}

if (broken.test(content)) {
  const patched = content.replace(
    broken,
    (_match, open, indent, guard) =>
      `${open}${indent}if (!allowedHosts || !allowedHosts.length) { return; }\n${indent}${guard}`,
  );
  fs.writeFileSync(filePath, patched, 'utf8');
  console.log('patch-angular-platform-server: applied successfully');
  process.exit(0);
}

// Neither our guard nor the known-broken signature is present. If the throwing
// host check is still there, the patch pattern is stale (e.g. after an Angular
// bump) and SSR prerendering will break — fail loudly at install time instead
// of skipping silently and breaking the build later.
if (content.includes('is not allowed. You can configure')) {
  console.error(
    'patch-angular-platform-server: validateAllowedHosts signature changed but the ' +
      'host-allow throw is still present — update the patch pattern for this Angular version.',
  );
  process.exit(1);
}

console.log('patch-angular-platform-server: no vulnerable host check found — assuming fixed upstream, skipping');
