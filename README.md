# Urganchtransgaz Korporativ Portali (my-urtg)

Enterprise employee self-service and HR administration portal for **"Urganchtransgaz" MCHJ** — a central office plus an unlimited number of subordinate organizations (14 seeded for demo purposes). The web app is branded "Urganchtransgaz Korporativ Portali"; the mobile app keeps its own separate name, "My Urganchtransgaz" (see [`mobile/README.md`](mobile/README.md)'s "App name" note). Both share the same corporate flame logo (`logo.svg` at the repo root — the parent "Uztransgaz" group mark, used as-is since "Urganchtransgaz" is one of its regional divisions).

Domain (production): `https://my.urtg.uz`

## Architecture

```
Web SPA (Vue 3 + Vuetify)   ─┐
Mobile app (Flutter)        ─┼──►  Laravel REST API (/api/v1/...)  ──►  MySQL
                             ─┘         (Sanctum token auth)
```

- **backend/** — Laravel 13 API (PHP 8.3), token-based auth via Sanctum, RBAC via `spatie/laravel-permission`, MySQL.
- **frontend/** — Vue 3 + TypeScript + Vuetify SPA (Vite), consuming the API over HTTP only. No business logic lives in the frontend.
- **mobile/** — Flutter app covering employee self-service essentials (tasks, documents, KPI, exams, location-based issue reporting, announcements, notifications). Admin/HR/management screens stay web-only. See [`mobile/README.md`](mobile/README.md) for scope and setup.
- **dahua-bridge/** — a standalone, zero-dependency Node.js service that runs on-site next to a Dahua access-control turnstile, subscribes to its real-time event stream, and forwards each scan to the backend's existing device-attendance webhook, resolving the employee by a new `employees.dahua_person_id` field. See [`dahua-bridge/README.md`](dahua-bridge/README.md).

The API is versioned (`/api/v1`) and mobile-ready by design: authentication is a Bearer token (not a browser session/cookie), so the Flutter app (and any future native client) reuses the exact same backend, with zero API changes.

See [`backend/README.md`](backend/README.md), [`frontend/README.md`](frontend/README.md), and [`mobile/README.md`](mobile/README.md) for stack-specific setup, and the architecture decisions recorded there for *why* things are built this way (Sanctum token mode, `spatie/laravel-permission` instead of hand-rolled tables, organization-scoped Policies, etc.).

## Current status: Phase 9 (Advanced Features — final phase)

**Phase 1 — Foundation:** authentication (login/logout/me/change-password/forgot-reset password, login history), RBAC (9 roles, granular permissions), Organizations (unlimited-depth hierarchy), Departments, Employees, audit logging, and the corresponding Vue admin UI.

**Phase 2 — Employee self-service:** employees edit their own phone/email/address/photo/contacts immediately; changes to official-record fields (name, birth info, passport/PINFL, org/department/position/employee_number/hire_date) instead create a pending change request that HR/admin must approve before it takes effect. Document upload with HR approve/reject, private authenticated file access, and a daily expiry-reminder job (30/7/1-day + expired thresholds). In-app notifications with a header bell and a notifications page. Corresponding Vue UI: profile page, documents page (adapts to the viewer's permissions), HR change-request review queue, notifications page.

**Phase 3 — Attendance:** self-service check-in/check-out with an automatically computed status (present/late/early leave, against configurable work hours), a scoped "today" board, a database-aggregated report (by employee/department/organization, broken down by every status — present/late/early-leave/absent/business-trip/vacation/sick-leave — with a click-through to that person's day-by-day records), a monthly "tabel" timesheet grid (one row per employee, one column per calendar day, exportable as CSV/Excel/PDF with the standard abbreviated codes), and a manual-correction path for HR/admin. A nightly job marks employees with no record as absent (or carries over their vacation/business-trip/sick-leave status). A biometric/integration device API boundary (`POST /api/v1/integrations/attendance/events`, its own per-device token auth, provisioned via `php artisan attendance:create-device`) exists so a real device can be wired up without any API changes — a Dahua turnstile bridge (`dahua-bridge/`) is one such device, live. Corresponding Vue UI: an Attendance page (Today / Report / Tabel tabs) and a dashboard check-in/out widget.

**Phase 4 — Task Management:** managers/department managers/Technical Policy Service/admins create and assign tasks (one or more assignees) with priority/due dates; assignees update their own progress and mark work complete (submitting it for review), and a manager approves or reopens it. Comments, file attachments, and a per-task activity timeline. A daily job reminds assignees 3/1 days before a deadline and on the due date, and automatically flips overdue tasks. Corresponding Vue UI: a Tasks list with a create dialog, a task detail page (progress/complete/approve/reopen/cancel controls, comments, attachments, activity timeline), and a dashboard "My Tasks" widget.

**Phase 5 — Safety Exams:** the Safety Department creates exams (single/multiple-choice and true/false questions), scoped company-wide or to one organization/department; eligible employees take them within a time limit and attempts allowance, get graded immediately (exact-match scoring, no partial credit for multiple-choice), and see which answers were correct. The Safety Department reviews a pass/fail/not-taken roster and statistics per exam. A daily job reminds employees of upcoming exams and approaching deadlines. Corresponding Vue UI: an Exams page (admin management + question authoring, or an employee's Available/My Results tabs), a timed exam-taking flow, and a results roster page.

**Phase 6 — KPI System:** organization-admins (and central roles) define reusable KPI templates scoped company-wide or to one organization/department, each holding indicators (name, weight, target, measurement unit, calculation type, cadence). A "Generate" action bulk-creates one draft result per eligible employee per indicator for a given period, idempotently. Admins enter each employee's actual value and approve it — approval computes the score (calculation-type-dispatched: percentage/quantity/rating score against target, manual/formula take the entered value directly, all clamped 0–100), stamps the approval, and notifies the employee. Employees only ever see their own approved results (a deliberately narrower rule than other modules' broad org-wide visibility, per the spec); managers/department-managers additionally see their team's results. A department-ranking report aggregates approved scores per period. Corresponding Vue UI: a KPI page (admin template/indicator/period management + results entry, or an employee's/manager's results tabs), a dashboard "My KPI" widget, and a report page.

**Phase 7 — Announcements:** organization-admins/HR compose announcement drafts targeted at everyone, the central office, a specific organization/department/employee, or a specific role (an employee sees an announcement the moment any one of its targets applies to them); only a central-admin can actually publish or archive one, a deliberate centralized-editorial-control split. Publishing computes the audience and notifies every targeted employee; a scheduled command auto-publishes a draft once its chosen `publish_at` time arrives and auto-archives one past its `expire_at`. Read/unread is tracked per employee, separate from the notification bell. Corresponding Vue UI: an Announcements page (admin management + targeting, or an employee's read/unread feed), a detail page with image/attachment support, and a dashboard "Latest announcements" widget.

**Phase 8 — Issues (location-based problem reports):** department managers and Technical Policy Service report a problem tied to a map location (region/site) — title, description, an optional free-text object/site name, and a lat/lng pin dropped on a Leaflet + OpenStreetMap map (no API key/billing, unlike Google Maps). Technical Policy Service and central leadership (central-admin/super-admin) see every open issue on a live map that only clears a pin once Technical Policy Service resolves it with a required response; a department manager only sees their own department's issues. Only that resolution response (never any other status change) counts as "fixed" — a deliberately 2-state model (open/resolved), not an invented "in progress" stage. A per-issue timeline (comments + auto-logged activity, mirroring the Task module's own timeline) records the full history. Corresponding Vue UI: an Issues page (map + list + status filter + create dialog) and an issue detail page (map pin, timeline, comments, resolve dialog). This module replaced an earlier Business Trips/Leave Requests module, removed outright (migrations, code, and UI) as unneeded complexity; department-manager task assignment (a related ask) already worked without changes, since `tasks.create`/`tasks.assign` scoping was already department-scoped.

**Phase 9 — Advanced Features (final phase):** a global search bar (app-bar dropdown, top-5-per-type across Employees/Organizations/Departments/Tasks/Announcements/Documents, each reusing that module's own existing authorization/scoping code rather than a parallel search index); an Audit Logs page (filter by user/module/action/date, export as CSV/Excel/PDF) built on the audit-logging infrastructure every prior phase already fed into, plus the two real gaps that infrastructure had — exam-attempt completion and user role changes — are now logged too, the latter via a new central-admin/HR "change role" action on the Employees page; a reusable export action (CSV/native, `.xlsx` via `maatwebsite/excel`, PDF via `barryvdh/laravel-dompdf`) wired into Employees, the Attendance report, and Audit Logs; a stateless preview-then-commit Employee CSV import (organizations/departments resolved by human-readable code, row-level validation, invalid rows skipped and reported individually — never all-or-nothing); real Chart.js bar/pie charts added to the Dashboard (employee distribution, task status), the Attendance report, the KPI report, and Exam results; and a System Settings page (organization details/logo, attendance work hours/grace periods/working days, document upload limits, exam reminder thresholds) backed by a generic key-value `settings` table that falls back to existing `config()` values until an admin actually edits something.

This is the last phase in the project's phased delivery plan (§56) — every module the spec describes now has a working, tested, browser-verified implementation.

**Post-phase improvements — tasks and the issues map:** tasks now carry an optional category (a company-wide `task_categories` list with a color each, managed by Technical Policy Service and central-admin on a Task Categories page; a category used by tasks can only be deactivated, never deleted, and an edited task may keep a since-deactivated one). The Tasks list gained status tabs with live counts (`GET /tasks/summary`, computed under exactly the list's visibility and filters via the shared `Task::visibleTo()` scope), category/priority/"assigned to me"/"created by me" filters, a working title search (the search box previously sent a filter the API rejected), sortable due dates with overdue/near-due hints, and an edit dialog on the task page. The issues map fits itself to the pins, shows a details popup per pin (built from DOM text nodes, so user-typed titles can't inject markup — the old string popups could), marks each status with a glyph as well as a color (red/green alone is unreadable for color-blind users), fans out pins that share a spot, and adds a satellite layer, full-screen mode, and a "my location" button when picking a spot. Leaders get a map report (`GET /issues/report`, page at `/issues/report`, permission `issues.report` for technical-policy, central-admin, organization-admin and department-manager): totals, open-longer-than-7-days count, resolution rate and average time to resolve, a per-organization table (exportable as CSV/Excel/PDF), category and monthly charts, and a map of pins or per-organization bubbles — all aggregated in SQL over exactly the issues `Issue::visibleTo()` lets that user see. Both new permissions reach already-deployed databases through a migration, so a redeploy only needs `migrate` plus `db:seed --class=TaskCategorySeeder` once.

**Leave & absences (HR registry):** HR, central admins and organization admins record each employee's leave (annual, unpaid, study, maternity, childcare), sick leave certificates, business trips (destination required) and other excused absences as dated records, with the order/certificate number and a scanned copy. This is an HR-entered registry, not the employee request workflow that was removed earlier. The records drive the rest of the app. The employee's status switches to vacation, business trip or sick leave while a record is in effect and back to active afterwards (`absences:sync-statuses`, daily at 00:05, plus immediately on save or cancel). Each covered working day goes on the tabel as `T`/`X`/`B`, or `S` (a new *excused* attendance status) for other reasons, and a certificate handed in after the fact retroactively excuses days already marked absent. Two records for one employee can't overlap. Cancelling keeps the record in the history and puts its days back to absent. Annual leave is counted in calendar days against a Settings-configurable entitlement (21 by default, the Labour Code minimum). The web app has a list and a month schedule of who is away, an "away today" summary, CSV/Excel/PDF export, and a section on each employee's page. Department managers and managers can view their scope. Plain employees see their own records and balance on the web, in the mobile app ("Ta'til va safarlar") and on both calendars.

**Mobile app:** a Flutter client covering employee self-service essentials — login, dashboard, profile (view/edit + photo + pending change-request status), documents, tasks, KPI results, safety exams (take + results), location-based issue reporting (flutter_map + geolocator, mirroring the web's Leaflet map), announcements, and notifications (including background system notifications on Android, checked roughly every 15 minutes rather than pushed) — built entirely against the existing API with no backend changes. Attendance is intentionally web-only; the mobile app has none. It has an in-app Uzbek/Russian language setting and an adaptive layout (bottom bar on phones, side rail on wider windows). Admin/HR/management screens (org/employee/KPI-template/exam authoring, settings, audit logs, etc.) are deliberately web-only. It ships in Uzbek and Russian only (no English — its audience is this company's own workforce), carries a Material 3 theme refresh (brand colors pulled from the corporate logo, Google Fonts "Inter" typography, softer rounded styling) on top of the original Phase-1 design, and is prepared for Google Play Console submission (production API default, generated app icon/splash, release signing scaffold, a public `/privacy-policy` page on the web app for the location-permission disclosure). See [`mobile/README.md`](mobile/README.md) for the full scope, setup, Play Store steps, and architecture notes.

## Quick start

Requires PHP 8.3+, Composer, Node 20+, and a MySQL server.

```bash
# 1. Create the database
mysql -uroot -p -e "CREATE DATABASE my_urtg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Backend
cd backend
cp .env.example .env      # already points at my_urtg / root / root for local dev
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000

# 3. Frontend (separate terminal)
cd frontend
cp .env.example .env
npm install
npm run dev                 # http://localhost:5173
```

Open `http://localhost:5173` and sign in with any of the seeded demo accounts (see below) — password `password` for all of them. To run the mobile app instead (or alongside), see [`mobile/README.md`](mobile/README.md) — it needs a Flutter SDK install and points at the same backend.

### Demo accounts (seeded, clearly for local development only)

| Username | Role | Scope |
| --- | --- | --- |
| `superadmin` | super-admin | Everything (bypasses all permission checks) |
| `centraladmin` | central-admin | Everything |
| `orgadmin` | organization-admin | One subordinate organization |
| `deptmanager` | department-manager | One department |
| `hr` | hr | Central office |
| `safety` | safety-manager | Central office |
| `techpolicy` | technical-policy | Central office |
| `manager` | manager | One subordinate organization |
| `employee` | employee | Own profile only |

## Repository layout

```
backend/    Laravel 13 API
frontend/   Vue 3 + Vuetify SPA
mobile/     Flutter app (employee self-service)
```

## Testing

```bash
cd backend
php artisan test --compact
```

The backend test database is a separate MySQL schema (`my_urtg_test`, configured in `phpunit.xml`) — running tests never touches the `my_urtg` development data. Tests cover authentication, RBAC, and — critically — that a user from one organization cannot read or write another organization's data by changing an id (see `tests/Feature/Http/Controllers/Api/V1/EmployeeControllerTest.php`, `AttendanceControllerTest.php`, `TaskControllerTest.php`, `ExamControllerTest.php`, `KpiControllerTest.php`, `AnnouncementControllerTest.php`, `LeaveRequestControllerTest.php`, `BusinessTripControllerTest.php`, and `SearchControllerTest.php`).

Note: the `array` cache driver PHPUnit runs under (`phpunit.xml`) doesn't serialize cached values the way the real `.env`'s `database` driver does — a bug that only ever shows up in a real browser/server (see the `Setting` cache note in `backend/README.md`) is a standing reminder that the test suite alone is not sufficient sign-off for anything cache- or driver-dependent.

```bash
cd mobile
flutter test
```

The mobile app's own test suite (model-parsing tests against real API response shapes, plus a handful of widget tests) is a separate `flutter test` run — see [`mobile/README.md`](mobile/README.md)'s Verification section, including a router bug it caught during real-browser verification that unit tests alone did not.

## Deployment notes

- Both local/dev and the Docker setup below use the `database` queue and cache drivers — no Redis container exists or is needed, since nothing in the app actually queues a job (`grep -r ShouldQueue app/` is empty) or benefits from a faster cache backend than one already-required MySQL table.
- Employee documents, task attachments, and profile photos are stored on the private `local` disk (`storage/app/private`) and served only through authenticated controller endpoints — never a public URL.
- `php artisan documents:check-expiration` is scheduled daily at 07:00, `php artisan absences:sync-statuses` daily at 00:05, `php artisan attendance:mark-absentees` daily at 00:30, `php artisan tasks:check-deadlines` daily at 07:15, `php artisan exams:send-reminders` daily at 07:30, and `php artisan announcements:process-schedule` every 5 minutes (`routes/console.php`). The Docker setup runs these via a dedicated `scheduler` container (`php artisan schedule:work`, a long-running foreground process); outside Docker, use the standard `* * * * * php artisan schedule:run` cron entry instead.
- To connect a real biometric/attendance device, provision it with `php artisan attendance:create-device {device_id} {name} [--organization_id=]` and configure the device to POST to `/api/v1/integrations/attendance/events` with the printed Bearer token — see `backend/README.md` for the payload shape.
- CORS currently allows all origins (`config('cors')` defaults) since auth is token-based, not cookie-based; tighten `allowed_origins` to `https://my.urtg.uz` before deploying to production.

## Docker deployment

A production-oriented `docker-compose.yml` lives at the repo root: MySQL, the Laravel API (a `backend` php-fpm container plus a `backend-nginx` container serving it), the Vue SPA (`frontend`, static files on nginx), a `scheduler` container for the cron-equivalent commands above, a one-off `artisan` tooling container for migrations/seeding, and a `nginx` reverse proxy that's the stack's single public entry point (routes `/api/*` to the backend, everything else to the frontend). Verified end-to-end in this environment: built every image, ran migrations + seeders, brought up all six containers, and confirmed login worked through the full reverse-proxy → backend-nginx → php-fpm → MySQL chain.

```bash
# 1. Configure
cp .env.docker.example .env
# Edit .env: set DB_PASSWORD and MYSQL_ROOT_PASSWORD to real values, and
# generate APP_KEY (needs a build first, since it runs inside the container):
docker compose --profile tools build
docker compose run --rm artisan key:generate --show   # paste the base64:... result into .env as APP_KEY

# 2. Bring up the database and set up the schema
docker compose up -d db
docker compose run --rm artisan migrate --force

# 3. Seed real reference data — NOT the full demo dataset (see note below)
docker compose run --rm artisan db:seed --class=RolePermissionSeeder --force
docker compose run --rm artisan db:seed --class=DocumentTypeSeeder --force
docker compose run --rm artisan db:seed --class=IssueCategorySeeder --force
docker compose run --rm artisan db:seed --class=TaskCategorySeeder --force

# 4. Bring up everything else
docker compose up -d
```

The stack listens on `${HTTP_PORT:-8090}` (host port), not `:80` directly — on a host that already runs other projects behind their own reverse proxy (as this one does), point that existing proxy's `my.urtg.uz` server block at `127.0.0.1:${HTTP_PORT}` instead of exposing this stack's own `nginx` service to the internet directly. Set `HTTP_PORT=80` in `.env` instead if this stack gets a dedicated host.

**Seeding note:** `RolePermissionSeeder`, `DocumentTypeSeeder`, `IssueCategorySeeder` and `TaskCategorySeeder` are real, required setup (roles/permissions, document categories, issue categories — the report-issue form refuses to submit without a category — and the starting task categories, editable afterwards on the Task Categories page). `OrganizationSeeder`/`DepartmentSeeder`/`PositionSeeder`/`EmployeeSeeder` (what a plain `db:seed --force` would also run) encode this company's actual district structure but via Eloquent factories — fine for local dev/staging/demos, but skip them for a real production database and create the real org/department/employee records through the app itself (or the CSV import feature) instead. If you do want the full demo dataset (e.g. for a staging environment), `docker compose run --rm artisan db:seed --force` works too — the `artisan` service's image includes dev dependencies specifically so this works (see `backend/Dockerfile`'s `vendor-dev`/`fpm-tools` stages), unlike the lean `backend`/`scheduler` runtime images.

**First admin account:** no seeder creates one in the real (non-demo) path above — create your first organization, department, and super-admin employee/user once via `docker compose run --rm artisan tinker`, then manage everything else through the web app from there.

**HTTPS:** the bundled `docker/nginx/default.conf` is plain HTTP, meant to sit behind whatever already terminates TLS on this host (as with the other projects here). If this stack ever gets its own dedicated host instead, add a certbot container (webroot method — `docker/nginx/default.conf` already has the `/.well-known/acme-challenge/` location prepared for it) and a second `server` block for `:443`.

**Redeploying after a permission change:** roles and their permissions come from `RolePermissionSeeder`, which is safe to re-run (it syncs, never duplicates) — do so whenever a release adds or regrants permissions, or the new grants never reach the database. The issues update (every role can report issues; `issue_categories.manage`) needs it: `docker compose run --rm artisan db:seed --class=RolePermissionSeeder --force`.

**Redeploying after a code change:**

```bash
git pull
docker compose --profile tools build   # --profile tools matters: it also rebuilds `artisan`
docker compose run --rm artisan migrate --force   # only if new migrations exist
docker compose up -d
```

**Why `--profile tools`:** the `artisan` service (migrations, seeders) is kept out of a normal `docker compose up`/`build` by a compose profile, and `docker compose run` reuses whatever image already exists instead of rebuilding it. Without `--profile tools`, after a `git pull` the app containers get the new code but `artisan` keeps the old image — so a new migration or seeder fails with `Target class [...] does not exist` or silently reports "nothing to migrate". If you hit that, run `docker compose --profile tools build artisan` and retry.

**Logs:** `docker compose logs -f backend` (or `scheduler`, `nginx`, etc.). **Persistent data:** the `db-data` (MySQL) and `backend-storage` (uploaded documents/photos) named volumes survive `docker compose down`; only `docker compose down -v` removes them.
