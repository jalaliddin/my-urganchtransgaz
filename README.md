# Urganchtransgaz Employee Portal (my-urtg)

Enterprise employee self-service and HR administration portal for **"Urganchtransgaz" MCHJ** — a central office plus an unlimited number of subordinate organizations (14 seeded for demo purposes).

Domain (production): `https://my.urtg.uz`

## Architecture

```
Web SPA (Vue 3 + Vuetify)   ─┐
Mobile app (Flutter)        ─┼──►  Laravel REST API (/api/v1/...)  ──►  MySQL
                             ─┘         (Sanctum token auth)
```

- **backend/** — Laravel 13 API (PHP 8.3), token-based auth via Sanctum, RBAC via `spatie/laravel-permission`, MySQL.
- **frontend/** — Vue 3 + TypeScript + Vuetify SPA (Vite), consuming the API over HTTP only. No business logic lives in the frontend.
- **mobile/** — Flutter app covering employee self-service essentials (attendance, tasks, documents, KPI, exams, leave requests, announcements, notifications). Admin/HR/management screens stay web-only. See [`mobile/README.md`](mobile/README.md) for scope and setup.

The API is versioned (`/api/v1`) and mobile-ready by design: authentication is a Bearer token (not a browser session/cookie), so the Flutter app (and any future native client) reuses the exact same backend, with zero API changes.

See [`backend/README.md`](backend/README.md), [`frontend/README.md`](frontend/README.md), and [`mobile/README.md`](mobile/README.md) for stack-specific setup, and the architecture decisions recorded there for *why* things are built this way (Sanctum token mode, `spatie/laravel-permission` instead of hand-rolled tables, organization-scoped Policies, etc.).

## Current status: Phase 9 (Advanced Features — final phase)

**Phase 1 — Foundation:** authentication (login/logout/me/change-password/forgot-reset password, login history), RBAC (9 roles, granular permissions), Organizations (unlimited-depth hierarchy), Departments, Employees, audit logging, and the corresponding Vue admin UI.

**Phase 2 — Employee self-service:** employees edit their own phone/email/address/photo/contacts immediately; changes to official-record fields (name, birth info, passport/PINFL, org/department/position/employee_number/hire_date) instead create a pending change request that HR/admin must approve before it takes effect. Document upload with HR approve/reject, private authenticated file access, and a daily expiry-reminder job (30/7/1-day + expired thresholds). In-app notifications with a header bell and a notifications page. Corresponding Vue UI: profile page, documents page (adapts to the viewer's permissions), HR change-request review queue, notifications page.

**Phase 3 — Attendance:** self-service check-in/check-out with an automatically computed status (present/late/early leave, against configurable work hours), a scoped "today" board, a database-aggregated report (by employee/department/organization), and a manual-correction path for HR/admin. A nightly job marks employees with no record as absent (or carries over their vacation/business-trip/sick-leave status). A biometric/integration device API boundary (`POST /api/v1/integrations/attendance/events`, its own per-device token auth, provisioned via `php artisan attendance:create-device`) exists so a real device (e.g. Hikvision) can be wired up later without any API changes. Corresponding Vue UI: an Attendance page (Today / Report tabs) and a dashboard check-in/out widget.

**Phase 4 — Task Management:** managers/department managers/Technical Policy Service/admins create and assign tasks (one or more assignees) with priority/due dates; assignees update their own progress and mark work complete (submitting it for review), and a manager approves or reopens it. Comments, file attachments, and a per-task activity timeline. A daily job reminds assignees 3/1 days before a deadline and on the due date, and automatically flips overdue tasks. Corresponding Vue UI: a Tasks list with a create dialog, a task detail page (progress/complete/approve/reopen/cancel controls, comments, attachments, activity timeline), and a dashboard "My Tasks" widget.

**Phase 5 — Safety Exams:** the Safety Department creates exams (single/multiple-choice and true/false questions), scoped company-wide or to one organization/department; eligible employees take them within a time limit and attempts allowance, get graded immediately (exact-match scoring, no partial credit for multiple-choice), and see which answers were correct. The Safety Department reviews a pass/fail/not-taken roster and statistics per exam. A daily job reminds employees of upcoming exams and approaching deadlines. Corresponding Vue UI: an Exams page (admin management + question authoring, or an employee's Available/My Results tabs), a timed exam-taking flow, and a results roster page.

**Phase 6 — KPI System:** organization-admins (and central roles) define reusable KPI templates scoped company-wide or to one organization/department, each holding indicators (name, weight, target, measurement unit, calculation type, cadence). A "Generate" action bulk-creates one draft result per eligible employee per indicator for a given period, idempotently. Admins enter each employee's actual value and approve it — approval computes the score (calculation-type-dispatched: percentage/quantity/rating score against target, manual/formula take the entered value directly, all clamped 0–100), stamps the approval, and notifies the employee. Employees only ever see their own approved results (a deliberately narrower rule than other modules' broad org-wide visibility, per the spec); managers/department-managers additionally see their team's results. A department-ranking report aggregates approved scores per period. Corresponding Vue UI: a KPI page (admin template/indicator/period management + results entry, or an employee's/manager's results tabs), a dashboard "My KPI" widget, and a report page.

**Phase 7 — Announcements:** organization-admins/HR compose announcement drafts targeted at everyone, the central office, a specific organization/department/employee, or a specific role (an employee sees an announcement the moment any one of its targets applies to them); only a central-admin can actually publish or archive one, a deliberate centralized-editorial-control split. Publishing computes the audience and notifies every targeted employee; a scheduled command auto-publishes a draft once its chosen `publish_at` time arrives and auto-archives one past its `expire_at`. Read/unread is tracked per employee, separate from the notification bell. Corresponding Vue UI: an Announcements page (admin management + targeting, or an employee's read/unread feed), a detail page with image/attachment support, and a dashboard "Latest announcements" widget.

**Phase 8 — Business Trips / Leave Requests:** an employee submits a leave request (vacation, business trip, sick leave, or other) that goes through a two-stage approval — their department manager first, then HR/organization-admin/central — auto-skipping stage 1 when the employee's department has no manager assigned. Approving a vacation/business-trip/sick-leave request (or, independently, HR/admin creating a standalone business-trip record directly) drives the employee's existing status field, the same one Phase 3's attendance already reads — so attendance recognizes the absence automatically, with no attendance-side changes needed. A daily job keeps this in sync as date ranges start and end. Corresponding Vue UI: a Leave Requests page (submit + a review queue whose actions match the viewer's own approval stage), a Business Trips page (HR/admin management, or a read-only list otherwise), and a dashboard "Upcoming business trips" widget.

**Phase 9 — Advanced Features (final phase):** a global search bar (app-bar dropdown, top-5-per-type across Employees/Organizations/Departments/Tasks/Announcements/Documents, each reusing that module's own existing authorization/scoping code rather than a parallel search index); an Audit Logs page (filter by user/module/action/date, export as CSV/Excel/PDF) built on the audit-logging infrastructure every prior phase already fed into, plus the two real gaps that infrastructure had — exam-attempt completion and user role changes — are now logged too, the latter via a new central-admin/HR "change role" action on the Employees page; a reusable export action (CSV/native, `.xlsx` via `maatwebsite/excel`, PDF via `barryvdh/laravel-dompdf`) wired into Employees, the Attendance report, and Audit Logs; a stateless preview-then-commit Employee CSV import (organizations/departments resolved by human-readable code, row-level validation, invalid rows skipped and reported individually — never all-or-nothing); real Chart.js bar/pie charts added to the Dashboard (employee distribution, task status), the Attendance report, the KPI report, and Exam results; and a System Settings page (organization details/logo, attendance work hours/grace periods/working days, document upload limits, exam reminder thresholds) backed by a generic key-value `settings` table that falls back to existing `config()` values until an admin actually edits something.

This is the last phase in the project's phased delivery plan (§56) — every module the spec describes now has a working, tested, browser-verified implementation.

**Mobile app:** a Flutter client covering employee self-service essentials — login, dashboard, attendance check-in/out + history, profile (view/edit + photo + pending change-request status), documents, tasks, KPI results, safety exams (take + results), leave requests, announcements, and notifications — built entirely against the existing API with no backend changes. Admin/HR/management screens (org/employee/KPI-template/exam authoring, settings, audit logs, etc.) are deliberately web-only; see [`mobile/README.md`](mobile/README.md) for the full scope, setup, and architecture notes.

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

- Local/dev uses the `database` queue and cache drivers (no Redis required). Redis is the recommended driver for production/Docker but is not wired up yet.
- Employee documents, task attachments, and profile photos are stored on the private `local` disk (`storage/app/private`) and served only through authenticated controller endpoints — never a public URL.
- `php artisan documents:check-expiration` is scheduled daily at 07:00, `php artisan attendance:mark-absentees` daily at 00:30, `php artisan tasks:check-deadlines` daily at 07:15, and `php artisan exams:send-reminders` daily at 07:30 (`routes/console.php`); running the scheduler in production requires the standard `* * * * * php artisan schedule:run` cron entry (or `php artisan schedule:work` in development).
- To connect a real biometric/attendance device, provision it with `php artisan attendance:create-device {device_id} {name} [--organization_id=]` and configure the device to POST to `/api/v1/integrations/attendance/events` with the printed Bearer token — see `backend/README.md` for the payload shape.
- CORS currently allows all origins (`config('cors')` defaults) since auth is token-based, not cookie-based; tighten `allowed_origins` to `https://my.urtg.uz` before deploying to production.
