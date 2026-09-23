import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { OfflineQueue } from '../src/offlineQueue.js';

function tempDir() {
  return mkdtempSync(join(tmpdir(), 'dahua-bridge-queue-'));
}

test('enqueue then peekAll returns events in order', () => {
  const dir = tempDir();
  const queue = new OfflineQueue(dir);

  queue.enqueue({ personId: 'A', direction: 'check_in' });
  queue.enqueue({ personId: 'B', direction: 'check_out' });

  assert.deepEqual(queue.peekAll(), [
    { personId: 'A', direction: 'check_in' },
    { personId: 'B', direction: 'check_out' },
  ]);

  rmSync(dir, { recursive: true, force: true });
});

test('replaceWith rewrites the queue to exactly the given events', () => {
  const dir = tempDir();
  const queue = new OfflineQueue(dir);

  queue.enqueue({ personId: 'A' });
  queue.enqueue({ personId: 'B' });
  queue.replaceWith([{ personId: 'B' }]);

  assert.deepEqual(queue.peekAll(), [{ personId: 'B' }]);
  assert.equal(queue.size, 1);

  rmSync(dir, { recursive: true, force: true });
});

test('replaceWith([]) empties the queue', () => {
  const dir = tempDir();
  const queue = new OfflineQueue(dir);

  queue.enqueue({ personId: 'A' });
  queue.replaceWith([]);

  assert.deepEqual(queue.peekAll(), []);

  rmSync(dir, { recursive: true, force: true });
});

test('a queue reopened from disk sees what an earlier instance wrote', () => {
  const dir = tempDir();
  new OfflineQueue(dir).enqueue({ personId: 'A' });

  const reopened = new OfflineQueue(dir);
  assert.deepEqual(reopened.peekAll(), [{ personId: 'A' }]);

  rmSync(dir, { recursive: true, force: true });
});
