import { test } from 'node:test';
import assert from 'node:assert/strict';
import { EXEC_SERVICES, MAKE_TARGETS, SERVICES } from './config.js';

/**
 * These lists are the whole access-control model — a tool never runs anything outside them. They
 * are hand-maintained arrays with no type-level enforcement of what must stay out, so nothing but a
 * test catches an entry silently coming back (a bad merge, a copy-paste from another list).
 */
test('MAKE_TARGETS never includes a target that drops schemas or wipes volumes', () => {
  for (const destructive of ['clean', 'init', 'db-recreate']) {
    assert.ok(!MAKE_TARGETS.includes(destructive as (typeof MAKE_TARGETS)[number]), `${destructive} must stay out of MAKE_TARGETS`);
  }
});

test('EXEC_SERVICES never includes a service that holds application data', () => {
  for (const dataService of ['database', 'mail']) {
    assert.ok(!EXEC_SERVICES.includes(dataService as (typeof EXEC_SERVICES)[number]), `${dataService} must stay out of EXEC_SERVICES`);
  }
});

test('EXEC_SERVICES is a subset of SERVICES', () => {
  for (const service of EXEC_SERVICES) {
    assert.ok((SERVICES as readonly string[]).includes(service));
  }
});
