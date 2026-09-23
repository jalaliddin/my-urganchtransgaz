import assert from 'node:assert/strict';
import { test } from 'node:test';

import { ScanDeduper } from '../src/dedupe.js';

test('drops a second scan of the same person within the window', () => {
  let now = 0;
  const dedupe = new ScanDeduper(4, () => now);

  assert.equal(dedupe.shouldProcess('A'), true);
  now += 1000;
  assert.equal(dedupe.shouldProcess('A'), false);
});

test('allows a scan once the window has passed', () => {
  let now = 0;
  const dedupe = new ScanDeduper(4, () => now);

  assert.equal(dedupe.shouldProcess('A'), true);
  now += 5000;
  assert.equal(dedupe.shouldProcess('A'), true);
});

test('tracks each person independently', () => {
  let now = 0;
  const dedupe = new ScanDeduper(4, () => now);

  assert.equal(dedupe.shouldProcess('A'), true);
  assert.equal(dedupe.shouldProcess('B'), true);
});
