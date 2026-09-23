import assert from 'node:assert/strict';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';
import { createEventPipeline } from '../src/eventPipeline.js';

function part(contentType, bodyText) {
  return { headers: new Map([['content-type', contentType]]), body: Buffer.from(bodyText, 'utf8') };
}

function setup(overrides = {}) {
  const config = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
    ...overrides,
  });
  const logger = { info() {}, warn() {}, error() {}, debug() {} };
  const enqueued = [];
  const queue = { enqueue: (event) => enqueued.push(event) };
  const direction = { resolve: () => 'check_in' };
  const dedupe = { shouldProcess: () => true };
  let flushCalls = 0;
  const flush = async () => {
    flushCalls++;
  };

  const handlePart = createEventPipeline(config, { logger, dedupe, direction, queue, flush });

  return { handlePart, enqueued, getFlushCalls: () => flushCalls };
}

test('ignores a non-text/plain part (a JPEG snapshot)', async () => {
  const { handlePart, enqueued } = setup();

  await handlePart(part('image/jpeg', 'irrelevant'));

  assert.deepEqual(enqueued, []);
});

test('ignores a heartbeat part', async () => {
  const { handlePart, enqueued } = setup();

  await handlePart(part('text/plain', 'Heartbeat'));

  assert.deepEqual(enqueued, []);
});

test('ignores a non-AccessControl event', async () => {
  const { handlePart, enqueued } = setup();

  await handlePart(part('text/plain', 'Events[0].Code=VideoMotion'));

  assert.deepEqual(enqueued, []);
});

test('ignores a denied access attempt', async () => {
  const { handlePart, enqueued } = setup();

  await handlePart(part(
    'text/plain',
    'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001\r\nEvents[0].AccessControl.Status=Failure',
  ));

  assert.deepEqual(enqueued, []);
});

test('enqueues and flushes a valid, successful scan', async () => {
  const { handlePart, enqueued, getFlushCalls } = setup();

  await handlePart(part(
    'text/plain',
    'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001\r\nEvents[0].AccessControl.Status=Success',
  ));

  assert.equal(enqueued.length, 1);
  assert.equal(enqueued[0].personId, '1001');
  assert.equal(enqueued[0].direction, 'check_in');
  assert.ok(enqueued[0].eventTime);
  assert.equal(getFlushCalls(), 1);
});

test('handles more than one event in a single part', async () => {
  const { handlePart, enqueued } = setup();

  await handlePart(part(
    'text/plain',
    [
      'Events[0].Code=AccessControl',
      'Events[0].AccessControl.UserID=1001',
      'Events[1].Code=AccessControl',
      'Events[1].AccessControl.UserID=1002',
    ].join('\r\n'),
  ));

  assert.deepEqual(enqueued.map((e) => e.personId), ['1001', '1002']);
});

test('dry run recognizes scans but never enqueues them', async () => {
  const { handlePart, enqueued, getFlushCalls } = setup({ DRY_RUN: 'true' });

  await handlePart(part('text/plain', 'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001'));

  assert.deepEqual(enqueued, []);
  assert.equal(getFlushCalls(), 0);
});

test('a deduped repeat scan is never enqueued', async () => {
  const config = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
  });
  const enqueued = [];
  const handlePart = createEventPipeline(config, {
    logger: { info() {}, warn() {}, error() {}, debug() {} },
    dedupe: { shouldProcess: () => false },
    direction: { resolve: () => 'check_in' },
    queue: { enqueue: (event) => enqueued.push(event) },
    flush: async () => {},
  });

  await handlePart(part('text/plain', 'Events[0].Code=AccessControl\r\nEvents[0].AccessControl.UserID=1001'));

  assert.deepEqual(enqueued, []);
});
