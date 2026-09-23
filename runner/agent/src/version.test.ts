import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isNpmInstalled, runnerVersion } from './version.js';

test('runnerVersion reads package.json next to dist', () => {
  assert.match(runnerVersion(), /^\d+\.\d+\.\d+$/);
});

test('isNpmInstalled recognises a global install and an npx cache, not a checkout or the image', () => {
  assert.equal(isNpmInstalled('file:///usr/lib/node_modules/@refleet-it/runner/dist/version.js'), true);
  assert.equal(isNpmInstalled('file:///home/x/.npm/_npx/abc/node_modules/@refleet-it/runner/dist/version.js'), true);
  assert.equal(isNpmInstalled('file:///home/x/refleet/runner/agent/dist/version.js'), false);
  assert.equal(isNpmInstalled('file:///agent/dist/version.js'), false);
  assert.equal(isNpmInstalled('not a url'), false);
});
