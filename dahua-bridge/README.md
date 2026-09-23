# Dahua attendance bridge

A small local service that sits on the same network as a Dahua
access-control terminal (a turnstile, a door reader), subscribes to its
real-time event stream, and forwards each successful scan to
**my.urtg.uz** as an attendance check-in/check-out — the same webhook
already used by any other biometric device
(`backend/app/Http/Controllers/Api/V1/Integrations/AttendanceEventController.php`).
It runs continuously, on-site, independent of anyone having a browser
open; the backend and the web/mobile apps need no code changes to
receive what it sends beyond what is described in this change (an
employee's Dahua ID, see below).

It is a standalone Node.js script with **zero runtime dependencies** —
nothing to `npm install` on a locked-down on-site machine beyond Node
itself.

## Why this exists

Dahua's own documentation
(`ACCESS CONTROL PRODUCTS INTEGRATION INSTRUCTION`, "Subscribe to
real-time events") describes an HTTP endpoint on the terminal itself
(`/cgi-bin/snapManager.cgi?action=attachFileProc&Events=[...]`) that
streams a long-lived `multipart/x-mixed-replace` response: one part per
event, plus periodic heartbeat parts, for as long as the connection is
held open. There is no cloud service in between — the terminal only
speaks to whoever connects to it on the local network — so something has
to run on that network, hold that connection, and relay what it reports
to the outside world. That is all this program does.

## Employee setup: the Dahua ID field

Before a scan can be attributed to anyone, that employee needs their
**Dahua terminal ID** recorded — the `UserID` the terminal itself was
enrolled with when their card/face/fingerprint was registered on it (set
in the terminal's own configuration, not by this bridge or by
my.urtg.uz). This is entered once, in the web app:

**Employees → edit an employee → "Dahua terminal ID"**

It is a new, nullable, unique `employees.dahua_person_id` column
(migration `2026_09_23_090000_add_dahua_person_id_to_employees_table`).
An employee with nothing in this field is simply never matched by this
bridge — no error, the scan is just rejected as unknown (see "Rejected
events" below), which is the expected state for the many employees who
are not enrolled on any terminal.

## One-time backend setup

Provision a device record and its token, same as any other biometric
device:

```bash
php artisan attendance:create-device TURNSTILE-MAIN-GATE "Main gate turnstile" [--organization_id=]
```

This prints a token **once** — put it straight into `.env` as
`DEVICE_TOKEN` below; it is stored on the backend only as a hash and
cannot be recovered later (re-run the command with a new device_id, or
ask someone with database access to rotate it, if it's lost).

## Setup

```bash
cd dahua-bridge
cp .env.example .env
# edit .env: at minimum DEVICE_HOST, DEVICE_USERNAME, DEVICE_PASSWORD,
# SERVER_URL, DEVICE_TOKEN, DEVICE_ID (matching the device_id above)
```

No `npm install` step — there is nothing to install.

**Before enabling forwarding, capture what the terminal actually sends:**

```bash
npm run capture
```

This connects, logs every parsed `AccessControl` event's raw fields, and
never calls the webhook (`LOG_RAW_EVENTS=true DRY_RUN=true`, see
"What was verified, and what was not" below for why this matters). Walk
through the turnstile, look at the output, and confirm:

- which field actually holds the person id — adjust `PERSON_ID_FIELDS`
  in `.env` if it isn't `AccessControl.UserID`/`AccessControl.CardNo`.
- whether a `Status` field distinguishes an accepted scan from a denied
  one, and what its values look like.
- whether the terminal reports anything usable as a direction (a door or
  reader number) if this is a two-reader gate — see "Direction" below.

Once that looks right, start it for real:

```bash
npm start
```

or install it as a systemd service (`scripts/dahua-bridge.service` —
edit the paths and user in it first, then):

```bash
sudo cp scripts/dahua-bridge.service /etc/systemd/system/
sudo systemctl enable --now dahua-bridge
journalctl -u dahua-bridge -f
```

## Direction: check-in vs check-out

Dahua does not have one universal field across every terminal model that
says "this was an entry" vs "this was an exit". Two modes are supported
(`DIRECTION_MODE` in `.env`):

- **`toggle`** (the default, and almost certainly what a single-lane
  turnstile with one reader needs): no device field is trusted at all.
  Each person's first scan on a given calendar day is a check-in, their
  next scan that day is a check-out, and so on. State lives in
  `data/direction-state.json` and resets at local midnight, so a bridge
  restart mid-day doesn't turn someone's next scan back into a check-in.
- **`field`**: for a gate with two readers (one per direction), trust a
  device field — set `DIRECTION_FIELD` (e.g. `AccessControl.Door`),
  `ENTRY_VALUES` and `EXIT_VALUES` to whatever `npm run capture` showed
  for that field. If the field is ever missing from a particular event,
  this mode falls back to toggling for that one scan rather than
  guessing wrong.

## Resilience

- **Offline queue**: every recognized scan is appended to
  `data/queue.ndjson` before delivery is even attempted, and only
  removed once the backend has accepted (or permanently rejected) it. If
  my.urtg.uz is unreachable, scans keep queuing — nothing is lost — and
  a background retry (every 30 seconds, plus immediately after each new
  scan) delivers the backlog once the connection returns. This is what
  "runs locally" is actually for: the terminal itself and this bridge
  keep working through a site's internet outage; only the sync to
  my.urtg.uz is delayed.
- **Rejected events** (a `dahua_person_id` no employee is assigned, or an
  invalid/inactive device token) are logged loudly and dropped, not
  retried forever — retrying a permanent rejection would just hide the
  real problem (someone needs to fill in that employee's Dahua ID, or
  the device token needs re-provisioning).
- **Reconnects** automatically if the terminal drops the connection, with
  backoff (2s, doubling, capped at 60s; resets once a connection has
  stayed up 20+ seconds). A watchdog also forces a reconnect if no data
  at all — not even a heartbeat — arrives for three missed heartbeat
  intervals, since a router can silently drop an idle long-lived
  connection without either side's TCP stack noticing right away.
- **Duplicate scans** of the same person within a few seconds
  (`DEDUPE_SECONDS`, default 4) are treated as one physical pass, not two.
- **Denied access attempts** (a rejected card/face at the terminal) are
  never forwarded as attendance — only a recognized, successful scan is.

## What was verified, and what was not

This was built entirely from Dahua's own published protocol
documentation (the HTTP Digest challenge, the multipart response shape,
the `Events[n].Code`/`Events[n].AccessControl.*` field-naming pattern),
**not against a physical terminal** — none was available while building
this. What that means concretely:

- **Verified, with automated tests** (`npm test`, 60 tests): the HTTP
  Digest authentication handshake (including against a real local HTTP
  server acting as the challenge/response counterpart, not just as unit
  math); the multipart stream parser, including a JPEG snapshot body
  engineered to contain boundary-like bytes inside it, and a part
  deliberately fed one byte at a time to prove chunk-boundary
  reassembly is correct; the flat `Events[n].*` text parser; the direction
  toggle (including surviving a restart) and field modes; the dedupe
  window; the offline queue's durability and flush/retry/drop logic.
- **Verified against the real backend**, live: a real device record was
  provisioned with `attendance:create-device`, an employee given that
  `dahua_person_id`, and the bridge's actual webhook client (unmodified)
  called the running Laravel backend directly — confirmed a real
  `attendance_records` row was created, and separately confirmed an
  unknown person id (422, dropped) and an invalid device token (401,
  dropped) are both rejected rather than retried forever, and that an
  unreachable server is treated as retryable. Both records created
  during this check were deleted afterward.
- **Not verified**: the exact field names a *specific* real terminal
  model sends for the person id, the success/failure status, and (for a
  two-reader gate) the direction. Dahua's own documentation confirms the
  general `Events[n].Code`/dotted-field shape but the concrete
  sub-fields under `AccessControl` were not available to check against a
  real event dump. This is exactly why `npm run capture` and the
  `PERSON_ID_FIELDS`/`DIRECTION_FIELD` settings exist — run capture mode
  against the real terminal before trusting it in production, and adjust
  those settings from what it actually shows. Until that is done, do not
  assume the default `PERSON_ID_FIELDS` guess is correct for a given
  terminal model.
- **HTTP only** (not HTTPS) to the device, matching Dahua's own
  documentation examples and typical on-site configuration — this bridge
  has no HTTPS client path for the device connection.
- The bridge's own outbound call to my.urtg.uz always uses HTTPS when
  `SERVER_URL` is an `https://` URL, same as any other client of this
  API.

## Layout

```
src/
  config.js               loads and validates .env
  digestAuth.js            HTTP Digest challenge/response (RFC 7616)
  multipartStreamParser.js parses the device's multipart event stream
  eventTextParser.js       "Events[n].Key=Value" text -> per-event objects
  accessControlEvent.js    reads one event as an AccessControl scan (or not)
  directionResolver.js     check_in vs check_out (toggle or field mode)
  dedupe.js                suppresses a rapid repeat scan of the same person
  offlineQueue.js          durable NDJSON queue of not-yet-delivered events
  webhookClient.js         POSTs to my.urtg.uz, classifies the outcome
  queueFlusher.js          retries/drops queued events based on that outcome
  eventPipeline.js         wires the above into "one part in -> 0+ events out"
  dahuaClient.js           the device connection itself (auth, streaming, reconnect)
  index.js                 entry point
test/                      node --test — see "What was verified" above
scripts/dahua-bridge.service
```
