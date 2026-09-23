/**
 * Parses a `text/plain` part's body: a flat list of `Key=Value` lines
 * describing one or more events, e.g.
 *
 *   Events[0].Code=AccessControl
 *   Events[0].AccessControl.UserID=1001
 *   Events[0].AccessControl.Status=Success
 *
 * grouped here into one plain object per `Events[n]`/`ReturnEvents[n]`
 * index, keyed by whatever comes after the index (`Code`,
 * `AccessControl.UserID`, ...) — kept as a single dotted string key
 * rather than deep-nested, since every field this bridge reads is looked
 * up by its full dotted name anyway (see config.js's PERSON_ID_FIELDS).
 *
 * A body that is just the word "Heartbeat" (the device's keep-alive, sent
 * at the interval requested in the subscription URL) is reported
 * separately, not as an event group.
 */
export function parseEventText(text) {
  const trimmed = text.trim();

  if (trimmed === 'Heartbeat') {
    return { heartbeat: true, events: [] };
  }

  const groups = new Map();
  const indexPattern = /^(?:Events|ReturnEvents)\[(\d+)]\.(.+)$/;

  for (const rawLine of trimmed.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line) {
      continue;
    }

    const separatorIndex = line.indexOf('=');
    if (separatorIndex === -1) {
      continue; // stray non-KV line (some firmware/OCR dumps include a bare "Success" marker) — not an event field
    }

    const key = line.slice(0, separatorIndex).trim();
    const value = line.slice(separatorIndex + 1).trim();

    const match = indexPattern.exec(key);
    if (!match) {
      continue; // a top-level field outside any Events[n]/ReturnEvents[n] group — nothing here needs one
    }

    const [, indexText, field] = match;
    const index = Number(indexText);

    if (!groups.has(index)) {
      groups.set(index, {});
    }

    groups.get(index)[field] = value;
  }

  const events = [...groups.entries()]
    .sort(([a], [b]) => a - b)
    .map(([, fields]) => fields);

  return { heartbeat: false, events };
}
