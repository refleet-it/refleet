import { test } from 'node:test';
import assert from 'node:assert/strict';
import { parseArgs } from './run.js';

test('parseArgs reads engine/workdir/kind/model', () => {
  const args = parseArgs([
    '--engine',
    'kiro',
    '--workdir',
    '/repo',
    '--kind',
    'qualification',
    '--model',
    'some-model',
  ]);

  assert.deepEqual(args, { engine: 'kiro', workdir: '/repo', kind: 'qualification', model: 'some-model' });
});

test('parseArgs makes --model optional', () => {
  const args = parseArgs(['--engine', 'claude', '--workdir', '/repo', '--kind', 'change']);
  assert.equal(args.model, undefined);
});

test('parseArgs rejects an unknown engine', () => {
  assert.throws(
    () => parseArgs(['--engine', 'gpt', '--workdir', '/repo', '--kind', 'change']),
    /--engine must be "claude" or "kiro"/,
  );
});

test('parseArgs requires --workdir', () => {
  assert.throws(
    () => parseArgs(['--engine', 'claude', '--kind', 'change']),
    /--workdir is required/,
  );
});

test('parseArgs rejects an unknown kind', () => {
  assert.throws(
    () => parseArgs(['--engine', 'claude', '--workdir', '/repo', '--kind', 'bogus']),
    /--kind must be "qualification" or "change"/,
  );
});
