const FAILURE_VALUES = new Set(['failure', 'fail', 'false', '0', 'abnormal', 'denied', 'reject', 'invalid']);

/**
 * Reads one parsed event group (see eventTextParser.js) as a Dahua access
 * control scan, or returns `null` if it isn't one — a terminal on the
 * same subscription can also report other event types (motion, video
 * loss, ...), which this bridge has no use for and must not mistake for
 * an attendance scan.
 *
 * The exact field names Dahua uses for the event type, the person id, and
 * the success/failure status are not identical across every terminal
 * model, so all three are resolved through the configurable, ordered
 * candidate lists in config.js rather than a single hardcoded key —
 * `LOG_RAW_EVENTS=true` is how a real device's actual field names get
 * confirmed on-site.
 */
export function readAccessControlEvent(fields, config) {
  const code = firstPresentField(fields, config.codeFields);

  if ((code || '').toLowerCase() !== 'accesscontrol') {
    return null;
  }

  return readAccessRecord(fields, config);
}

/**
 * Same field resolution as readAccessControlEvent, minus the Code check —
 * for a record read from recordFinder.cgi (offline/historical access
 * records), which has no Code field at all because the query itself
 * (`name=AccessControlCardRec`) already guarantees every record returned
 * is one. Confirmed live: a real record carries the same flat field
 * names as a live event (UserID, Status, ErrorCode, Type, Door, ...), so
 * the same candidate lists apply unchanged.
 */
export function readAccessRecord(fields, config) {
  const personId = firstPresentField(fields, config.personIdFields);

  if (!personId) {
    return { personId: null, successful: true, directionFieldValue: undefined, raw: fields };
  }

  return {
    personId,
    successful: isSuccessful(fields),
    directionFieldValue: firstPresentField(fields, config.direction.fields),
    raw: fields,
  };
}

/**
 * The event/record's own reported time (Unix epoch seconds), preferred
 * over "whenever this bridge happened to process it" — essential for a
 * backfilled record (which is by definition processed long after it
 * happened) and more accurate even for a live event. `undefined` when
 * none of the candidate fields are present, so the caller can fall back
 * to "now" for a genuinely live event with no time field at all.
 */
export function resolveEventTime(fields, config) {
  return firstPresentField(fields, config.timeFields);
}

/**
 * `ErrorCode`, when present, is trusted first: 0 means no error, matching
 * the same convention Dahua's other HTTP APIs use. Two real examples
 * confirmed this live: a "no matching person" scan (`ErrorCode: 16`,
 * empty UserID, `Status: 0`) and, from recordFinder, a genuine recognized
 * entry (`ErrorCode: 0`, a real UserID and CardName, `Status: 1`) —
 * consistent with 0/absent-vs-nonzero being the real signal and `Status`
 * being numeric on this model, not the word "Success"/"Failure" the
 * vendor's documentation excerpt implied. `Status`'s own known-failure
 * values are the fallback for an event with no `ErrorCode` at all.
 */
function isSuccessful(fields) {
  const errorCode = fields.ErrorCode;

  if (errorCode !== undefined && errorCode.trim() !== '') {
    const numeric = Number(errorCode);

    if (Number.isFinite(numeric)) {
      return numeric === 0;
    }
  }

  const statusValue = fields['AccessControl.Status'] ?? fields.Status;

  return statusValue === undefined ? true : !isKnownFailure(statusValue);
}

function firstPresentField(fields, candidateKeys) {
  for (const key of candidateKeys) {
    if (fields[key]) {
      return fields[key];
    }
  }

  return undefined;
}

function isKnownFailure(statusValue) {
  const normalized = statusValue.trim().toLowerCase();

  // An unrecognized status value is treated as success (permissive
  // default): a real device value this bridge doesn't yet know about
  // should still record attendance rather than silently drop it.
  return FAILURE_VALUES.has(normalized);
}
