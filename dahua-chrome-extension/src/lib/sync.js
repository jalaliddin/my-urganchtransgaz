import { DeviceError, fetchEnrolledPeople, findAccessRecords } from './dahua.js';
import { tashkentMidnight, tashkentToday, toAttendanceEvent } from './records.js';
import { sendAttendanceEvent, sendRoster } from './webhook.js';

/**
 * One sync run: pull every enabled terminal's new offline records into a
 * persistent queue, then deliver the queue to my.urtg.uz. Everything it
 * needs from the outside (storage, fetch, the clock) is passed in, so the
 * same code runs in the extension's service worker and under `node --test`.
 *
 * Storage keys (chrome.storage.local):
 *   settings  {serverUrl, intervalMinutes, devices: [{id, name, host, port,
 *             username, password, deviceId, deviceToken, direction,
 *             enabled, syncFrom}]}
 *   state     {[device.id]: {cursor, lastSyncAt, lastError, blocked,
 *             today: {date, delivered, rejected}, unknown: {[personId]: …},
 *             rosterSyncedAt, rosterSize, rosterComplete, rosterError}}
 *   queue     [{key, deviceRef, personId, cardName, direction, eventTime}]
 *   log       newest-first activity lines for the popup
 */

export const PAGE_SIZE = 500;
// Bounds one run (the service worker may be stopped at any time); the
// cursor is saved after every page, so the next run carries on from there.
export const MAX_PAGES_PER_RUN = 20;
const MAX_QUEUE = 20000;
// Who is enrolled changes rarely; send the list this often (and on
// "Hozir sinxronlash").
export const ROSTER_INTERVAL_MS = 30 * 60 * 1000;
const MAX_LOG = 60;
const SAVE_EVERY = 25;

export const DEFAULT_SETTINGS = {
  serverUrl: 'https://my.urtg.uz/api/v1',
  intervalMinutes: 2,
  devices: [],
};

export async function loadAll(store) {
  const data = await store.get(['settings', 'state', 'queue', 'log']);

  return {
    settings: { ...DEFAULT_SETTINGS, ...(data.settings ?? {}) },
    state: data.state ?? {},
    queue: data.queue ?? [],
    log: data.log ?? [],
  };
}

function deviceState(state, deviceRef, nowMs) {
  const today = tashkentToday(nowMs);
  const current = state[deviceRef] ?? {};
  const counters = current.today?.date === today ? current.today : { date: today, delivered: 0, rejected: 0 };
  state[deviceRef] = { unknown: {}, ...current, today: counters };

  return state[deviceRef];
}

function addLog(log, nowMs, level, message) {
  log.unshift({ at: nowMs, level, message });
  log.length = Math.min(log.length, MAX_LOG);
}

/** Has this record already been taken? Records come oldest-first. */
function isSeen(record, cursor) {
  const time = Number(record.CreateTime);
  const recNo = Number(record.RecNo);

  if (time < cursor.time) return true;
  if (time > cursor.time) return false;

  // Same second as the last one taken: RecNo (the terminal's own
  // increasing record number) tells which of them were already handled.
  return Number.isFinite(recNo) && recNo <= cursor.recNo;
}

/**
 * Pulls one terminal's records since its cursor. The terminal answers at
 * most `count` records per request and reports no total, so a full page
 * means "there may be more": ask again from the last record's time, and
 * skip the overlap by the cursor.
 */
export async function pullDevice(device, ctx) {
  const { state, queue, log, persist, now, fetchImpl } = ctx;
  const ds = deviceState(state, device.id, now());
  const cursor = ds.cursor ?? {
    time: tashkentMidnight(device.syncFrom || tashkentToday(now())),
    recNo: -1,
  };
  const endTime = Math.floor(now() / 1000);
  const queuedKeys = new Set(queue.map((event) => event.key));
  let added = 0;
  let start = cursor.time;

  for (let page = 0; page < MAX_PAGES_PER_RUN; page++) {
    const { records } = await findAccessRecords(device, { startTime: start, endTime, count: PAGE_SIZE }, fetchImpl);

    let queueFull = false;

    for (const record of records) {
      if (isSeen(record, cursor)) continue;

      const event = toAttendanceEvent(record, device);
      if (event && !queuedKeys.has(event.key)) {
        if (queue.length >= MAX_QUEUE) {
          // The server has been unreachable for a long time. Stop here
          // without moving the cursor; these records stay on the terminal.
          queueFull = true;
          break;
        }
        queue.push(event);
        queuedKeys.add(event.key);
        added++;
      }

      const time = Number(record.CreateTime);
      if (Number.isFinite(time)) {
        cursor.recNo = time > cursor.time ? Number(record.RecNo) : Math.max(cursor.recNo, Number(record.RecNo));
        cursor.time = time;
      }
    }

    ds.cursor = { ...cursor };
    await persist();

    if (queueFull || records.length < PAGE_SIZE) break;

    const lastTime = Number(records.at(-1).CreateTime);
    if (!(lastTime > start)) {
      // A whole page within one second can't be paged past by time.
      addLog(log, now(), 'warn', `${device.name}: bir soniyada ${PAGE_SIZE} dan ortiq yozuv — qolgani o'tkazib yuborildi.`);
      cursor.time = start + 1;
      cursor.recNo = -1;
      ds.cursor = { ...cursor };
      await persist();
      break;
    }
    start = lastTime;
  }

  ds.lastSyncAt = now();
  ds.lastError = null;

  return added;
}

/**
 * Sends the terminal's enrolled people to the server for its terminals
 * page. Separate from the scans: a failure here is noted on its own and
 * never stops attendance from flowing.
 */
export async function syncRoster(device, settings, ctx, { force = false } = {}) {
  const { state, now, fetchImpl } = ctx;
  const ds = deviceState(state, device.id, now());

  if (ds.blocked || (!force && ds.rosterSyncedAt && now() - ds.rosterSyncedAt < ROSTER_INTERVAL_MS)) return;

  try {
    const roster = await fetchEnrolledPeople(device, fetchImpl);
    const result = await sendRoster(settings.serverUrl, device, roster, fetchImpl);

    if (!result.ok) {
      ds.rosterError = `Ro'yxat yuborilmadi (${result.status ?? result.error})`;
      return;
    }

    Object.assign(ds, { rosterSyncedAt: now(), rosterSize: roster.users.length, rosterComplete: roster.complete, rosterError: null });
  } catch (error) {
    ds.rosterError = error.message;
  }
}

/** Delivers the queue oldest-first; stops at the first sign the server is down. */
export async function deliverQueue(settings, ctx) {
  const { state, log, persist, now, fetchImpl } = ctx;
  const devices = new Map(settings.devices.map((device) => [device.id, device]));
  const blocked = new Set(settings.devices.filter((device) => state[device.id]?.blocked).map((device) => device.id));
  const remaining = [];
  let sent = 0;
  let serverDown = false;

  for (const [index, event] of ctx.queue.entries()) {
    const device = devices.get(event.deviceRef);

    if (!device) continue; // its terminal was removed from the settings
    if (serverDown || blocked.has(device.id)) {
      remaining.push(event);
      continue;
    }

    const result = await sendAttendanceEvent(settings.serverUrl, device, event, fetchImpl);
    const ds = deviceState(state, device.id, now());

    if (result.outcome === 'delivered') {
      ds.today.delivered++;
      ds.lastDeliveredAt = now();
    } else if (result.outcome === 'rejected') {
      ds.today.rejected++;
      if (result.unknownPerson) {
        const known = ds.unknown[event.personId];
        ds.unknown[event.personId] = { name: event.cardName, count: (known?.count ?? 0) + 1, lastSeen: event.eventTime };
      } else {
        addLog(log, now(), 'warn', `${device.name}: ${event.personId} qabul qilinmadi (${result.status} ${result.message ?? ''})`.trim());
      }
    } else if (result.outcome === 'auth') {
      blocked.add(device.id);
      ds.blocked = true;
      ds.lastError = result.message
        ? `Server rad etdi: ${result.message}`
        : "Qurilma tokeni noto'g'ri yoki qurilma o'chirilgan — sozlamalarni tekshiring.";
      addLog(log, now(), 'error', `${device.name}: ${ds.lastError}`);
      remaining.push(event);
    } else {
      serverDown = true;
      addLog(log, now(), 'warn', `Serverga ulanib bo'lmadi (${result.status ?? result.error}) — keyinroq qayta yuboriladi.`);
      remaining.push(event);
    }

    if (++sent % SAVE_EVERY === 0) {
      // Persist progress mid-way: what was delivered must not be resent
      // if the worker is stopped before the run ends.
      await persist({ queue: [...remaining, ...ctx.queue.slice(index + 1)] });
    }
  }

  ctx.queue.length = 0;
  ctx.queue.push(...remaining);

  return { sent, serverDown };
}

/**
 * The whole run. `store` is chrome.storage.local (or an in-memory stand-in):
 * get(keys) → object, set(object).
 */
export async function runSync({ store, fetchImpl = fetch, now = () => Date.now(), forceRoster = false }) {
  const { settings, state, queue, log } = await loadAll(store);
  const ctx = {
    state,
    queue,
    log,
    now,
    fetchImpl,
    persist: (overrides = {}) => store.set({ state, queue, log, ...overrides }),
  };
  let pulled = 0;

  for (const device of settings.devices.filter((d) => d.enabled !== false)) {
    try {
      pulled += await pullDevice(device, ctx);
    } catch (error) {
      const ds = deviceState(state, device.id, now());
      ds.lastError = error instanceof DeviceError ? error.message : `Xatolik: ${error.message}`;
      ds.lastErrorAt = now();
      addLog(log, now(), 'error', `${device.name}: ${ds.lastError}`);
      continue;
    }

    await syncRoster(device, settings, ctx, { force: forceRoster });
  }

  const delivery = await deliverQueue(settings, ctx);
  if (pulled > 0 || delivery.sent > 0) {
    addLog(log, now(), 'info', `Yangi yozuvlar: ${pulled}, serverga yuborildi: ${delivery.sent}, navbatda: ${queue.length}.`);
  }
  await store.set({ state, queue, log, lastRunAt: now() });

  return { pulled, sent: delivery.sent, queued: queue.length, serverDown: delivery.serverDown };
}
