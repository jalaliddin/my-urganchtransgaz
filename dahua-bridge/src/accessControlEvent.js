const FAILURE_VALUES = new Set(['failure', 'fail', 'false', '0', 'abnormal', 'denied', 'reject', 'invalid']);

/**
 * Reads one parsed event group (see eventTextParser.js) as a Dahua access
 * control scan, or returns `null` if it isn't one — a terminal on the
 * same subscription can also report other event types (motion, video
 * loss, ...), which this bridge has no use for and must not mistake for
 * an attendance scan.
 *
 * The exact field names Dahua uses for the person id and the
 * success/failure status are not identical across every terminal model,
 * so both are resolved through the configurable, ordered candidate lists
 * in config.js rather than a single hardcoded key — `LOG_RAW_EVENTS=true`
 * is how a real device's actual field names get confirmed on-site.
 */
export function readAccessControlEvent(fields, config) {
  if ((fields.Code || '').toLowerCase() !== 'accesscontrol') {
    return null;
  }

  const personId = firstPresentField(fields, config.personIdFields);

  if (!personId) {
    return { personId: null, successful: true, directionFieldValue: undefined, raw: fields };
  }

  const statusValue = fields['AccessControl.Status'] ?? fields.Status;
  const successful = statusValue === undefined ? true : !isKnownFailure(statusValue);

  return {
    personId,
    successful,
    directionFieldValue: fields[config.direction.field],
    raw: fields,
  };
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
