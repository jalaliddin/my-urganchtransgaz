# Dahua davomat — Chrome extension

Records Dahua access-control scans (the turnstiles at the gate) as
**my.urtg.uz attendance**, from any Windows/Linux PC on the same local
network as the terminals. Nothing to install but the extension itself: no
Node.js, no service, no server on site.

It is the browser-based alternative to [`dahua-bridge/`](../dahua-bridge/)
(the Node.js service). Both send the same thing to the same webhook; pick
one per terminal. If both ever run against the same terminal, the backend
records each scan once anyway.

## How it works

Every few minutes (and on "Hozir sinxronlash"), for each terminal:

1. **Pull** — reads the terminal's own stored records over the LAN with
   `recordFinder.cgi?action=find&name=AccessControlCardRec` (HTTP Digest
   login), starting where the previous run stopped. The terminal returns
   at most one page per request and no total, so a full page means "ask
   again from the last record's time". Records are skipped by the
   terminal's own record number (`RecNo`) where pages overlap.
2. **Filter** — keeps only recognized scans (`ErrorCode` 0 with a
   `UserID`). Failed face/card attempts (`ErrorCode` 16, no user), about
   half of what this site's entry terminal stores, are ignored.
3. **Deliver** — POSTs each scan to
   `/api/v1/integrations/attendance/events` with that terminal's device
   token, as `check_in` or `check_out` per the terminal's direction, at the
   scan's own time (Asia/Tashkent).

Because it reads the terminal's memory rather than a live stream, nothing
is lost while the PC or browser is off. The next run picks up everything
since the last record it took. Scans wait in a queue while my.urtg.uz is
unreachable and go out when it's back.

| Server answer | What happens |
|---|---|
| 201 | Recorded. |
| 422, unknown `dahua_person_id` | Dropped. The ID and the name the terminal knows are listed in the popup under **Xodimga biriktirilmagan ID lar**, so HR can enter it at Employees → edit → "Dahua terminal ID". |
| 401 / 403, or a token for another `device_id` | Kept. That terminal stops sending until its settings are fixed. The popup shows the error and the icon a red "!". |
| Offline / 5xx | Kept. Retried on the next run. |

## Who is enrolled: comparing with employees

Besides the scans, the extension sends each terminal's list of enrolled
people to the server (`recordFinder.cgi?action=find&name=AccessControlCard`
gives each person's `UserID` and the name on the terminal; one row per
card, folded to one per person) — every 30 minutes and on "Hozir
sinxronlash", via `PUT /api/v1/integrations/attendance/device-users`. A
complete list replaces what the server had; a partial one (the terminal
returned fewer rows than it reported holding) only adds.

The web app's **Dahua terminallari → Odamlarni solishtirish** tab
compares those people with employees (`employees.dahua_person_id`):

- **Web appda yo'q** — enrolled on a turnstile, no employee carries the
  ID. Suggested employees (same surname; the first name matching the
  terminal's initial or name comes first) link with one click; **Boshqa
  xodim…** links any employee; **Yangi xodim** creates one already
  carrying the ID. After linking, "Shu sanadan qayta yuklash" in the
  extension records the past days' passes too (already-recorded ones
  aren't duplicated).
- **Bog'langan** — linked; can be unlinked.
- **Web appda bor, turniketda yo'q** — an employee carries an ID no
  terminal has: a typo, or the person was removed from the turnstile.

Verified against both real terminals: 268 people each, read in about two
seconds, every one with a name. The names come as "Surname", "Surname N",
"Surname N.O.", "Surname NO" and "Surname Nodir"; the matcher treats the
dotted and short capital forms as initials.

## Install (one PC at the gate, or any PC on 10.100.90.x)

Everything is in the web app: **Xodimlar → Dahua terminallari**
(`/attendance/devices`, for users with `attendance_devices.manage` —
hr, central-admin, super-admin). That page:

- offers the extension as `dahua-davomat.zip` (served from
  `frontend/public/downloads/`, built by `npm run pack`) with the
  install steps: extract, `chrome://extensions` → Developer mode →
  **Load unpacked** → the `dahua-davomat` folder;
- lists the terminals (device records), adds new ones and issues a
  device token, shown once with copy buttons together with the Device ID
  and the server address. **Yangi token** replaces a lost or leaked one
  (the old one stops working at once); a terminal can be switched off.

Then, in the extension's **Sozlamalar**, add each terminal:

| Field | This site |
|---|---|
| IP manzil / Port | `10.100.90.51` (entry), `10.100.90.52` (exit) / `80` |
| Terminal logini / paroli | the terminal's admin login |
| Device ID / Qurilma tokeni | from the web page above (`TURNSTILE-1`, `TURNSTILE-2`) |
| Yo'nalish | Kirish for `.51`, Chiqish for `.52` |
| Qaysi sanadan | the first day to load. Earlier days can be pulled later with "Shu sanadan qayta yuklash" (scans already recorded aren't duplicated). |

**Saqlash** asks Chrome for permission to reach that IP; accept it.
**Ulanishni tekshirish** checks the terminal login and the device token
without recording anything. Keep Chrome running on that PC (minimized is
fine); when it starts again it catches up from the terminals' memory.

After changing anything under `src/`, `icons/` or `manifest.json`, run
`npm run pack` so the web app serves the new version — `npm test` fails
until the published zip matches the source.

The terminal password and device tokens are stored in this Chrome
profile's local extension storage (`chrome.storage.local`, never synced to
a Google account). Use a PC and Windows account that only the people
responsible for the gate use.

## Verified

- **Real Chrome (153), real entry terminal (`10.100.90.51`)**: the
  extension's background worker did the Digest login with no extra
  local-network prompt and pulled the day's 187 recognized scans. They
  went to a local stand-in server, not production, each with the right
  device ID, token and Tashkent time. A second run sent nothing new.
  "Ulanishni tekshirish" passed for the terminal and the token.
- **Real terminal behavior it relies on**: records come back oldest first
  with increasing `RecNo`, and two days on the entry gate already exceed
  one 1,024-record page. That's why it pages.
- **`npm test`** (23 tests, `node --test`, no dependencies): MD5 against
  RFC vectors and `node:crypto`; a real Digest login against a local fake
  terminal (right and wrong password, terminal unreachable); parsing a
  real captured response; scan → event rules; and the sync engine end to
  end against a fake terminal and server (paging across same-second page
  boundaries without loss or repeats, unknown IDs, wrong token, token for
  another device, server down then back, one terminal down while the other
  works).

## Layout

```
manifest.json          MV3; storage + alarms; host access to my.urtg.uz,
                       terminal IPs asked for when a terminal is saved
src/background.js      service worker: alarm, "sync now", badge
src/lib/md5.js         MD5 for Digest auth (Web Crypto has none)
src/lib/dahua.js       Digest login, recordFinder.cgi query and parser
src/lib/records.js     record → attendance event, Tashkent time
src/lib/webhook.js     POST to my.urtg.uz, outcome per answer; token check
src/lib/sync.js        pull → queue → deliver, cursor per terminal
src/popup.*            status: per terminal, unknown IDs, recent activity
src/options.*          settings: server, interval, terminals, test, re-sync
scripts/pack.mjs       builds the zip the web app serves (reproducible)
test/                  node --test
```

