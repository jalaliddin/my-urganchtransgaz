# Urganchtransgaz Employee Portal — API

Laravel 13 (PHP 8.3) REST API for the employee portal. See the [project root README](../README.md) for the full-stack overview.

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (or MariaDB)

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The `.env.example` already points at a local MySQL database named `my_urtg` (`root`/`root`). Create it first:

```bash
mysql -uroot -p -e "CREATE DATABASE my_urtg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Testing

```bash
php artisan test --compact
# or a single file / filter:
php artisan test --compact --filter=EmployeeControllerTest
```

Tests run against a separate `my_urtg_test` MySQL database (see `phpunit.xml`) via `RefreshDatabase`, never against your local `my_urtg` data. Create it once:

```bash
mysql -uroot -p -e "CREATE DATABASE my_urtg_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

## Scheduled jobs

`documents:check-expiration` runs daily at 07:00 (`routes/console.php`), notifying employees at 30/7/1 days before a document's `expiry_date` and once when it expires. In local development, either run it once manually or start the scheduler loop:

```bash
php artisan documents:check-expiration   # one-off run
php artisan schedule:work                 # runs the scheduler continuously
```

## Architecture decisions

Deliberate substitutions/choices made while implementing the spec — recorded here so they aren't mistaken for oversights.

**Phase 1:**

- **Auth: Sanctum personal-access tokens (Bearer), not SPA cookie/session mode.** One token-based auth path works identically for the Vue SPA and a future mobile app, and avoids cross-domain cookie/CSRF concerns between the SPA and API hosts. Login accepts email, username, *or* employee number (see `App\Auth\LoginIdentifierResolver`).
- **RBAC: `spatie/laravel-permission`, not hand-rolled `roles`/`permissions` tables.** Idiomatic, well-tested, and still stores the exact permission strings the spec calls for (`employees.view`, `tasks.assign`, ...). `super-admin` bypasses every check via a `Gate::before` in `AppServiceProvider` rather than being granted every permission row — see `App\Models\User::hasCentralAccess()` for the one place that bypass has to be duplicated for query-level (non-Gate) scoping.
- **`hr` has company-wide data scope**, unlike every other non-central role. Per the spec's own org chart (§68), HR is a Central Office service managing employees/documents/users across all 15 organizations, not just the central office's own ~9 people — `hasCentralAccess()` includes it. This is a *data-scope* privilege only: granting powerful roles (central-admin, super-admin) on account creation is gated separately in `StoreEmployeeRequest`, checking the literal `central-admin` role.
- **Organization-level data isolation is enforced by Policies** (`App\Policies\*`, sharing `App\Policies\Concerns\ChecksOrganizationScope`), not global Eloquent scopes — explicit, testable, and it's what the mandatory cross-organization security test asserts against.
- **Sensitive PII** (`passport_number`, `pinfl` on `employees`) use Eloquent's `encrypted` cast and are excluded from `EmployeeResource` entirely — no workflow needs them over the API.
- **Password reset links point at the Vue SPA**, not a Laravel web route (`ResetPassword::createUrlUsing` in `AppServiceProvider`, using `config('app.frontend_url')` / `FRONTEND_URL`), since there is no server-rendered reset page.
- **Queues/cache use the `database` driver locally** — no Redis in this environment. Redis is the recommended driver for Docker/production but isn't wired up.

**Phase 2:**

- **Self-service field split**: `phone`/`email`/`address`/photo/contacts apply immediately; official-record fields (name, birth info, gender, passport/PINFL, org/department/position/employee_number/hire_date) instead create one `EmployeeChangeRequest` per submission (`App\Actions\Profile\SubmitProfileUpdateAction`) and never touch the `employees` row until HR/admin approves it (`ApproveChangeRequestAction`). `termination_date`/`employment_type`/`status` are never employee-editable, even via a request — they stay purely admin/HR territory through the Phase 1 `EmployeeController`.
- **Date casts use `date:Y-m-d`, and Resources still `->format('Y-m-d')` explicitly.** A plain `date` cast returns a Carbon instance on direct access; Carbon's own JSON serialization converts to UTC, which silently shifts a stored calendar date to the previous day under `APP_TIMEZONE=Asia/Tashkent` (+5) once the local time crosses midnight-to-5am. Uncaught, this made the profile form re-submit a "changed" `birth_date` on every save even when untouched. The cast's format arg only takes effect inside a model's own `toArray()`, not when a Resource cherry-picks the attribute directly — hence the belt-and-suspenders fix in both places.
- **"Request replacement" (§6)** is reject-with-a-required-reason, not a 4th document status — re-uploading is just a new `POST /documents`.
- **Document-expiry dedup** queries the existing `notifications` table for a prior matching `DocumentExpiring` row (by `document_id` + `threshold`) instead of a new tracking table, keeping §21's minimum-tables list intact.
- **Notifications are result-facing only**: an employee is notified of outcomes (approved/rejected/expiring); HR is not notified per submission and instead works off the scoped pending-queue endpoints.
- **`DatabaseNotificationPolicy` is registered explicitly** via `Gate::policy()` in `AppServiceProvider` — Laravel's policy auto-discovery only guesses within a model's own namespace, so it never finds `App\Policies\DatabaseNotificationPolicy` for the framework's `Illuminate\Notifications\DatabaseNotification`.
- **Photos and documents are both served through authenticated controller endpoints** (`GET /employees/{id}/photo`, `GET /documents/{id}/download`) on the private `local` disk — never a public URL, for the same reason §20 gives for documents.

## API

All endpoints are versioned under `/api/v1`. Auth is a Bearer token from `POST /api/v1/auth/login`. Standard response envelope:

```jsonc
{ "success": true, "message": "Success", "data": {}, "meta": {} }      // success
{ "success": false, "message": "Validation error", "errors": {} }       // 422
```

| Method | Endpoint | Notes |
| --- | --- | --- |
| POST | `/api/v1/auth/login` | `{ login, password }` — email, username, or employee number |
| POST | `/api/v1/auth/logout` | Revokes the current token |
| GET | `/api/v1/auth/me` | Current user, roles, permissions, employee profile |
| POST | `/api/v1/auth/change-password` | |
| POST | `/api/v1/auth/forgot-password` / `reset-password` | |
| GET/POST/PUT/DELETE | `/api/v1/organizations[/{id}]` | Filters: `filter[type]`, `filter[status]`, `filter[parent_id]`, `filter[search]`; sorts: `name`, `code`, `created_at` |
| GET/POST/PUT/DELETE | `/api/v1/departments[/{id}]` | Filters: `filter[status]`, `filter[organization_id]`, `filter[search]` |
| GET/POST/PUT/DELETE | `/api/v1/employees[/{id}]` | Filters: `filter[status]`, `filter[employment_type]`, `filter[organization_id]`, `filter[department_id]`, `filter[search]` |
| GET | `/api/v1/employees/{id}/photo` | Streams the employee's photo; authorized like viewing the employee |
| GET/PUT | `/api/v1/profile` | Own employee profile; `PUT` splits the payload into direct-apply vs. a pending change request (see above) |
| GET | `/api/v1/profile/completion` | `{ percentage, sections, missing_sections }` |
| POST | `/api/v1/profile/photo` | Multipart `photo` (image, max 2MB); resized/re-encoded |
| GET | `/api/v1/document-types` | Read-only lookup for the upload form |
| GET/POST/DELETE | `/api/v1/documents[/{id}]` | Filters: `filter[status]`, `filter[employee_id]`, `filter[document_type_id]`; `POST` is multipart (`document_type_id`, `title`, `file`, optional `employee_id` to upload on someone's behalf) |
| GET | `/api/v1/documents/{id}/download` | Streams the file; never a public URL |
| POST | `/api/v1/documents/{id}/approve` \| `/reject` | `reject` requires `{ reason }`; both 409 if already reviewed |
| GET | `/api/v1/change-requests[/{id}]` | "My requests" for a plain employee; the scoped review queue for HR/admin |
| POST | `/api/v1/change-requests/{id}/approve` \| `/reject` | `reject` requires `{ reason }`; both 409 if already reviewed |
| GET | `/api/v1/notifications` | `?unread_only=1` to filter |
| GET | `/api/v1/notifications/unread-count` | Powers the header bell badge |
| POST | `/api/v1/notifications/{id}/read` \| `/read-all` | |

`php artisan route:list --path=api` is the source of truth as more phases land.

## Conventions

This project uses [Laravel Boost](https://github.com/laravel/boost) — see `CLAUDE.md` / `AGENTS.md` for the coding conventions (PHP 8.3 attribute-based `#[Fillable]`/`#[Hidden]` on models, Pint formatting, Pest testing, `make:` artisan commands, etc.) followed throughout.
