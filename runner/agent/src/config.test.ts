import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readFileSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { clearStoredConfig, loadStoredConfig, resolveFleetConfig, saveStoredConfig } from './config.js';

function tempDir(): string {
  return mkdtempSync(join(tmpdir(), 'refleet-runner-config-test-'));
}

const sampleCredentials = {
  apiUrl: 'http://localhost/api',
  apiKey: 'ib_test_token',
  apiKeyId: 'key-1',
  accountEmail: 'owner@acme-robotics.test',
  createdAt: '2026-08-08T00:00:00+00:00',
};

test('loadStoredConfig returns null when nothing has been saved', () => {
  assert.equal(loadStoredConfig(tempDir()), null);
});

test('saveStoredConfig/loadStoredConfig round-trip', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  assert.deepEqual(loadStoredConfig(dir), sampleCredentials);
});

test('saveStoredConfig writes the file with restrictive permissions', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  const mode = statSync(join(dir, 'runner.json')).mode & 0o777;
  assert.equal(mode, 0o600);
});

test('clearStoredConfig removes a saved config', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);
  clearStoredConfig(dir);

  assert.equal(loadStoredConfig(dir), null);
});

test('clearStoredConfig is a no-op when nothing was saved', () => {
  assert.doesNotThrow(() => clearStoredConfig(tempDir()));
});

test('resolveFleetConfig prefers REFLEET_API_URL/REFLEET_API_KEY env vars over a stored login', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  const env = { REFLEET_API_URL: 'https://cloud.example/api', REFLEET_API_KEY: 'ib_env_token' };
  assert.deepEqual(resolveFleetConfig(env, dir), {
    apiUrl: 'https://cloud.example/api',
    apiKey: 'ib_env_token',
    source: 'env',
  });
});

test('resolveFleetConfig falls back to a stored login when env vars are absent', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  assert.deepEqual(resolveFleetConfig({}, dir), {
    apiUrl: sampleCredentials.apiUrl,
    apiKey: sampleCredentials.apiKey,
    source: 'file',
  });
});

test('resolveFleetConfig falls back to the stored file when only one env var is set', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  assert.deepEqual(resolveFleetConfig({ REFLEET_API_URL: 'https://cloud.example/api' }, dir), {
    apiUrl: sampleCredentials.apiUrl,
    apiKey: sampleCredentials.apiKey,
    source: 'file',
  });
});

test('resolveFleetConfig returns null when neither env vars nor a stored login exist', () => {
  assert.equal(resolveFleetConfig({}, tempDir()), null);
});

test('sample credentials file actually round-trips through JSON on disk', () => {
  const dir = tempDir();
  saveStoredConfig(sampleCredentials, dir);

  const raw = readFileSync(join(dir, 'runner.json'), 'utf8');
  assert.deepEqual(JSON.parse(raw), sampleCredentials);
});
