# My Urganchtransgaz — mobile app

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

**In:** login/logout, dashboard, profile (view/edit self-service fields, photo, pending
change-request status), documents (list/upload/download), tasks
(list/detail/progress/complete/comments/attachments), KPI (my results),
safety exams (available/take/results), location-based issue reporting
("Muammolar" — any employee can report, choosing the organization,
a category and one or more executors; list/detail/comment, and resolve
for technical-policy), announcements (feed/detail), notifications
(list/mark read, plus system notifications while the app is closed —
see "Notifications in the background"), and an in-app language setting
(Uzbek or Russian).

**Attendance is not in the mobile app** (it was removed on request; the
backend endpoints and the web app's Attendance pages are unchanged).

**Out (stays on the web app):** all organization/department/employee
management, KPI template/exam authoring, task assignment/approval,
announcement authoring/publishing, settings, audit logs, global search,
import/export. Those screens are data-table- and multi-field-form-heavy
and suit a wide screen better than a phone. There's no
push-notification transport (FCM/APNs) either — the backend only has
in-app notifications (see the web app's own Phase 9 README) — so system
notifications come from the app checking the server itself, see below.

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
- **Maps:** `flutter_map` + `latlong2` (OpenStreetMap tiles, no API key
  or billing), the Flutter-side equivalent of the web app's Leaflet
  map — same tile source, same colored-dot marker convention. The
  Issues module's "use my location" button uses `geolocator` for
  on-device GPS.
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
  language (the same "My X" pattern many branded apps use). The web
  app was separately renamed to "Urganchtransgaz Korporativ Portali";
  the mobile app's own name deliberately did **not** follow that
  rename — instead the login screen shows "Urganchtransgaz Korporativ
  Portali" as a small subtitle under the unchanged "My Urganchtransgaz"
  app name (`l10n.appCorporatePortal`), so both names stay visible
  without the app itself being renamed.
- **Theme:** Material 3, seeded from the same brand colors Vuetify
  uses (`frontend/src/plugins/vuetify.ts`): `#1E3A5F` primary in light
  mode, `#3B6EA5` in dark mode, plus a `#9ACC48` tertiary green pulled
  from the corporate logo's flame mark. Typography is Google Fonts
  "Inter" (`google_fonts` package — it covers Cyrillic, needed for the
  `ru` locale) rather than the Material default, with larger corner
  radii (12–20px), softer card/list styling, and gradient welcome
  banners on the Dashboard and More screens — a deliberate visual
  refresh pass (`lib/core/theme/app_theme.dart`) layered on top of the
  original Phase-1 theme rather than a per-screen redesign, so every
  screen picks up the same look automatically.

## Project layout

```
lib/
  core/            # network client, secure storage, theme, router/shell, locale, layout breakpoints, shared widgets/models
  features/
    auth/ dashboard/ profile/ documents/
    tasks/ kpi/ exams/ issues/ announcements/
    notifications/ more/
      data/          # Repository classes (talk to ApiClient)
      domain/        # Plain models
      presentation/  # Riverpod controllers + screens
  l10n/            # .arb source files + generated AppLocalizations
```

## Language

Uzbek (default) and Russian, chosen in **More → Language** or on the
login screen, applied immediately and remembered (`shared_preferences`).
Nothing follows the phone's system language: with no choice made the app
is Uzbek. `LocaleController` (`lib/core/locale/`) owns the setting; the
background notification worker reads the same stored value, so a
notification's *summary* text ("3 new notifications") follows it too.
Note that the title and message of an individual notification are
written by the server, which only produces Uzbek — those are shown as
received.

## Layout

Layout follows the width the app actually has, never the device type:
compact (< 600) gets a bottom navigation bar; 600 and up gets a
navigation rail, extended from 840. Both are built from one destination
list (Home, Tasks, Issues — only for users with issue permissions —
and More). The dashboard is a single column on a phone and two columns
(tasks beside quick actions and announcements) once the page itself is
at least 840 wide, capped at 1100 so content never stretches edge to
edge. It shows a greeting with the notification bell, three counters
(active tasks, open issues, exams to take), a prominent "report a
problem" action, upcoming/overdue tasks with progress, and the latest
announcements, and refreshes with pull-down.

## Notifications in the background

There is no push service behind this app, so system notifications are
produced by the app itself asking the server for unread notifications:

- **App open:** every 60 seconds and whenever it returns to the
  foreground — the bell badge refreshes and anything new is announced.
- **App closed or in the background (Android):** an Android WorkManager
  periodic task (`workmanager`) runs every ~15 minutes with network
  access, in its own isolate, and shows what is new with
  `flutter_local_notifications`. **15 minutes is Android's minimum for
  periodic work and battery saving may delay it further, so this is not
  instant delivery.** Real-time delivery needs push (Firebase Cloud
  Messaging): a Firebase project and `google-services.json`, storing each
  device's token on the backend and sending through FCM when a
  notification is created. That is the natural next step; nothing here
  prevents it.
- Tapping a notification opens what it is about (task, issue,
  announcement, exam, document — from the ids in the notification's
  data), falling back to the notification list.
- What counts as "new" is remembered per account
  (`NotificationSync`): the first check after signing in only records
  what is already unread (it is on the badge; nothing is alerted for it),
  each notification is announced once, more than three at once collapse
  into one summary, and logging out cancels the task and forgets the
  record.
- Android 13+ asks for the notification permission after the first
  sign-in; if it is declined nothing is shown but the in-app bell keeps
  working.
- **iOS is not wired up** (it needs its own background configuration and
  could not be verified here); other platforms just refresh the badge.

## Running it

### Requirements

Flutter 3.35+ (stable channel), and a running instance of `backend/`
(`php artisan serve`).

### Server address

The API base URL is resolved per-platform by default
(`lib/core/network/api_config.dart`):

| Build | Target | Default |
| --- | --- | --- |
| Release (`flutter build`/`--release`) | any | `https://my.urtg.uz/api/v1` (production) |
| Debug/profile | Android emulator | `http://10.0.2.2:8000/api/v1` |
| Debug/profile | iOS simulator / desktop / web | `http://127.0.0.1:8000/api/v1` |

A release build must work the moment someone installs it from Google
Play with no `--dart-define` and no manual setup, which is why release
mode always defaults to the real production server regardless of
platform — the emulator/localhost convenience defaults only apply to
local development builds.

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

An Android SDK is present in this environment (unlike earlier phases),
so real Android builds could be produced and checked directly — no
macOS/Xcode is available, so iOS still could not be built or run here.

- `flutter analyze` — clean.
- `flutter test` — unit tests for every model's `fromJson` (matched
  against real `Api\V1\*Resource` shapes) and `ApiException` parsing,
  plus widget tests for the login form (including switching language
  before signing in), the task list, the adaptive navigation shell
  (bar on a phone, rail on a wider window, selection kept across a
  resize, Issues tab only for users who may use it), the dashboard (at
  320, 390 and 1200/1800 px wide, in Uzbek and Russian, with and
  without issue permissions), the language setting (default, fallback,
  persistence) and the notification-sync logic (baseline, no repeats,
  retry after a failed display, reset on logout).
- `flutter build appbundle --release` — a real, full release build,
  producing an installable `app-release.aab` (~60MB) signed with the
  debug-signing fallback (no real upload keystore exists in this
  environment — see "Google Play Console" above for the one-time setup
  a maintainer must do locally before actually publishing). This
  exercised the whole Play-Store-facing pipeline for real: the
  generated launcher icon/splash screen, R8 minification, resource
  shrinking, and the release-mode production API default all compiled
  successfully together. (Flutter printed a "failed to strip debug
  symbols from native libraries" warning — traced to this sandbox's
  Android SDK missing the `cmdline-tools` component per `flutter
  doctor`, not a project issue; the `.aab` still built correctly.)
- `flutter build web` — a full project compile.
- The compiled **release** web build, served locally and driven with
  Playwright against the real local backend (via the login screen's
  server-address override, confirming the release build's production
  default is otherwise `https://my.urtg.uz/api/v1` and not localhost):
  confirmed the corporate flame logo renders in full color (not the
  solid-black `flutter_svg`/`<style>` bug described above) on the login
  screen alongside both the "My Urganchtransgaz" name and the
  "Urganchtransgaz Korporativ Portali" subtitle, then logged in as
  `deptmanager` and reviewed the redesigned Dashboard (gradient welcome
  banner) and More screen (gradient profile header, grouped menu card,
  logo watermark) — all with real data and zero console errors.

**Before shipping to a real device, run it once for real** — `flutter
run` on an Android emulator or physical device (or `flutter run -d ios`
on macOS) — as the final check this pass couldn't cover: native camera/
file-picker permission prompts, secure-storage behavior on-device,
actual touch-target sizing, and the location permission prompt for the
Issues module.

## Branding

`assets/images/logo.svg` is a copy of the shared `logo.svg` at the repo
root (the corporate "Uztransgaz" flame mark + wordmark), rendered with
`flutter_svg` on the login screen and (small, semi-transparent) at the
bottom of the More screen.

**The upstream SVG uses a CSS `<style>` block with class selectors
(`.fil0 { fill: ... }`, `class="fil0"` on each `<path>`) — `flutter_svg`
doesn't parse `<style>` elements at all** (confirmed by an "unhandled
element `<style/>`" warning and, worse, the logo silently rendering
solid black instead of erroring). Browsers render this fine, so the
issue only showed up here, not on the web app. Fixed once at the
source: the repo-root `logo.svg` now has each path's fill inlined as a
plain `fill="#RRGGBB"` attribute instead of a class, which every SVG
renderer (browsers and `flutter_svg` alike) supports — verified after
the fix with a real `flutter build web` + Playwright screenshot showing
the correct blue/green/navy colors, not black.

**App icon and splash screen** are generated from the same source by
`flutter_launcher_icons`/`flutter_native_splash` (dev dependencies,
configured in `pubspec.yaml`), not hand-exported per platform/density:

```bash
dart run flutter_launcher_icons
dart run flutter_native_splash:create
```

Both read from `assets/icon/`: `icon-legacy.png` (the mark on an opaque
white square — required for iOS, which rejects a transparent icon),
`icon-foreground.png` (the same mark on a transparent square, for
Android's adaptive icon, composited at runtime over
`adaptive_icon_background: "#FFFFFF"`) and `splash-icon.png`. They are
a rasterized crop of `logo.svg` — the full logo's wide icon+wordmark
shape doesn't fit a square — and **the crop is the flame together with
the "U"** (everything above the "TRANSGAZ" wordmark). An earlier crop
cut the mark short, which is why the logo looked incomplete on opening;
the bounds are now measured from the rendered logo's pixel rows/columns
rather than guessed.

Two sizing rules matter here: Android 12+ draws the splash icon inside a
circle two-thirds the size of the image, so a tall mark must occupy no
more than about half of the image's height (the splash icon does), and an
adaptive launcher icon's safe zone is the central ~61%, which
`flutter_launcher_icons` already insets the foreground for. The
notification status-bar icon (`ic_stat_notification`, in each
`res/drawable-*dpi`) is the same mark as a white silhouette — Android
tints status-bar icons, so a coloured one would render as a blob.

## Google Play Console

**Package identity** (already set, do not change once published — the
Play Store ties a listing to this permanently): `uz.urtg.urtg_mobile`
(Android `applicationId`/`namespace`) and `uz.urtg.urtgMobile` (iOS
`PRODUCT_BUNDLE_IDENTIFIER`).

**Release signing.** `android/app/build.gradle.kts` reads
`android/key.properties` (gitignored — copy `key.properties.example`
and fill it in) if present, and only falls back to debug signing when
it's absent, so a fresh checkout still builds without it. Generate a
real upload keystore once, and never regenerate it afterward — losing
it means you can never publish an update to the same Play Store
listing again, only a brand-new one:

```bash
keytool -genkeypair -v -keystore android/app/upload-keystore.jks \
  -alias urtg_upload -keyalg RSA -keysize 2048 -validity 10000
```

Back up the resulting `.jks` file and the store/key passwords you
choose somewhere durable (a password manager) — not just this machine.
Release builds also enable R8 minification and resource shrinking
(`isMinifyEnabled`/`isShrinkResources` in `build.gradle.kts`).

**Production API default.** A release build always talks to
`https://my.urtg.uz/api/v1` with no setup needed (see "Server address"
above) — a Play Store install must work out of the box.

**Privacy policy.** The Issues module requests device location
(`ACCESS_FINE_LOCATION`/`ACCESS_COARSE_LOCATION`), which Play Console's
Data Safety section requires a privacy policy URL for. The web app now
serves one at `/privacy-policy` (`frontend/src/views/legal/PrivacyPolicyView.vue`,
public, no login required) — once deployed, submit
`https://my.urtg.uz/privacy-policy` as the listing's privacy policy
URL.

**Build the release bundle** (what you upload to Play Console):

```bash
flutter build appbundle --release
# → build/app/outputs/bundle/release/app-release.aab
```

**Still manual, outside this repo:** creating the Play Console app
listing itself, the content rating questionnaire, Data Safety form
answers, store graphics (screenshots/feature graphic/icon — the launcher
icon PNG can be reused for the 512×512 store icon), and the actual
upload/rollout.

## Verification

- **Every endpoint already scoped correctly for a plain `employee`
  role** — verified directly against the controllers, not assumed:
  `TaskController::index()` already restricts a non-central user to
  tasks they created or are assigned to; `IssueController::index()`
  scopes a department-manager to their own department and shows
  leadership roles everything. No backend change, no extra query
  parameter, was needed for any of the "my X" screens in this app.
- **Issues module mirrors the web app's design 1:1**: the same
  `flutter_map`/Leaflet map-first UI, the same two-state
  (open/resolved) model, the same "only Technical Policy Service's
  response counts as resolved" rule, and the same single
  `GET /issues` endpoint serving both a department-manager's own list
  and the leadership map — there is no separate mobile-only Issues
  API. See [`backend/README.md`](../backend/README.md)'s Phase 8
  section for the full design rationale (permission model, why
  leadership visibility is narrower than `hasCentralAccess()`, why
  Leave Requests/Business Trips were removed).
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
