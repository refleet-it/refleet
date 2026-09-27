import { test } from 'node:test';
import assert from 'node:assert/strict';
import { COMMANDS, usageText } from './cli.js';
import { DEFAULT_API_URL } from './login.js';

test('the help lists every command with a summary', () => {
  const help = usageText();

  for (const [name, summary] of COMMANDS) {
    assert.ok(help.includes(name), `help does not mention the "${name}" command`);
    assert.ok(help.includes(summary), `help does not carry the summary of "${name}"`);
  }
});

test('the help names the two credentials a runner cannot start without', () => {
  const help = usageText();

  assert.match(help, /REFLEET_API_URL/);
  assert.match(help, /REFLEET_API_KEY/);
});

test('the help quotes the real login default rather than a hardcoded copy of it', () => {
  assert.ok(usageText().includes(DEFAULT_API_URL));
});

test('the help leads with the version, so a bug report shows which runner produced it', () => {
  assert.match(usageText(), /^refleet \d+\.\d+\.\d+ — /);
});
