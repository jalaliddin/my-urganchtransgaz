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

## Current status: Phase 2 (Employee Self-Service, Documents, Notifications)

**Phase 1 — Foundation:** authentication (login/logout/me/change-password/forgot-reset password, login history), RBAC (9 roles, granular permissions), Organizations (unlimited-depth hierarchy), Departments, Employees, audit logging, and the corresponding Vue admin UI.

**Phase 2 — Employee self-service:** employees edit their own phone/email/address/photo/contacts immediately; changes to official-record fields (name, birth info, passport/PINFL, org/department/position/employee_number/hire_date) instead create a pending change request that HR/admin must approve before it takes effect. Document upload with HR approve/reject, private authenticated file access, and a daily expiry-reminder job (30/7/1-day + expired thresholds). In-app notifications with a header bell and a notifications page. Corresponding Vue UI: profile page, documents page (adapts to the viewer's permissions), HR change-request review queue, notifications page.

Not yet built (later phases, per the project's phased delivery plan): attendance, tasks, KPI, safety exams, announcements, business trips/leave, global search, full audit-log coverage, import/export, system settings.

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

The backend test database is a separate MySQL schema (`my_urtg_test`, configured in `phpunit.xml`) — running tests never touches the `my_urtg` development data. Tests cover authentication, RBAC, and — critically — that a user from one organization cannot read or write another organization's data by changing an id (see `tests/Feature/Http/Controllers/Api/V1/EmployeeControllerTest.php`).

## Deployment notes

- Local/dev uses the `database` queue and cache drivers (no Redis required). Redis is the recommended driver for production/Docker but is not wired up yet.
- Employee documents and profile photos are stored on the private `local` disk (`storage/app/private`) and served only through authenticated controller endpoints — never a public URL.
- `php artisan documents:check-expiration` is scheduled daily at 07:00 (`routes/console.php`); running the scheduler in production requires the standard `* * * * * php artisan schedule:run` cron entry (or `php artisan schedule:work` in development).
- CORS currently allows all origins (`config('cors')` defaults) since auth is token-based, not cookie-based; tighten `allowed_origins` to `https://my.urtg.uz` before deploying to production.
