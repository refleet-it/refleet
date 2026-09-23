import { test } from 'node:test';
import assert from 'node:assert/strict';
import { detectAgents, resolveExecutable } from './detect.js';

test('resolveExecutable finds a bare command on PATH', () => {
  const env = { PATH: '/usr/bin:/usr/local/bin' };
  const isExecutable = (path: string): boolean => '/usr/local/bin/claude' === path;

  assert.equal(resolveExecutable('claude', env, isExecutable), '/usr/local/bin/claude');
});

test('resolveExecutable returns null when nothing on PATH is executable', () => {
  const env = { PATH: '/usr/bin:/usr/local/bin' };
  assert.equal(
    resolveExecutable('claude', env, () => false),
    null,
  );
});

test('resolveExecutable treats a command containing a slash as an explicit path', () => {
  const env = { PATH: '/usr/bin' };
  const isExecutable = (path: string): boolean => '/opt/tools/claude' === path;

  assert.equal(resolveExecutable('/opt/tools/claude', env, isExecutable), '/opt/tools/claude');
  assert.equal(resolveExecutable('./claude', env, () => false), null);
});

test('resolveExecutable returns null for an empty command', () => {
  assert.equal(resolveExecutable('  ', { PATH: '/usr/bin' }, () => true), null);
});

test('detectAgents reports only the engines actually found on PATH', () => {
  const env = { PATH: '/usr/local/bin' };
  const isExecutable = (path: string): boolean => path.endsWith('/kiro-cli');

  assert.deepEqual(detectAgents(env, isExecutable), [{ engine: 'kiro', path: '/usr/local/bin/kiro-cli' }]);
});

test('detectAgents honors a REFLEET_*_PATH override instead of the bare command name', () => {
  const env = { PATH: '/usr/local/bin', REFLEET_CLAUDE_PATH: '/custom/claude-beta' };
  const isExecutable = (path: string): boolean => '/custom/claude-beta' === path;

  assert.deepEqual(detectAgents(env, isExecutable), [{ engine: 'claude', path: '/custom/claude-beta' }]);
});

test('detectAgents returns an empty list when nothing is installed', () => {
  assert.deepEqual(
    detectAgents({ PATH: '/usr/bin' }, () => false),
    [],
  );
});
