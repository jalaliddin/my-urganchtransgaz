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
 * `ErrorCode`, when present, is trusted first: 0 means no error, matching
 * the same convention Dahua's other HTTP APIs use — and it is what
 * distinguished the one real "no matching person" scan seen live during
 * development (`ErrorCode: 16`, empty UserID) from what a genuine
 * recognized scan should look like. `Status`'s own known-failure values
 * are the fallback for an event with no `ErrorCode` at all.
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
