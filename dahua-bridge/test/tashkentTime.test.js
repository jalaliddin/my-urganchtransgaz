import assert from 'node:assert/strict';
import { test } from 'node:test';

import { epochSecondsToTashkentDateTimeString, toTashkentDateTimeString } from '../src/tashkentTime.js';

test('converts a UTC instant to naive Asia/Tashkent (+5) wall-clock text', () => {
  // Midday UTC lands at 17:00 in Tashkent, same calendar day.
  assert.equal(toTashkentDateTimeString(new Date('2026-09-23T12:00:00.000Z')), '2026-09-23 17:00:00');
});

test('crosses midnight into the next Tashkent calendar day when UTC is late enough', () => {
  // 20:30 UTC + 5h = 01:30 the next day in Tashkent.
  assert.equal(toTashkentDateTimeString(new Date('2026-09-23T20:30:00.000Z')), '2026-09-24 01:30:00');
});

test('pads single-digit month/day/hour/minute/second', () => {
  assert.equal(toTashkentDateTimeString(new Date('2026-01-02T01:02:03.000Z')), '2026-01-02 06:02:03');
});

test('epoch-seconds helper matches the real device value it was verified against', () => {
  // Captured live from a real Dahua terminal's recordFinder.cgi response:
  // CreateTime=1789554842 on a record whose own snapshot file path was
  // ".../2026-09-16/15/34/..." — the Tashkent hour:minute the device
  // itself recorded for that same scan.
  assert.equal(epochSecondsToTashkentDateTimeString('1789554842'), '2026-09-16 15:34:02');
});

test('epoch-seconds helper accepts a number as well as a numeric string', () => {
  assert.equal(epochSecondsToTashkentDateTimeString(1789554842), epochSecondsToTashkentDateTimeString('1789554842'));
});
