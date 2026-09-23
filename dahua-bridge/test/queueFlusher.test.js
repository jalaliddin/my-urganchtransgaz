import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';
import { OfflineQueue } from '../src/offlineQueue.js';
import { flushQueue } from '../src/queueFlusher.js';

function setup() {
  const dataDir = mkdtempSync(join(tmpdir(), 'dahua-bridge-flush-'));
  const config = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd', DATA_DIR: dataDir,
  });
  const queue = new OfflineQueue(dataDir);
  const logger = { info() {}, warn() {}, error() {}, debug() {} };

  return { config, queue, logger, dataDir };
}

test('delivered events are removed from the queue', async () => {
  const { config, queue, logger, dataDir } = setup();
  queue.enqueue({ personId: 'A' });
  queue.enqueue({ personId: 'B' });

  await flushQueue(config, queue, logger, async () => ({ outcome: 'delivered', status: 201 }));

  assert.deepEqual(queue.peekAll(), []);
  rmSync(dataDir, { recursive: true, force: true });
});

test('rejected events are dropped, not retried', async () => {
  const { config, queue, logger, dataDir } = setup();
  queue.enqueue({ personId: 'unknown-person' });

  await flushQueue(config, queue, logger, async () => ({ outcome: 'rejected', status: 422 }));

  assert.deepEqual(queue.peekAll(), []);
  rmSync(dataDir, { recursive: true, force: true });
});

test('a transient failure keeps that event and everything after it queued, in order', async () => {
  const { config, queue, logger, dataDir } = setup();
  queue.enqueue({ personId: 'A' });
  queue.enqueue({ personId: 'B' });
  queue.enqueue({ personId: 'C' });

  let call = 0;
  await flushQueue(config, queue, logger, async (_config, event) => {
    call++;
    // The first delivers fine; the backend then goes offline for the rest.
    return event.personId === 'A' ? { outcome: 'delivered' } : { outcome: 'transient', error: 'network down' };
  });

  assert.deepEqual(queue.peekAll(), [{ personId: 'B' }, { personId: 'C' }]);
  assert.equal(call, 2, 'should stop attempting further events after the first transient failure');
  rmSync(dataDir, { recursive: true, force: true });
});

test('an empty queue does not call sendEvent at all', async () => {
  const { config, queue, logger, dataDir } = setup();
  let called = false;

  await flushQueue(config, queue, logger, async () => {
    called = true;
    return { outcome: 'delivered' };
  });

  assert.equal(called, false);
  rmSync(dataDir, { recursive: true, force: true });
});
