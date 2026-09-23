/**
 * my.urtg.uz stores and reads attendance times as naive "Y-m-d H:i:s"
 * strings with no UTC marker, always meaning Asia/Tashkent wall-clock
 * time — confirmed directly against the backend (`config('app.timezone')
 * === 'Asia/Tashkent'`, and `Carbon::parse('...Z')` keeps the string's own
 * 'Z'/UTC timezone instead of converting it, so a UTC-labelled ISO string
 * lands in the database still reading its UTC wall-clock digits, five
 * hours behind the real Tashkent time). This bridge must therefore never
 * send a 'Z'-suffixed or otherwise offset-aware timestamp — only this
 * naive local form.
 *
 * The +5:00 offset is fixed, not looked up from a timezone database:
 * Uzbekistan has used a single UTC+5 offset year-round (no DST) since
 * 1992, so a constant is both correct and avoids relying on the
 * runtime's own IANA tzdata or ICU locale data being complete — the same
 * category of problem `Intl.DateTimeFormat('uz', ...)` already turned out
 * to have gaps in for this project (see the web dashboard's date
 * formatting notes).
 */
const TASHKENT_OFFSET_MS = 5 * 60 * 60 * 1000;

/**
 * @param {Date} date  An absolute instant (a `Date` is always UTC-based
 *   internally regardless of the process's own local timezone).
 * @returns {string} "Y-m-d H:i:s" in Asia/Tashkent wall-clock time.
 */
export function toTashkentDateTimeString(date) {
  const shifted = new Date(date.getTime() + TASHKENT_OFFSET_MS);
  const pad = (n) => String(n).padStart(2, '0');

  return (
    `${shifted.getUTCFullYear()}-${pad(shifted.getUTCMonth() + 1)}-${pad(shifted.getUTCDate())} ` +
    `${pad(shifted.getUTCHours())}:${pad(shifted.getUTCMinutes())}:${pad(shifted.getUTCSeconds())}`
  );
}

/**
 * The device reports event/record times as Unix epoch seconds (confirmed
 * live: a captured record's CreateTime converted this way landed exactly
 * on the same wall-clock minute embedded in that same record's own
 * snapshot file path).
 *
 * @param {string|number} epochSeconds
 */
export function epochSecondsToTashkentDateTimeString(epochSeconds) {
  return toTashkentDateTimeString(new Date(Number(epochSeconds) * 1000));
}
