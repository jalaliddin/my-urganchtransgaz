import { readAccessControlEvent, resolveEventTime } from './accessControlEvent.js';
import { epochSecondsToTashkentDateTimeString, toTashkentDateTimeString } from './tashkentTime.js';
import { parseEventText } from './eventTextParser.js';

/**
 * Turns one multipart part from the device into zero or more delivered
 * (or queued) attendance events. Takes its collaborators as plain
 * objects/functions rather than importing them directly, so this — the
 * part with the actual business rules (which events count, dedupe,
 * direction, what dry-run/log-raw mean) — can be unit tested with fake
 * parts and no real device, queue, or network involved.
 */
export function createEventPipeline(config, { logger, dedupe, direction, queue, flush }) {
  return async function handlePart(part) {
    const contentType = part.headers.get('content-type') || '';

    if (!contentType.toLowerCase().startsWith('text/plain')) {
      return; // a JPEG snapshot or something else this bridge has no use for
    }

    const { heartbeat, events } = parseEventText(part.body.toString('utf8'));

    if (heartbeat) {
      logger.debug('Heartbeat received from device');

      return;
    }

    for (const fields of events) {
      if (config.logRawEvents) {
        logger.info('Raw event received', fields);
      }

      const accessEvent = readAccessControlEvent(fields, config);

      if (!accessEvent) {
        continue; // not an AccessControl event (motion, video loss, ...) — nothing to do with attendance
      }

      if (!accessEvent.personId) {
        logger.warn('An AccessControl event had no recognizable person-id field — check PERSON_ID_FIELDS against LOG_RAW_EVENTS output', { fields });
        continue;
      }

      if (!accessEvent.successful) {
        logger.debug('Ignoring a denied/failed access attempt', { personId: accessEvent.personId });
        continue;
      }

      if (!dedupe.shouldProcess(accessEvent.personId)) {
        logger.debug('Ignoring a repeat scan within the dedupe window', { personId: accessEvent.personId });
        continue;
      }

      // The device's own reported scan time when the event carries one
      // (it does on every model seen live so far) — never "now" labelled
      // as if it were UTC. my.urtg.uz stores this as naive Asia/Tashkent
      // wall-clock text with no timezone marker at all (see
      // tashkentTime.js); sending anything UTC-suffixed here previously
      // recorded every check-in/check-out five hours early.
      const deviceTime = resolveEventTime(fields, config);
      const eventTime = deviceTime
        ? epochSecondsToTashkentDateTimeString(deviceTime)
        : toTashkentDateTimeString(new Date());

      const attendanceEvent = {
        personId: accessEvent.personId,
        direction: direction.resolve(accessEvent),
        eventTime,
      };

      logger.info('Scan recognized', attendanceEvent);

      if (config.dryRun) {
        continue;
      }

      queue.enqueue(attendanceEvent);
      await flush();
    }
  };
}
