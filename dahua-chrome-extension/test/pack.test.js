import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { test } from 'node:test';

import { buildZip, OUTPUT } from '../scripts/pack.mjs';

test('the zip the web app offers matches the extension source (run `npm run pack` if not)', () => {
  assert.ok(existsSync(OUTPUT), `${OUTPUT} is missing — run npm run pack`);
  assert.ok(readFileSync(OUTPUT).equals(buildZip().zip), 'frontend/public/downloads/dahua-davomat.zip is out of date — run npm run pack');
});

test('packs only what Chrome loads, under one folder', () => {
  const { paths } = buildZip();

  assert.ok(paths.includes('manifest.json'));
  assert.ok(paths.includes('src/background.js'));
  assert.ok(paths.includes('icons/icon128.png'));
  assert.ok(paths.every((path) => /^(manifest\.json|src\/|icons\/)/.test(path)), 'no tests, README or package.json');
});
