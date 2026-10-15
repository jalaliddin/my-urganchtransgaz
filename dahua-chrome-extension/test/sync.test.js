import assert from 'node:assert/strict';
import { test } from 'node:test';

import { tashkentMidnight } from '../src/lib/records.js';
import { PAGE_SIZE, runSync } from '../src/lib/sync.js';

const DAY = '2026-10-14';
const DAY_START = tashkentMidnight(DAY);
const NOW_MS = (DAY_START + 18 * 3600) * 1000; // 18:00 Tashkent

function memoryStore(initial = {}) {
  const data = structuredClone(initial);

  return {
    data,
    async get(keys) {
      return Object.fromEntries([keys].flat().filter((key) => key in data).map((key) => [key, structuredClone(data[key])]));
    },
    async set(items) {
      for (const [key, value] of Object.entries(items)) data[key] = structuredClone(value);
    },
  };
}

function scan(recNo, secondsIntoDay, userId, extra = {}) {
  return { RecNo: String(recNo), CreateTime: String(DAY_START + secondsIntoDay), ErrorCode: '0', Status: '1', Type: 'Entry', UserID: userId, CardName: `Xodim ${userId}`, ...extra };
}

/**
 * A terminal as recordFinder.cgi behaves on this site: records oldest
 * first, StartTime/EndTime inclusive, at most `count` per answer, no total.
 */
function fakeTerminal(host, records) {
  const queries = [];

  return {
    host,
    queries,
    async handle(url) {
      const start = Number(url.searchParams.get('StartTime'));
      const end = Number(url.searchParams.get('EndTime'));
      const count = Number(url.searchParams.get('count'));
      queries.push({ start, end, count });
      const page = records.filter((r) => Number(r.CreateTime) >= start && Number(r.CreateTime) <= end).slice(0, count);
      const lines = [`found=${page.length}`];
      page.forEach((record, i) => Object.entries(record).forEach(([k, v]) => lines.push(`records[${i}].${k}=${v}`)));

      return new Response(lines.join('\r\n'), { status: 200 });
    },
  };
}

/** my.urtg.uz's webhook: knows some Dahua IDs, accepts some tokens. */
function fakeServer({ knownPeople, tokens, down = false }) {
  const received = [];

  return {
    received,
    down,
    async handle(init) {
      if (this.down) throw new TypeError('fetch failed');

      const token = init.headers.Authorization.replace('Bearer ', '');
      const body = JSON.parse(init.body);
      if (!(token in tokens)) return Response.json({ message: 'Unauthenticated.' }, { status: 401 });
      if (tokens[token] !== body.device_id) {
        return Response.json({ message: 'device_id does not match the authenticated device.' }, { status: 422 });
      }
      if (!knownPeople.includes(body.dahua_person_id)) {
        return Response.json({ message: 'invalid', errors: { dahua_person_id: ['invalid'] } }, { status: 422 });
      }
      received.push(body);

      return Response.json({ success: true }, { status: 201 });
    },
  };
}

function world({ terminals, server }) {
  const byHost = new Map(terminals.map((t) => [t.host, t]));

  return async (input, init = {}) => {
    const url = new URL(input);
    if (url.hostname === 'my.urtg.uz') return server.handle(init);

    const terminal = byHost.get(url.hostname);
    if (!terminal) throw new TypeError('fetch failed');

    return terminal.handle(url);
  };
}

function device(id, host, overrides = {}) {
  return {
    id,
    name: id,
    host,
    port: 80,
    username: 'admin',
    password: 'x',
    deviceId: id.toUpperCase(),
    deviceToken: `token-${id}`,
    direction: 'check_in',
    enabled: true,
    syncFrom: DAY,
    ...overrides,
  };
}

function settingsWith(...devices) {
  return { settings: { serverUrl: 'https://my.urtg.uz/api/v1', intervalMinutes: 2, devices } };
}

test('pulls the day\'s recognized scans, records them, and never sends them again', async () => {
  const entry = fakeTerminal('10.0.0.1', [
    scan(1, 8 * 3600, '101'),
    scan(2, 8 * 3600 + 5, '', { ErrorCode: '16', Status: '0' }), // nobody matched
    scan(3, 9 * 3600, '102'),
  ]);
  const server = fakeServer({ knownPeople: ['101', '102'], tokens: { 'token-gate': 'GATE' } });
  const store = memoryStore(settingsWith(device('gate', '10.0.0.1')));
  const fetchImpl = world({ terminals: [entry], server });

  const first = await runSync({ store, fetchImpl, now: () => NOW_MS });

  assert.deepEqual(first, { pulled: 2, sent: 2, queued: 0, serverDown: false });
  assert.deepEqual(server.received, [
    { device_id: 'GATE', dahua_person_id: '101', event_type: 'check_in', event_time: `${DAY} 08:00:00` },
    { device_id: 'GATE', dahua_person_id: '102', event_type: 'check_in', event_time: `${DAY} 09:00:00` },
  ]);
  assert.equal(entry.queries[0].start, DAY_START, 'starts at midnight of the chosen day');
  assert.equal(store.data.state.gate.today.delivered, 2);

  const second = await runSync({ store, fetchImpl, now: () => NOW_MS + 60_000 });
  assert.equal(second.pulled, 0);
  assert.equal(server.received.length, 2);
});

test('pages past the terminal\'s per-request limit without losing or repeating a scan', async () => {
  // More than two pages, with several scans sharing a second exactly at a
  // page boundary — the terminal repeats that second on the next page.
  const records = [];
  for (let i = 0; i < PAGE_SIZE * 2 + 37; i++) records.push(scan(1000 + i, 3600 + Math.floor(i / 3), String(i % 50)));
  const terminal = fakeTerminal('10.0.0.1', records);
  const server = fakeServer({ knownPeople: Array.from({ length: 50 }, (_, i) => String(i)), tokens: { 'token-gate': 'GATE' } });
  const store = memoryStore(settingsWith(device('gate', '10.0.0.1')));

  const result = await runSync({ store, fetchImpl: world({ terminals: [terminal], server }), now: () => NOW_MS });

  assert.equal(result.pulled, records.length);
  assert.equal(server.received.length, records.length);
  assert.equal(new Set(server.received.map((e) => `${e.dahua_person_id}@${e.event_time}`)).size, records.length);
  assert.ok(terminal.queries.length >= 3, 'asked again after each full page');
});

test('an ID no employee has is dropped and listed for HR, the rest still recorded', async () => {
  const terminal = fakeTerminal('10.0.0.1', [scan(1, 3600, '101'), scan(2, 3700, '999'), scan(3, 3800, '999')]);
  const server = fakeServer({ knownPeople: ['101'], tokens: { 'token-gate': 'GATE' } });
  const store = memoryStore(settingsWith(device('gate', '10.0.0.1')));

  const result = await runSync({ store, fetchImpl: world({ terminals: [terminal], server }), now: () => NOW_MS });

  assert.equal(result.queued, 0);
  assert.equal(server.received.length, 1);
  assert.deepEqual(store.data.state.gate.unknown['999'], { name: 'Xodim 999', count: 2, lastSeen: `${DAY} 01:03:20` });
  assert.equal(store.data.state.gate.today.rejected, 2);
});

test('a wrong token holds that terminal\'s scans without blocking the other terminal', async () => {
  const entry = fakeTerminal('10.0.0.1', [scan(1, 3600, '101')]);
  const exit = fakeTerminal('10.0.0.2', [scan(1, 7200, '101')]);
  const server = fakeServer({ knownPeople: ['101'], tokens: { 'token-exit': 'EXIT' } });
  const store = memoryStore(
    settingsWith(device('entry', '10.0.0.1'), device('exit', '10.0.0.2', { direction: 'check_out' })),
  );

  const result = await runSync({ store, fetchImpl: world({ terminals: [entry, exit], server }), now: () => NOW_MS });

  assert.equal(result.queued, 1, "the entry gate's scan waits for a fixed token");
  assert.deepEqual(server.received.map((e) => e.event_type), ['check_out']);
  assert.equal(store.data.state.entry.blocked, true);
  assert.match(store.data.state.entry.lastError, /token/i);
});

test('a token for a different device_id holds the scans instead of dropping them', async () => {
  const terminal = fakeTerminal('10.0.0.1', [scan(1, 3600, '101')]);
  const server = fakeServer({ knownPeople: ['101'], tokens: { 'token-gate': 'SOMETHING-ELSE' } });
  const store = memoryStore(settingsWith(device('gate', '10.0.0.1')));

  const result = await runSync({ store, fetchImpl: world({ terminals: [terminal], server }), now: () => NOW_MS });

  assert.equal(result.queued, 1);
  assert.match(store.data.state.gate.lastError, /device_id/);
});

test('scans wait in the queue while the server is unreachable and go out once it is back', async () => {
  const terminal = fakeTerminal('10.0.0.1', [scan(1, 3600, '101'), scan(2, 3700, '102')]);
  const server = fakeServer({ knownPeople: ['101', '102'], tokens: { 'token-gate': 'GATE' }, down: true });
  const store = memoryStore(settingsWith(device('gate', '10.0.0.1')));
  const fetchImpl = world({ terminals: [terminal], server });

  const offline = await runSync({ store, fetchImpl, now: () => NOW_MS });
  assert.deepEqual(offline, { pulled: 2, sent: 1, queued: 2, serverDown: true });

  server.down = false;
  const online = await runSync({ store, fetchImpl, now: () => NOW_MS + 120_000 });
  assert.deepEqual(online, { pulled: 0, sent: 2, queued: 0, serverDown: false });
  assert.equal(server.received.length, 2);
});

test('an unreachable terminal is reported and the other terminal carries on', async () => {
  const exit = fakeTerminal('10.0.0.2', [scan(1, 3600, '101')]);
  const server = fakeServer({ knownPeople: ['101'], tokens: { 'token-exit': 'EXIT' } });
  const store = memoryStore(
    settingsWith(device('entry', '10.0.0.9'), device('exit', '10.0.0.2', { direction: 'check_out' })),
  );

  const result = await runSync({ store, fetchImpl: world({ terminals: [exit], server }), now: () => NOW_MS });

  assert.equal(result.sent, 1);
  assert.match(store.data.state.entry.lastError, /ulanib bo'lmadi/);
  assert.equal(store.data.state.exit.lastError, null);
});
