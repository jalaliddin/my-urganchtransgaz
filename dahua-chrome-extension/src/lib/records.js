/**
 * One offline record from recordFinder.cgi → one attendance event for
 * my.urtg.uz, or null when it isn't one (a failed/unrecognized scan).
 * Same rules as dahua-bridge/src/accessControlEvent.js, which were
 * confirmed against this site's real terminals.
 */

const TASHKENT_OFFSET_MS = 5 * 60 * 60 * 1000;
const FAILURE_VALUES = new Set(['failure', 'fail', 'false', '0', 'abnormal', 'denied', 'reject', 'invalid']);

/**
 * my.urtg.uz reads times as naive "Y-m-d H:i:s" Asia/Tashkent wall-clock —
 * never send a UTC/offset string. Uzbekistan is UTC+5 all year (no DST
 * since 1992), so a fixed offset is exact.
 */
export function toTashkentDateTime(epochSeconds) {
  const shifted = new Date(Number(epochSeconds) * 1000 + TASHKENT_OFFSET_MS);
  const pad = (n) => String(n).padStart(2, '0');

  return (
    `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}-${pad(shifted.getUTCDate())} ` +
    `${pad(shifted.getUTCHours())}:${pad(shifted.getUTCMinutes())}:${pad(shifted.getUTCSeconds())}`
  );
}

/** Epoch seconds of 00:00 Tashkent time on `yyyy-mm-dd`. */
export function tashkentMidnight(date) {
  const [year, month, day] = date.split('-').map(Number);

  return (Date.UTC(year, month - 1, day) - TASHKENT_OFFSET_MS) / 1000;
}

/** Today's date in Tashkent, `yyyy-mm-dd`. */
export function tashkentToday(nowMs) {
  return toTashkentDateTime(nowMs / 1000).slice(0, 10);
}

/**
 * `ErrorCode` decides first (0 = no error): a "nobody matched" scan comes
 * back as ErrorCode 16 with an empty UserID. `Status` is the fallback for a
 * model that sends no ErrorCode (numeric on these terminals: 1 ok, 0 not).
 */
export function isSuccessful(record) {
  const errorCode = record.ErrorCode?.trim();

  if (errorCode) {
    const numeric = Number(errorCode);
    if (Number.isFinite(numeric)) return numeric === 0;
  }

  const status = record.Status?.trim().toLowerCase();

  return status === undefined || status === '' || !FAILURE_VALUES.has(status);
}

/**
 * @param {'check_in'|'check_out'|'type'} mode  A terminal that only ever
 *   sees one direction (this site: one entry gate, one exit gate), or
 *   "type" to trust the record's own Type=Entry/Exit.
 */
export function resolveDirection(record, mode) {
  if (mode === 'check_in' || mode === 'check_out') return mode;

  const type = record.Type?.trim().toLowerCase();
  if (type === 'entry') return 'check_in';
  if (type === 'exit') return 'check_out';

  return null;
}

/**
 * @returns {{key: string, deviceRef: string, personId: string, cardName: string,
 *   direction: string, eventTime: string} | null}
 */
export function toAttendanceEvent(record, device) {
  const personId = record.UserID?.trim() || record.CardNo?.trim();
  const createTime = Number(record.CreateTime);

  if (!personId || !Number.isFinite(createTime) || !isSuccessful(record)) return null;

  const direction = resolveDirection(record, device.direction);
  if (!direction) return null;

  return {
    key: `${device.id}:${record.RecNo ?? createTime}:${personId}`,
    deviceRef: device.id,
    personId,
    cardName: record.CardName?.trim() ?? '',
    direction,
    eventTime: toTashkentDateTime(createTime),
  };
}
