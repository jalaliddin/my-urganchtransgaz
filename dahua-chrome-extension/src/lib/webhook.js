const REQUEST_TIMEOUT_MS = 10000;

/**
 * POSTs one scan to my.urtg.uz's device webhook (`POST
 * /integrations/attendance/events`, the same one dahua-bridge uses) with
 * the terminal's own device token, and says what to do with it next:
 *
 *  - `delivered`: accepted (the backend ignores an exact repeat, so a
 *    resend after a lost response is harmless).
 *  - `rejected`: refused for a reason resending won't fix — almost always
 *    a Dahua UserID no employee has been given yet. Dropped, and the ID is
 *    listed in the popup so HR can fill it in.
 *  - `auth`: the device token is wrong, belongs to another device_id, or
 *    the device was deactivated. Kept queued; nothing more is sent for
 *    that terminal until its settings are fixed.
 *  - `transient`: offline, timeout, 5xx. Kept queued for the next run.
 */
export async function sendAttendanceEvent(serverUrl, device, event, fetchImpl = fetch) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);

  try {
    const response = await fetchImpl(`${serverUrl.replace(/\/+$/, '')}/integrations/attendance/events`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${device.deviceToken}`,
      },
      body: JSON.stringify({
        device_id: device.deviceId,
        dahua_person_id: event.personId,
        event_type: event.direction,
        event_time: event.eventTime,
      }),
      credentials: 'omit',
      signal: controller.signal,
    });

    if (response.ok) return { outcome: 'delivered', status: response.status };
    if (response.status === 401 || response.status === 403) return { outcome: 'auth', status: response.status };

    if (response.status >= 400 && response.status < 500) {
      const body = await response.json().catch(() => null);
      const unknownPerson = Boolean(body?.errors?.dahua_person_id);

      // A token issued for a different device_id: a settings mistake that
      // would reject every scan from this terminal — hold them, don't drop.
      if (!unknownPerson && /device_id/i.test(body?.message ?? '')) {
        return { outcome: 'auth', status: response.status, message: body.message };
      }

      return { outcome: 'rejected', status: response.status, unknownPerson, message: body?.message ?? null };
    }

    return { outcome: 'transient', status: response.status };
  } catch (error) {
    return { outcome: 'transient', error: error.message };
  } finally {
    clearTimeout(timeout);
  }
}

/**
 * Checks a server URL + device token without recording anything: an empty
 * event is refused by validation (422) only after the token was accepted,
 * and with 401 when the token is wrong.
 */
export async function checkDeviceToken(serverUrl, device, fetchImpl = fetch) {
  try {
    const response = await fetchImpl(`${serverUrl.replace(/\/+$/, '')}/integrations/attendance/events`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${device.deviceToken}` },
      body: '{}',
      credentials: 'omit',
    });

    if (response.status === 422) return { ok: true };
    if (response.status === 401 || response.status === 403) return { ok: false, reason: 'token' };

    return { ok: false, reason: 'http', status: response.status };
  } catch (error) {
    return { ok: false, reason: 'unreachable', error: error.message };
  }
}

/**
 * Sends the terminal's list of enrolled people (`PUT
 * /integrations/attendance/device-users`): the server can't reach the
 * terminal itself, and compares this list with its employees.
 */
export async function sendRoster(serverUrl, device, roster, fetchImpl = fetch) {
  try {
    const response = await fetchImpl(`${serverUrl.replace(/\/+$/, '')}/integrations/attendance/device-users`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${device.deviceToken}` },
      body: JSON.stringify({ device_id: device.deviceId, complete: roster.complete, users: roster.users }),
      credentials: 'omit',
    });

    if (response.ok) return { ok: true };

    return { ok: false, status: response.status };
  } catch (error) {
    return { ok: false, error: error.message };
  }
}
