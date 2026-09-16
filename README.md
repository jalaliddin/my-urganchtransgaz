# Urganchtransgaz Employee Portal (my-urtg)

Enterprise employee self-service and HR administration portal for **"Urganchtransgaz" MCHJ** — a central office plus an unlimited number of subordinate organizations (14 seeded for demo purposes).

Domain (production): `https://my.urtg.uz`

## Architecture

```
Web SPA (Vue 3 + Vuetify)  ─┐
                             ├──►  Laravel REST API (/api/v1/...)  ──►  MySQL
Future mobile apps          ─┘         (Sanctum token auth)
```

- **backend/** — Laravel 13 API (PHP 8.3), token-based auth via Sanctum, RBAC via `spatie/laravel-permission`, MySQL.
- **frontend/** — Vue 3 + TypeScript + Vuetify SPA (Vite), consuming the API over HTTP only. No business logic lives in the frontend.

The API is versioned (`/api/v1`) and mobile-ready by design: authentication is a Bearer token (not a browser session/cookie), so a future native or Flutter/React Native app can reuse the same backend without changes.

See [`backend/README.md`](backend/README.md) and [`frontend/README.md`](frontend/README.md) for stack-specific setup, and the architecture decisions recorded there for *why* things are built this way (Sanctum token mode, `spatie/laravel-permission` instead of hand-rolled tables, organization-scoped Policies, etc.).

## Current status: Phase 6 (KPI System)

**Phase 1 — Foundation:** authentication (login/logout/me/change-password/forgot-reset password, login history), RBAC (9 roles, granular permissions), Organizations (unlimited-depth hierarchy), Departments, Employees, audit logging, and the corresponding Vue admin UI.

**Phase 2 — Employee self-service:** employees edit their own phone/email/address/photo/contacts immediately; changes to official-record fields (name, birth info, passport/PINFL, org/department/position/employee_number/hire_date) instead create a pending change request that HR/admin must approve before it takes effect. Document upload with HR approve/reject, private authenticated file access, and a daily expiry-reminder job (30/7/1-day + expired thresholds). In-app notifications with a header bell and a notifications page. Corresponding Vue UI: profile page, documents page (adapts to the viewer's permissions), HR change-request review queue, notifications page.

**Phase 3 — Attendance:** self-service check-in/check-out with an automatically computed status (present/late/early leave, against configurable work hours), a scoped "today" board, a database-aggregated report (by employee/department/organization), and a manual-correction path for HR/admin. A nightly job marks employees with no record as absent (or carries over their vacation/business-trip/sick-leave status). A biometric/integration device API boundary (`POST /api/v1/integrations/attendance/events`, its own per-device token auth, provisioned via `php artisan attendance:create-device`) exists so a real device (e.g. Hikvision) can be wired up later without any API changes. Corresponding Vue UI: an Attendance page (Today / Report tabs) and a dashboard check-in/out widget.

**Phase 4 — Task Management:** managers/department managers/Technical Policy Service/admins create and assign tasks (one or more assignees) with priority/due dates; assignees update their own progress and mark work complete (submitting it for review), and a manager approves or reopens it. Comments, file attachments, and a per-task activity timeline. A daily job reminds assignees 3/1 days before a deadline and on the due date, and automatically flips overdue tasks. Corresponding Vue UI: a Tasks list with a create dialog, a task detail page (progress/complete/approve/reopen/cancel controls, comments, attachments, activity timeline), and a dashboard "My Tasks" widget.

**Phase 5 — Safety Exams:** the Safety Department creates exams (single/multiple-choice and true/false questions), scoped company-wide or to one organization/department; eligible employees take them within a time limit and attempts allowance, get graded immediately (exact-match scoring, no partial credit for multiple-choice), and see which answers were correct. The Safety Department reviews a pass/fail/not-taken roster and statistics per exam. A daily job reminds employees of upcoming exams and approaching deadlines. Corresponding Vue UI: an Exams page (admin management + question authoring, or an employee's Available/My Results tabs), a timed exam-taking flow, and a results roster page.

**Phase 6 — KPI System:** organization-admins (and central roles) define reusable KPI templates scoped company-wide or to one organization/department, each holding indicators (name, weight, target, measurement unit, calculation type, cadence). A "Generate" action bulk-creates one draft result per eligible employee per indicator for a given period, idempotently. Admins enter each employee's actual value and approve it — approval computes the score (calculation-type-dispatched: percentage/quantity/rating score against target, manual/formula take the entered value directly, all clamped 0–100), stamps the approval, and notifies the employee. Employees only ever see their own approved results (a deliberately narrower rule than other modules' broad org-wide visibility, per the spec); managers/department-managers additionally see their team's results. A department-ranking report aggregates approved scores per period. Corresponding Vue UI: a KPI page (admin template/indicator/period management + results entry, or an employee's/manager's results tabs), a dashboard "My KPI" widget, and a report page.

Not yet built (later phases, per the project's phased delivery plan): announcements, business trips/leave workflows, global search, full audit-log coverage, import/export, system settings.

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

Open `http://localhost:5173` and sign in with any of the seeded demo accounts (see below) — password `password` for all of them.

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
```

## Testing

```bash
cd backend
php artisan test --compact
```

The backend test database is a separate MySQL schema (`my_urtg_test`, configured in `phpunit.xml`) — running tests never touches the `my_urtg` development data. Tests cover authentication, RBAC, and — critically — that a user from one organization cannot read or write another organization's data by changing an id (see `tests/Feature/Http/Controllers/Api/V1/EmployeeControllerTest.php`, `AttendanceControllerTest.php`, `TaskControllerTest.php`, `ExamControllerTest.php`, and `KpiControllerTest.php`).

## Deployment notes

- Local/dev uses the `database` queue and cache drivers (no Redis required). Redis is the recommended driver for production/Docker but is not wired up yet.
- Employee documents, task attachments, and profile photos are stored on the private `local` disk (`storage/app/private`) and served only through authenticated controller endpoints — never a public URL.
- `php artisan documents:check-expiration` is scheduled daily at 07:00, `php artisan attendance:mark-absentees` daily at 00:30, `php artisan tasks:check-deadlines` daily at 07:15, and `php artisan exams:send-reminders` daily at 07:30 (`routes/console.php`); running the scheduler in production requires the standard `* * * * * php artisan schedule:run` cron entry (or `php artisan schedule:work` in development).
- To connect a real biometric/attendance device, provision it with `php artisan attendance:create-device {device_id} {name} [--organization_id=]` and configure the device to POST to `/api/v1/integrations/attendance/events` with the printed Bearer token — see `backend/README.md` for the payload shape.
- CORS currently allows all origins (`config('cors')` defaults) since auth is token-based, not cookie-based; tighten `allowed_origins` to `https://my.urtg.uz` before deploying to production.
