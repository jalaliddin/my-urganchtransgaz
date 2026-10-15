import assert from 'node:assert/strict';
import { test } from 'node:test';

import { isSuccessful, tashkentMidnight, toAttendanceEvent, toTashkentDateTime } from '../src/lib/records.js';

const device = { id: 'dev-1', direction: 'check_in' };

test('converts the terminal\'s epoch seconds to naive Tashkent time', () => {
  // A real record whose own snapshot path was ".../2026-09-16/15/34/...".
  assert.equal(toTashkentDateTime('1789554842'), '2026-09-16 15:34:02');
  assert.equal(toTashkentDateTime(tashkentMidnight('2026-09-16')), '2026-09-16 00:00:00');
});

test('a recognized scan becomes an attendance event in the terminal\'s direction', () => {
  const event = toAttendanceEvent(
    { RecNo: '550478', CreateTime: '1789554842', ErrorCode: '0', Status: '1', Type: 'Entry', UserID: '108', CardName: 'Kenjayev N' },
    { id: 'dev-2', direction: 'check_out' },
  );

  assert.deepEqual(event, {
    key: 'dev-2:550478:108',
    deviceRef: 'dev-2',
    personId: '108',
    cardName: 'Kenjayev N',
    direction: 'check_out',
    eventTime: '2026-09-16 15:34:02',
  });
});

test('a "nobody matched" scan is not attendance', () => {
  // Real shape: ErrorCode 16, empty UserID, Status 0.
  assert.equal(toAttendanceEvent({ RecNo: '1', CreateTime: '1789554842', ErrorCode: '16', Status: '0', UserID: '' }, device), null);
  assert.equal(isSuccessful({ ErrorCode: '16', Status: '1' }), false, 'ErrorCode decides first');
  assert.equal(isSuccessful({ Status: '0' }), false);
  assert.equal(isSuccessful({ Status: '1' }), true);
});

test('in "type" mode the record\'s own Entry/Exit decides, and a record without one is skipped', () => {
  const typed = { id: 'dev-1', direction: 'type' };
  const base = { RecNo: '1', CreateTime: '1789554842', ErrorCode: '0', UserID: '7' };

  assert.equal(toAttendanceEvent({ ...base, Type: 'Entry' }, typed).direction, 'check_in');
  assert.equal(toAttendanceEvent({ ...base, Type: 'Exit' }, typed).direction, 'check_out');
  assert.equal(toAttendanceEvent(base, typed), null);
});
