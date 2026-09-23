const REQUEST_TIMEOUT_MS = 8000;

/**
 * Posts one attendance event to my.urtg.uz's device webhook
 * (`POST /integrations/attendance/events`, `AttendanceEventController`
 * on the backend) and classifies the outcome so the caller knows whether
 * to retry it later or drop it for good:
 *
 *  - `delivered`: the backend accepted it (2xx).
 *  - `rejected`: the backend understood the request and refused it for a
 *    reason that will not fix itself by resending — a validation error
 *    (a Dahua person id nobody is enrolled with, most likely), or the
 *    device token being invalid/inactive. Retrying would just repeat the
 *    same rejection forever.
 *  - `transient`: a network failure, timeout, or 5xx — the kind of thing
 *    a later retry can plausibly fix (the site's internet coming back,
 *    the backend recovering).
 */
export async function sendAttendanceEvent(config, event, fetchImpl = fetch) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const response = await fetchImpl(`${config.server.url}/integrations/attendance/events`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${config.server.deviceToken}`,
      },
      body: JSON.stringify({
        device_id: config.server.deviceId,
        dahua_person_id: event.personId,
        event_type: event.direction,
        event_time: event.eventTime,
      }),
      signal: controller.signal,
    });

    if (response.ok) {
      return { outcome: 'delivered', status: response.status };
    }

    if (response.status >= 400 && response.status < 500) {
      const body = await safeReadJson(response);

      return { outcome: 'rejected', status: response.status, body };
    }

    return { outcome: 'transient', status: response.status };
  } catch (error) {
    return { outcome: 'transient', error: error.message };
  } finally {
    clearTimeout(timeout);
  }
}

async function safeReadJson(response) {
  try {
    return await response.json();
  } catch {
    return null;
  }
}
