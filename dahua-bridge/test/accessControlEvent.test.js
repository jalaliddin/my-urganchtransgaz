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

test('recognizes the Code under EventBaseInfo.Code — the field a real terminal was observed using', () => {
  const event = readAccessControlEvent({ 'EventBaseInfo.Code': 'AccessControl', UserID: '1001' }, baseConfig());
  assert.equal(event.personId, '1001');
});

test('reads a real captured event from a live terminal (an unrecognized/no-match scan)', () => {
  // Captured verbatim (redacted of nothing — this scan had no matched
  // person at all) during development against a real Dahua terminal.
  const liveFields = {
    Alive: '0', CardName: '', CardNo: '', CardType: '0', CreateTime: '1790142518', Door: '0',
    ErrorCode: '16', 'EventBaseInfo.Action': 'Pulse', 'EventBaseInfo.Code': 'AccessControl',
    'EventBaseInfo.Index': '0', FeatureId: '0', Method: '15', ReaderID: '1', RealUTC: '1790142518',
    Similarity: '0', SnapPath: '/var/tmp/partsnap3117.jpg', Status: '0', Type: 'Entry',
    UTC: '1790142518', UserID: '', UserType: '0',
  };

  const event = readAccessControlEvent(liveFields, baseConfig());

  assert.equal(event.personId, null, 'both UserID and CardNo were empty — no person to attribute this to');
  assert.equal(event.successful, true, 'personId is null, so successful is a moot default and never reaches the caller as a scan');
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

test('ErrorCode, when present, decides success over Status — 0 is success, nonzero is a failure', () => {
  const config = baseConfig();

  // Shaped like the real terminal's field names (EventBaseInfo.Code,
  // bare UserID/Status/ErrorCode), but with a matched person and
  // ErrorCode=0 filled in — the live capture only ever caught a
  // no-match scan (ErrorCode 16, no UserID), so this is not itself a
  // captured example, only the same schema with the values a genuine
  // successful scan is expected to carry.
  const successful = readAccessControlEvent(
    { 'EventBaseInfo.Code': 'AccessControl', UserID: '1001', ErrorCode: '0', Status: '0' },
    config,
  );
  const failed = readAccessControlEvent(
    { 'EventBaseInfo.Code': 'AccessControl', UserID: '1001', ErrorCode: '16', Status: '0' },
    config,
  );

  assert.equal(successful.successful, true);
  assert.equal(failed.successful, false);
});

test('falls back to Status when ErrorCode is absent', () => {
  const config = baseConfig();

  assert.equal(readAccessControlEvent({ Code: 'AccessControl', UserID: '1', Status: 'Success' }, config).successful, true);
  assert.equal(readAccessControlEvent({ Code: 'AccessControl', UserID: '1', Status: 'Failure' }, config).successful, false);
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

test('by default, picks up the real terminal\'s own Type field ("Entry"/"Exit") for direction', () => {
  const event = readAccessControlEvent({ 'EventBaseInfo.Code': 'AccessControl', UserID: '1', Type: 'Entry', Door: '0' }, baseConfig());

  assert.equal(event.directionFieldValue, 'Entry');
});

test('falls back through the default direction-field candidates in order', () => {
  const withoutType = readAccessControlEvent({ Code: 'AccessControl', UserID: '1', Door: '9', ReaderID: '3' }, baseConfig());
  assert.equal(withoutType.directionFieldValue, '9');

  const onlyReaderId = readAccessControlEvent({ Code: 'AccessControl', UserID: '1', ReaderID: '3' }, baseConfig());
  assert.equal(onlyReaderId.directionFieldValue, '3');
});
