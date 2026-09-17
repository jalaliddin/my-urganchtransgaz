# Urganchtransgaz Employee Portal — mobile app

Flutter client covering **employee self-service essentials** — the
day-to-day things a rank-and-file employee does on their phone. See the
[project root README](../README.md) for the full-stack overview.

**No backend changes were needed to build this.** The API was already
"mobile-ready by design" since Phase 1 — Sanctum issues a Bearer token,
not a browser cookie — so this app talks to the exact same
`backend/`/`/api/v1/...` endpoints the Vue web app uses, with the same
scoping and permission rules (verified directly against the live
controllers, not just assumed from the web app's behavior).

## Scope

**In:** login/logout, dashboard, attendance (check-in/check-out +
history), profile (view/edit self-service fields, photo, pending
change-request status), documents (list/upload/download), tasks
(list/detail/progress/complete/comments/attachments), KPI (my results),
safety exams (available/take/results), leave requests
(list/submit/cancel), announcements (feed/detail), notifications
(list/mark read), plus a read-only "upcoming business trips" line on
the dashboard.

**Out (stays on the web app):** all organization/department/employee
management, KPI template/exam authoring, task assignment/approval,
leave-request approvals, announcement authoring/publishing, settings,
audit logs, global search, import/export, business-trip creation. Those
screens are data-table- and multi-field-form-heavy and suit a wide
screen better than a phone. There's also no push-notification transport
here — the backend only has in-app notifications (see the web app's own
Phase 9 README) — so this app polls the unread-count endpoint on
refresh instead of anything push-based.

## Stack

- **State management:** Riverpod (`flutter_riverpod`) — plain
  `AsyncNotifier`s, no code generation.
- **Routing:** `go_router`, with a `redirect` callback gating every
  route behind "is logged in," mirroring the web app's own nav guard.
- **HTTP:** `dio`, one client (`lib/core/network/api_client.dart`)
  attaching the stored Bearer token to every request and forcing a
  logout on any 401 — the same shape as
  `frontend/src/services/http.ts`.
- **Token storage:** `flutter_secure_storage` (Keychain/Keystore-backed
  on a real device).
- **Models:** plain hand-written Dart classes with manual
  `fromJson` — no `freezed`/`build_runner`, since every response shape
  was read directly from the actual `Api\V1\*Resource` classes rather
  than guessed.
- **i18n:** `uz` (default) and `ru` via `.arb` files (`lib/l10n/`),
  ported from `frontend/src/locales/*.json`'s existing, already-approved
  translations — no English, by design: the target audience is this
  company's own Uzbekistan-based workforce, not an international one,
  unlike the web app which also serves an `en` locale for completeness.
- **App name:** "My Urganchtransgaz" everywhere the app identifies
  itself — the home-screen icon label (Android `android:label`, iOS
  `CFBundleDisplayName`), the browser tab title, and the in-app login
  screen (`AppLocalizations.appName`) — kept as one fixed brand name
  across both locales rather than grammatically translated per
  language (the same "My X" pattern many branded apps use).
- **Theme:** Material 3, seeded from the same brand colors Vuetify
  uses (`frontend/src/plugins/vuetify.ts`): `#1E3A5F` primary in light
  mode, `#3B6EA5` in dark mode.

## Project layout

```
lib/
  core/            # network client, secure storage, theme, router, shared widgets/models
  features/
    auth/ dashboard/ attendance/ profile/ documents/
    tasks/ kpi/ exams/ leave_requests/ announcements/
    notifications/ more/
      data/          # Repository classes (talk to ApiClient)
      domain/        # Plain models
      presentation/  # Riverpod controllers + screens
  l10n/            # .arb source files + generated AppLocalizations
```

## Running it

### Requirements

Flutter 3.35+ (stable channel), and a running instance of `backend/`
(`php artisan serve`).

### Server address

The API base URL is resolved per-platform by default
(`lib/core/network/api_config.dart`):

| Target | Default |
| --- | --- |
| Android emulator | `http://10.0.2.2:8000/api/v1` |
| iOS simulator / desktop / web | `http://127.0.0.1:8000/api/v1` |

Override it at build/run time for a real device on the same network as
the backend (find the backend machine's LAN IP, and start it with
`php artisan serve --host=0.0.0.0` so it accepts non-localhost
connections):

```bash
flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api/v1
```

The login screen also has a collapsible **Server address** field
(persisted on-device) — useful for pointing one build at a different
backend without rebuilding, e.g. when testing against a staging server.

### Android emulator

```bash
flutter run -d android
```

### iOS simulator (macOS + Xcode required)

```bash
flutter run -d ios
```

### A real device on the same Wi-Fi as the backend

```bash
# on the backend machine:
cd backend && php artisan serve --host=0.0.0.0

# find that machine's LAN IP (e.g. `ip addr` / `ifconfig`), then:
flutter run --dart-define=API_BASE_URL=http://<that-ip>:8000/api/v1
```

### Demo accounts

Same seeded accounts as the web app (`password` for all) — `employee`
is the one this app is built for; the others will work too, since
scoping is identical, but their web-only screens (org/employee
management, etc.) simply have no mobile equivalent.

## Verification

This was built and verified inside a container with no Android
SDK/NDK and no macOS/Xcode — so an actual Android/iOS build could not
be produced or run here. What *was* verified for real:

- `flutter analyze` — clean.
- `flutter test` — unit tests for every model's `fromJson` (matched
  against real `Api\V1\*Resource` shapes) and `ApiException` parsing,
  plus widget tests for the login form, the task list, and the
  today-attendance card (login/session-restore, empty states, and
  button enablement all covered).
- `flutter build web` — a full project compile.
- The compiled web build, served locally and driven with Playwright
  against the real local backend: logged in with the seeded `employee`
  account, checked in for the day (and confirmed the button states
  update correctly), browsed attendance history with the real resulting
  record, and opened every other screen (tasks, documents, profile,
  KPI, exams, leave requests, announcements, notifications) — all with
  real data and zero console errors, then logged out.

**Before shipping to a real device, run it once for real** — `flutter
run` on an Android emulator or physical device (or `flutter run -d ios`
on macOS) — as the final check this pass couldn't cover: native camera/
file-picker permission prompts, secure-storage behavior on-device, and
actual touch-target sizing.

## Architecture decisions

- **Every endpoint already scoped correctly for a plain `employee`
  role** — verified directly against the controllers, not assumed:
  `TaskController::index()` already restricts a non-central user to
  tasks they created or are assigned to; `LeaveRequestController::index()`
  always includes the caller's own requests. No backend change,
  no extra query parameter, was needed for any of the "my X" screens
  in this app.
- **`GET /attendance/today` returns a scoped board, not a single "my
  status" record** (it's shared with the web app's HR/manager view) —
  the mobile dashboard/attendance-today screen finds the caller's own
  row in that list by `employee_id` rather than assuming the response
  is already just-me.
- **Emergency/bank contacts were deliberately left out of the Profile
  screen.** `UpdateProfileRequest` does accept a `contacts` field, but
  `EmployeeResource` never actually serializes it back out, and the web
  frontend never calls any contacts endpoint either — it's an
  incomplete, never-fully-wired feature from an earlier phase, not a
  working one to mirror. Building a fresh UI for it here would mean
  inventing behavior the actual app doesn't have.
- **A real, non-hypothetical bug caught only by browser verification,
  not the test suite**: `RouterRefreshNotifier`'s original `ref.listen`
  callback compared `previous?.value != next.value` to avoid
  "redundant" `notifyListeners()` calls — but `AsyncLoading().value` and
  `AsyncData(null).value` are both `null`, so that check silently
  swallowed the loading→resolved transition whenever the resolved
  session turned out to be "logged out" (no stored token — the most
  common case on a fresh install). The app would sit on the splash
  screen forever, on a real device and not just in this test
  environment. Fixed by trusting Riverpod's own `ref.listen` (which
  already only fires on a genuine state change) instead of re-checking
  equality on top of it.
- **Authenticated images use `NetworkImage`'s built-in `headers`
  param**, not the blob/object-URL workaround the web app needed —
  Flutter's `Image.network`/`NetworkImage` accept custom headers
  natively, so the employee photo and announcement images attach the
  Bearer token the same way any other request does.
- **Files (task attachments, documents, announcement attachments)
  download via `Dio` to a temp file, then open in the OS's own viewer**
  (`open_filex`) — there's no in-app document viewer, matching the
  scope's "essentials, not a full replica" philosophy.
