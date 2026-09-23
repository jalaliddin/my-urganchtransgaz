import assert from 'node:assert/strict';
import { test } from 'node:test';

import { readAccessControlEvent } from '../src/accessControlEvent.js';
import { loadConfig } from '../src/config.js';

function baseConfig(overrides = {}) {
  return loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
    ...overrides,
  });
}

test('returns null for a non-AccessControl event', () => {
  assert.equal(readAccessControlEvent({ Code: 'VideoMotion' }, baseConfig()), null);
});

test('is case-insensitive about the Code value', () => {
  const event = readAccessControlEvent({ Code: 'accesscontrol', 'AccessControl.UserID': '1001' }, baseConfig());
  assert.equal(event.personId, '1001');
});

test('resolves the person id from the first configured candidate field present', () => {
  const config = baseConfig();
  assert.equal(readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1001', 'AccessControl.CardNo': '99' }, config).personId, '1001');
  assert.equal(readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.CardNo': '99' }, config).personId, '99');
});

test('reports no person id when none of the candidate fields are present', () => {
  const event = readAccessControlEvent({ Code: 'AccessControl' }, baseConfig());
  assert.equal(event.personId, null);
});

test('treats a known failure status as unsuccessful', () => {
  const event = readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1', 'AccessControl.Status': 'Failure' }, baseConfig());
  assert.equal(event.successful, false);
});

test('treats a known success status as successful', () => {
  const event = readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1', 'AccessControl.Status': 'Success' }, baseConfig());
  assert.equal(event.successful, true);
});

test('treats a missing or unrecognized status as successful (permissive default)', () => {
  const noStatus = readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1' }, baseConfig());
  const weirdStatus = readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1', 'AccessControl.Status': 'SomeFutureValue' }, baseConfig());

  assert.equal(noStatus.successful, true);
  assert.equal(weirdStatus.successful, true);
});

test('carries the configured direction field value through unchanged', () => {
  const config = baseConfig({ DIRECTION_FIELD: 'AccessControl.Door' });
  const event = readAccessControlEvent({ Code: 'AccessControl', 'AccessControl.UserID': '1', 'AccessControl.Door': '2' }, config);

  assert.equal(event.directionFieldValue, '2');
});
