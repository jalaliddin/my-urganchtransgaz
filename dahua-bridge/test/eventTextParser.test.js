import assert from 'node:assert/strict';
import { test } from 'node:test';

import { parseEventText } from '../src/eventTextParser.js';

test('recognizes a bare "Heartbeat" body', () => {
  const result = parseEventText('Heartbeat');

  assert.equal(result.heartbeat, true);
  assert.deepEqual(result.events, []);
});

test('groups dotted Events[n].* keys into one object per index', () => {
  const text = [
    'Events[0].Code=AccessControl',
    'Events[0].AccessControl.UserID=1001',
    'Events[0].AccessControl.Status=Success',
    'Events[1].Code=AccessControl',
    'Events[1].AccessControl.UserID=1002',
  ].join('\r\n');

  const { heartbeat, events } = parseEventText(text);

  assert.equal(heartbeat, false);
  assert.equal(events.length, 2);
  assert.deepEqual(events[0], { Code: 'AccessControl', 'AccessControl.UserID': '1001', 'AccessControl.Status': 'Success' });
  assert.deepEqual(events[1], { Code: 'AccessControl', 'AccessControl.UserID': '1002' });
});

test('also groups the ReturnEvents[n].* prefix used in some responses', () => {
  const { events } = parseEventText('ReturnEvents[0].Code=TrafficJunction\r\nReturnEvents[0].TrafficCar.PlateNumber=Z A12345');

  assert.deepEqual(events, [{ Code: 'TrafficJunction', 'TrafficCar.PlateNumber': 'Z A12345' }]);
});

test('tolerates stray non-KV lines and a leading/trailing space around values', () => {
  const { events } = parseEventText('Events[0].Code=AccessControl\r\nSuccess……\r\nEvents[0].Data.PTS= 42949485818.0');

  assert.deepEqual(events, [{ Code: 'AccessControl', 'Data.PTS': '42949485818.0' }]);
});

test('ignores fields with no Events[n]/ReturnEvents[n] prefix', () => {
  const { events } = parseEventText('Code=AccessControl\r\nEvents[0].Code=AccessControl');

  assert.deepEqual(events, [{ Code: 'AccessControl' }]);
});

test('an empty body yields no events and is not treated as a heartbeat', () => {
  const { heartbeat, events } = parseEventText('   \r\n  ');

  assert.equal(heartbeat, false);
  assert.deepEqual(events, []);
});
