import { sendAttendanceEvent } from './webhookClient.js';

/**
 * Attempts to deliver every currently-queued event, oldest first, and
 * rewrites the queue to whatever is left. Stops at the first transient
 * failure instead of trying the rest of a possibly-large backlog against
 * a backend that is currently unreachable — every event from that point
 * on stays queued, in order, for the next scheduled flush.
 */
export async function flushQueue(config, queue, logger, sendEvent = sendAttendanceEvent) {
  const pending = queue.peekAll();
  if (pending.length === 0) {
    return;
  }

  for (let i = 0; i < pending.length; i++) {
    const event = pending[i];
    const result = await sendEvent(config, event);

    if (result.outcome === 'delivered') {
      logger.info('Delivered a queued attendance event', { personId: event.personId, direction: event.direction });
      continue;
    }

    if (result.outcome === 'rejected') {
      logger.error('Dropping a queued attendance event the backend rejected — check that this Dahua person id is assigned to an employee', {
        personId: event.personId,
        status: result.status,
        body: result.body,
      });
      continue;
    }

    logger.warn('Backend still unreachable while flushing the queue — will retry later', {
      queued: pending.length - i,
      error: result.error,
      status: result.status,
    });
    queue.replaceWith(pending.slice(i));

    return;
  }

  queue.replaceWith([]);
}
