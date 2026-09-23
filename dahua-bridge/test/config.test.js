import assert from 'node:assert/strict';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';

const validEnv = {
  DEVICE_HOST: '192.168.1.108', DEVICE_USERNAME: 'admin', DEVICE_PASSWORD: 'secret',
  SERVER_URL: 'https://my.urtg.uz/api/v1/', DEVICE_TOKEN: 'tok', DEVICE_ID: 'TURNSTILE-1',
};

test('loads sensible defaults from a minimal valid env', () => {
  const config = loadConfig(validEnv);

  assert.equal(config.device.host, '192.168.1.108');
  assert.equal(config.device.port, 80);
  assert.equal(config.server.url, 'https://my.urtg.uz/api/v1', 'trailing slash is stripped');
  assert.equal(config.direction.mode, 'toggle');
  assert.equal(config.dryRun, false);
  assert.deepEqual(config.personIdFields, ['AccessControl.UserID', 'AccessControl.CardNo', 'UserID', 'CardNo']);
});

for (const key of ['DEVICE_HOST', 'DEVICE_USERNAME', 'DEVICE_PASSWORD', 'SERVER_URL', 'DEVICE_TOKEN', 'DEVICE_ID']) {
  test(`throws a clear error when ${key} is missing`, () => {
    const env = { ...validEnv };
    delete env[key];

    assert.throws(() => loadConfig(env), new RegExp(key));
  });
}

test('rejects an unknown DIRECTION_MODE', () => {
  assert.throws(() => loadConfig({ ...validEnv, DIRECTION_MODE: 'sideways' }), /DIRECTION_MODE/);
});

test('parses comma-separated lists and booleans', () => {
  const config = loadConfig({
    ...validEnv,
    ENTRY_VALUES: '1, 3',
    EXIT_VALUES: '2,4',
    LOG_RAW_EVENTS: 'true',
    DRY_RUN: 'YES',
  });

  assert.deepEqual(config.direction.entryValues, ['1', '3']);
  assert.deepEqual(config.direction.exitValues, ['2', '4']);
  assert.equal(config.logRawEvents, true);
  assert.equal(config.dryRun, true);
});
