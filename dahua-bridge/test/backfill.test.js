import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { runBackfill } from '../src/backfill.js';
import { loadConfig } from '../src/config.js';

function setup(overrides = {}) {
  const dataDir = mkdtempSync(join(tmpdir(), 'dahua-bridge-backfill-'));
  const config = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd', DATA_DIR: dataDir,
    ...overrides,
  });
  const enqueued = [];
  const queue = { enqueue: (e) => enqueued.push(e) };
  const direction = { resolve: () => 'check_in' };
  const logger = { info() {}, warn() {}, error() {}, debug() {} };
  let flushCalls = 0;
  const flush = async () => {
    flushCalls++;
  };

  return { config, dataDir, enqueued, queue, direction, logger, flush, getFlushCalls: () => flushCalls };
}

function record(overrides = {}) {
  return {
    RecNo: '1', UserID: '108', Status: '1', ErrorCode: '0', Type: 'Entry', CreateTime: '1789554842',
    ...overrides,
  };
}

test('queues a successful record, converting its own device time to Tashkent local', async () => {
  const { config, dataDir, enqueued, queue, direction, logger, flush } = setup();
  const fetchRecords = async () => ({ found: 1, records: [record()] });

  const result = await runBackfill(config, { logger, queue, direction, flush, fetchRecords });

  assert.equal(result.processed, 1);
  assert.equal(enqueued.length, 1);
  assert.equal(enqueued[0].personId, '108');
  assert.equal(enqueued[0].direction, 'check_in');
  // Same real device value verified in tashkentTime.test.js.
  assert.equal(enqueued[0].eventTime, '2026-09-16 15:34:02');

  rmSync(dataDir, { recursive: true, force: true });
});

test('skips a denied/failed record', async () => {
  const { config, dataDir, enqueued, queue, direction, logger, flush } = setup();
  const fetchRecords = async () => ({ found: 1, records: [record({ ErrorCode: '16', UserID: '' })] });

  const result = await runBackfill(config, { logger, queue, direction, flush, fetchRecords });

  assert.equal(result.processed, 0);
  assert.deepEqual(enqueued, []);

  rmSync(dataDir, { recursive: true, force: true });
});

test('does not re-queue a record already processed on an earlier run (RecNo cursor)', async () => {
  const { config, dataDir, queue, direction, logger, flush } = setup();
  const fetchRecords = async () => ({ found: 1, records: [record({ RecNo: '5' })] });

  await runBackfill(config, { logger, queue, direction, flush, fetchRecords });

  const enqueuedSecondRun = [];
  const secondQueue = { enqueue: (e) => enqueuedSecondRun.push(e) };
  await runBackfill(config, { logger, queue: secondQueue, direction, flush, fetchRecords: async () => ({ found: 1, records: [record({ RecNo: '5' })] }) });

  assert.deepEqual(enqueuedSecondRun, []);

  rmSync(dataDir, { recursive: true, force: true });
});

test('processes a record with a higher RecNo than the last run, ignores an older one in the same batch', async () => {
  const { config, dataDir, queue, direction, logger, flush } = setup();
  await runBackfill(config, {
    logger, queue, direction, flush,
    fetchRecords: async () => ({ found: 1, records: [record({ RecNo: '10' })] }),
  });

  const enqueued = [];
  const secondQueue = { enqueue: (e) => enqueued.push(e) };
  await runBackfill(config, {
    logger, queue: secondQueue, direction, flush,
    fetchRecords: async () => ({
      found: 2,
      records: [record({ RecNo: '8', UserID: 'old' }), record({ RecNo: '11', UserID: 'new' })],
    }),
  });

  assert.deepEqual(enqueued.map((e) => e.personId), ['new']);

  rmSync(dataDir, { recursive: true, force: true });
});

test('DRY_RUN observes without enqueueing, flushing, or persisting the cursor', async () => {
  const { config, dataDir, direction, logger, flush } = setup({ DRY_RUN: 'true' });
  let flushCalls = 0;
  const countingFlush = async () => {
    flushCalls++;
    await flush();
  };

  const enqueued = [];
  const countingQueue = { enqueue: (e) => enqueued.push(e) };

  const result = await runBackfill(config, {
    logger, queue: countingQueue, direction, flush: countingFlush,
    fetchRecords: async () => ({ found: 1, records: [record({ RecNo: '7' })] }),
  });

  assert.equal(result.processed, 1, 'still reports what it would have queued');
  assert.deepEqual(enqueued, [], 'never actually enqueued');
  assert.equal(flushCalls, 0, 'never flushed');

  // A real (non-dry-run) run afterward, against that same on-disk state,
  // must still see this same record — dry-run must not have advanced the
  // cursor past it.
  const realConfig = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd', DATA_DIR: dataDir,
  });
  const realEnqueued = [];
  const realQueue = { enqueue: (e) => realEnqueued.push(e) };
  await runBackfill(realConfig, {
    logger, queue: realQueue, direction, flush,
    fetchRecords: async () => ({ found: 1, records: [record({ RecNo: '7' })] }),
  });
  assert.equal(realEnqueued.length, 1, 'the record is still available for a real run');

  rmSync(dataDir, { recursive: true, force: true });
});

test('does nothing when RECORD_FINDER_ENABLED is false', async () => {
  const { config, dataDir, queue, direction, logger, flush } = setup({ RECORD_FINDER_ENABLED: 'false' });
  let called = false;
  const fetchRecords = async () => {
    called = true;

    return { found: 0, records: [] };
  };

  const result = await runBackfill(config, { logger, queue, direction, flush, fetchRecords });

  assert.equal(called, false);
  assert.equal(result.processed, 0);

  rmSync(dataDir, { recursive: true, force: true });
});

test('a fresh install with no prior state and no BACKFILL_SINCE looks back one day', async () => {
  const { config, dataDir, queue, direction, logger, flush } = setup();
  let seenStartTime;
  const fetchRecords = async (_config, { startTime }) => {
    seenStartTime = startTime;

    return { found: 0, records: [] };
  };

  const before = Math.floor(Date.now() / 1000) - 24 * 3600;
  await runBackfill(config, { logger, queue, direction, flush, fetchRecords });
  const after = Math.floor(Date.now() / 1000) - 24 * 3600;

  assert.ok(seenStartTime >= before - 2 && seenStartTime <= after + 2);

  rmSync(dataDir, { recursive: true, force: true });
});

test('a fresh install honors an explicit BACKFILL_SINCE', async () => {
  const { config, dataDir, queue, direction, logger, flush } = setup({ BACKFILL_SINCE: '2026-01-01T00:00:00Z' });
  let seenStartTime;
  const fetchRecords = async (_config, { startTime }) => {
    seenStartTime = startTime;

    return { found: 0, records: [] };
  };

  await runBackfill(config, { logger, queue, direction, flush, fetchRecords });

  assert.equal(seenStartTime, Math.floor(Date.parse('2026-01-01T00:00:00Z') / 1000));

  rmSync(dataDir, { recursive: true, force: true });
});

test('flushes only when something was actually queued', async () => {
  const { config, dataDir, queue, direction, logger } = setup();
  let flushCalls = 0;
  const flush = async () => {
    flushCalls++;
  };

  await runBackfill(config, { logger, queue, direction, flush, fetchRecords: async () => ({ found: 0, records: [] }) });
  assert.equal(flushCalls, 0);

  await runBackfill(config, { logger, queue, direction, flush, fetchRecords: async () => ({ found: 1, records: [record({ RecNo: '99' })] }) });
  assert.equal(flushCalls, 1);

  rmSync(dataDir, { recursive: true, force: true });
});
