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

## This deployment: two terminals already provisioned

For the two real gates this bridge was set up against, everything above
is already done — `.env.turnstile-1` and `.env.turnstile-2` exist in
this folder, filled in and ready to run, and are **git-ignored: they are
never committed** (they hold the device admin password and a backend
device token). Copy the whole `dahua-bridge/` folder to wherever this
actually runs long-term and start both (see "Two separate entry/exit
terminals" below for the exact commands), or point each systemd instance
at these files directly.

| | Kirish (entry) | Chiqish (exit) |
|---|---|---|
| Terminal IP | `10.100.90.51` | `10.100.90.52` |
| `DEVICE_ID` | `TURNSTILE-1` | `TURNSTILE-2` |
| `FIXED_DIRECTION` | `check_in` | `check_out` |
| Env file | `.env.turnstile-1` | `.env.turnstile-2` |

**Security note:** the terminal admin password and the two backend
device tokens above were shared in a chat session while setting this up.
That's an acceptable way to hand them over once, but they now exist in
that chat's history — rotate the terminal's admin password and
re-provision both device tokens (`attendance:create-device` again, then
update the two `.env.turnstile-*` files) once this is deployed and
confirmed working, the same as for any credential that passed through a
chat transcript.

## Setup (a new terminal, from scratch)

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

or install it as a systemd service (`scripts/dahua-bridge@.service` — a
template unit; edit the paths and user in it first, then, per instance
name, e.g. `turnstile-1`):

```bash
sudo cp scripts/dahua-bridge@.service /etc/systemd/system/
sudo systemctl enable --now dahua-bridge@turnstile-1
journalctl -u dahua-bridge@turnstile-1 -f
```

## Two separate entry/exit terminals

A common real layout is **two physically separate terminals** at one
gate — one that only ever sees people entering, one that only ever sees
people leaving — rather than one terminal with two readers. In that
case, direction needs no field or guesswork at all: which terminal an
event came from already says everything.

Run **one bridge instance per terminal**, each with its own `.env` (its
own `DEVICE_HOST`, `DEVICE_ID`/`DEVICE_TOKEN` from its own
`attendance:create-device` call, and its own `DATA_DIR` so their queues
never collide), and set:

```bash
# .env.turnstile-1 (the entry terminal)
DIRECTION_MODE=fixed
FIXED_DIRECTION=check_in

# .env.turnstile-2 (the exit terminal)
DIRECTION_MODE=fixed
FIXED_DIRECTION=check_out
```

Run both at once, without systemd, with `ENV_FILE_PATH`:

```bash
ENV_FILE_PATH=.env.turnstile-1 npm start &
ENV_FILE_PATH=.env.turnstile-2 npm start &
```

or as two systemd instances of the same template unit —
`dahua-bridge@turnstile-1` and `dahua-bridge@turnstile-2` — each reading
its own `.env.turnstile-1`/`.env.turnstile-2` automatically (see
`scripts/dahua-bridge@.service`).

## Direction: check-in vs check-out

Dahua does not have one universal field across every terminal model that
says "this was an entry" vs "this was an exit" — see "Two separate
entry/exit terminals" above if that's the actual site layout, which
sidesteps the problem entirely. Otherwise, two modes are supported
(`DIRECTION_MODE` in `.env`):

- **`toggle`** (the default, and almost certainly what a single-lane
  turnstile with one reader needs): no device field is trusted at all.
  Each person's first scan on a given calendar day is a check-in, their
  next scan that day is a check-out, and so on. State lives in
  `data/direction-state.json` and resets at local midnight, so a bridge
  restart mid-day doesn't turn someone's next scan back into a check-in.
- **`field`**: for a single gate with two readers (one per direction),
  trust a device field. The default candidates (`Type`, then `Door`,
  then `ReaderID`) already match what a real terminal was observed
  reporting — its own `Type` field, with the literal values `Entry`/
  `Exit` — so this may work with no `DIRECTION_FIELD` configuration at
  all; confirm with `npm run capture` regardless before relying on it. If
  the field is ever missing from a particular event, this mode falls
  back to toggling for that one scan rather than guessing wrong.

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

This was originally built entirely from Dahua's own published protocol
documentation, then verified live against the two real terminals this
deployment actually uses (`10.100.90.51`, the entry gate; `10.100.90.52`,
the exit gate) — both reachable and tested during development, which is
also how the defaults below stopped being a documentation-only guess.

- **Verified live, against both real terminals**: the HTTP Digest
  handshake with real device credentials; the requested 5-second
  heartbeat arriving on schedule for 3+ minutes straight on both
  terminals simultaneously with zero drops or reconnects; graceful
  shutdown. One real `AccessControl` scan was captured from the entry
  terminal — an unrecognized/no-match attempt (`ErrorCode: 16`, empty
  `UserID`), which is exactly the case this bridge is supposed to ignore,
  and it was correctly ignored (logged as "no recognizable person-id
  field", nothing forwarded). That capture is also what corrected three
  defaults that had been guessed from documentation alone and turned out
  wrong for this hardware:
  - the event's type is reported as `EventBaseInfo.Code`, not the bare
    `Code` the documentation excerpt showed (`CODE_FIELDS`, checked
    before the documented form);
  - the person-id and status fields are flat (`UserID`, `Status`), not
    nested under an `AccessControl.` prefix (`PERSON_ID_FIELDS`, same
    ordering change);
  - a plain `ErrorCode` field (0 = no error) is a more reliable
    success/failure signal than `Status` alone, and is now checked first
    when present.
  - It also confirmed this specific deployment's topology matches
    `DIRECTION_MODE=fixed` exactly as intended: the entry terminal's own
    event carried `"Type":"Entry"`, matching `FIXED_DIRECTION=check_in`
    configured for it independently of that field.
- **Not yet verified**: a genuine *recognized* scan (matched to a real
  `UserID`) end to end from either terminal through to a real
  `attendance_records` row — the capture window only caught an
  unmatched attempt. The `ErrorCode=0`/populated-`UserID` shape a
  successful scan is expected to have is inferred from the one real
  sample's schema, not itself observed. The webhook call and backend
  side of that path *were* separately verified live (next point); what
  remains unconfirmed is only the terminal actually reporting a match
  in the field names this bridge now expects. Confirm with
  `npm run capture` against an employee's real badge/face before
  disabling dry-run in production, and watch the first day's real
  traffic in `journalctl` afterward regardless.
- **Verified against the real backend**, live: a real device record was
  provisioned with `attendance:create-device`, an employee given that
  `dahua_person_id`, and the bridge's actual webhook client (unmodified)
  called the running Laravel backend directly — confirmed a real
  `attendance_records` row was created, and separately confirmed an
  unknown person id (422, dropped) and an invalid device token (401,
  dropped) are both rejected rather than retried forever, and that an
  unreachable server is treated as retryable. Both records created
  during this check were deleted afterward.
- **Automated tests** (`npm test`, 70 tests) additionally cover: the
  multipart stream parser, including a JPEG snapshot body engineered to
  contain boundary-like bytes inside it, and a part deliberately fed one
  byte at a time to prove chunk-boundary reassembly is correct; the flat
  `Events[n].*` text parser; the direction toggle (including surviving a
  restart), field, and fixed modes — the last two exercised with the
  real terminal's own field names and values, including the real
  captured no-match event verbatim; the dedupe window; the offline
  queue's durability and flush/retry/drop logic.
- **HTTP only** (not HTTPS) to the device, matching what both real
  terminals here are actually configured for — this bridge has no HTTPS
  client path for the device connection.
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
