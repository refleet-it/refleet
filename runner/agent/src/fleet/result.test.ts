import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractLastJsonObject, parseChangeResult, parseQualificationResult } from './result.js';

test('extractLastJsonObject prefers the last fenced block over bare braces in the prose', () => {
  const output = 'Config is `{"debug": true}` in app.json.\n\n```json\n{"score": 5, "reasoning": "uses it"}\n```\nDone.';

  assert.deepEqual(extractLastJsonObject(output), { score: 5, reasoning: 'uses it' });
});

test('extractLastJsonObject falls back to the last bare object and skips unparsable candidates', () => {
  assert.deepEqual(extractLastJsonObject('Reasoning {not json} then {"score": 2}'), { score: 2 });
  assert.deepEqual(extractLastJsonObject('{"a": {"b": 1}}'), { a: { b: 1 } });
  assert.equal(extractLastJsonObject('no object here'), null);
  assert.equal(extractLastJsonObject('[1, 2]'), null);
  assert.equal(extractLastJsonObject(''), null);
});

test('parseQualificationResult accepts integer scores from 1 to 5, also as numeric strings', () => {
  assert.deepEqual(parseQualificationResult('{"score": 4, "reasoning": "close enough"}'), { score: 4, reasoning: 'close enough' });
  assert.deepEqual(parseQualificationResult('{"score": "1"}'), { score: 1, reasoning: '' });
  assert.equal(parseQualificationResult('{"score": 0}'), null);
  assert.equal(parseQualificationResult('{"score": 6}'), null);
  assert.equal(parseQualificationResult('{"score": 3.5}'), null);
  assert.equal(parseQualificationResult('{"score": "high"}'), null);
  assert.equal(parseQualificationResult('{"reasoning": "forgot the score"}'), null);
  assert.equal(parseQualificationResult('QUALIFICATION_DECISION: true'), null);
});

test('parseChangeResult needs a non-empty summary', () => {
  assert.deepEqual(parseChangeResult('Edited files.\n```json\n{"summary": "Bumped the lib."}\n```'), { summary: 'Bumped the lib.' });
  assert.equal(parseChangeResult('{"summary": "   "}'), null);
  assert.equal(parseChangeResult('Nothing structured.'), null);
});

test('parseChangeResult carries the proposed commit and merge request wording', () => {
  const output = JSON.stringify({
    summary: 'Bumped the lib.',
    commit: { subject: 'chore(deps): bump acme/legacy-lib to ^3.0', body: 'Needed for PHP 8.5.' },
    mergeRequest: { title: 'Bump acme/legacy-lib to ^3.0', description: '## What\n\nBumped it.' },
  });

  assert.deepEqual(parseChangeResult(output), {
    summary: 'Bumped the lib.',
    commit: { subject: 'chore(deps): bump acme/legacy-lib to ^3.0', body: 'Needed for PHP 8.5.' },
    mergeRequest: { title: 'Bump acme/legacy-lib to ^3.0', description: '## What\n\nBumped it.' },
  });
});

test('parseChangeResult drops a commit whose subject is blank, multi-line or over 72 characters', () => {
  assert.deepEqual(parseChangeResult('{"summary": "x", "commit": {"subject": "  "}}'), { summary: 'x' });
  assert.deepEqual(parseChangeResult('{"summary": "x", "commit": {"subject": "two\\nlines"}}'), { summary: 'x' });
  assert.deepEqual(parseChangeResult(`{"summary": "x", "commit": {"subject": "${'a'.repeat(73)}"}}`), { summary: 'x' });
  assert.deepEqual(parseChangeResult(`{"summary": "x", "commit": {"subject": "${'a'.repeat(72)}"}}`), {
    summary: 'x',
    commit: { subject: 'a'.repeat(72), body: '' },
  });
  assert.deepEqual(parseChangeResult('{"summary": "x", "commit": "not an object"}'), { summary: 'x' });
});

test('parseChangeResult drops a merge request without a single-line title', () => {
  assert.deepEqual(parseChangeResult('{"summary": "x", "mergeRequest": {"description": "no title"}}'), { summary: 'x' });
  assert.deepEqual(parseChangeResult('{"summary": "x", "mergeRequest": {"title": "a\\nb"}}'), { summary: 'x' });
  assert.deepEqual(parseChangeResult('{"summary": "x", "mergeRequest": {"title": " T "}}'), {
    summary: 'x',
    mergeRequest: { title: 'T', description: '' },
  });
});
