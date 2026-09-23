import assert from 'node:assert/strict';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { test } from 'node:test';

import { loadConfig } from '../src/config.js';
import { DirectionResolver } from '../src/directionResolver.js';

function tempConfig(overrides = {}) {
  const dataDir = mkdtempSync(join(tmpdir(), 'dahua-bridge-test-'));
  const config = loadConfig({
    DEVICE_HOST: 'h', DEVICE_USERNAME: 'u', DEVICE_PASSWORD: 'p',
    SERVER_URL: 'http://x', DEVICE_TOKEN: 't', DEVICE_ID: 'd',
    DATA_DIR: dataDir,
    ...overrides,
  });

  return { config, dataDir };
}

test('toggle mode: alternates check_in/check_out per person', () => {
  const { config, dataDir } = tempConfig();
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A' }), 'check_out');
  assert.equal(resolver.resolve({ personId: 'A' }), 'check_in');

  rmSync(dataDir, { recursive: true, force: true });
});

test('toggle mode: tracks each person independently', () => {
  const { config, dataDir } = tempConfig();
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'B' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A' }), 'check_out');
  assert.equal(resolver.resolve({ personId: 'B' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});

test('toggle mode: state survives being reloaded from disk (a bridge restart)', () => {
  const { config, dataDir } = tempConfig();

  new DirectionResolver(config).resolve({ personId: 'A' }); // check_in, then discarded
  const secondInstance = new DirectionResolver(config);

  assert.equal(secondInstance.resolve({ personId: 'A' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});

test('field mode: uses the configured field\'s value when present', () => {
  const { config, dataDir } = tempConfig({
    DIRECTION_MODE: 'field', DIRECTION_FIELD: 'AccessControl.Door', ENTRY_VALUES: '1', EXIT_VALUES: '2',
  });
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: '1' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: '2' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});

test('field mode: the default candidates work with a real terminal\'s own Type=Entry/Exit values, unconfigured', () => {
  const { config, dataDir } = tempConfig({ DIRECTION_MODE: 'field' });
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: 'Entry' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: 'Exit' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});

test('fixed mode: always returns the configured direction, never toggling', () => {
  const { config, dataDir } = tempConfig({ DIRECTION_MODE: 'fixed', FIXED_DIRECTION: 'check_in' });
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A' }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'B' }), 'check_in');

  rmSync(dataDir, { recursive: true, force: true });
});

test('fixed mode: the other direction works the same way', () => {
  const { config, dataDir } = tempConfig({ DIRECTION_MODE: 'fixed', FIXED_DIRECTION: 'check_out' });
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});

test('field mode: falls back to toggling when the field is missing or unrecognized', () => {
  const { config, dataDir } = tempConfig({ DIRECTION_MODE: 'field' });
  const resolver = new DirectionResolver(config);

  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: undefined }), 'check_in');
  assert.equal(resolver.resolve({ personId: 'A', directionFieldValue: 'unrecognized' }), 'check_out');

  rmSync(dataDir, { recursive: true, force: true });
});
