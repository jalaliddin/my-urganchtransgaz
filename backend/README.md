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

## Architecture decisions (Phase 1)

These are deliberate substitutions/choices made while implementing the project's Phase 1 spec — recorded here so they aren't mistaken for oversights:

- **Auth: Sanctum personal-access tokens (Bearer), not SPA cookie/session mode.** One token-based auth path works identically for the Vue SPA and a future mobile app, and avoids cross-domain cookie/CSRF concerns between the SPA and API hosts. Login accepts email, username, *or* employee number (see `App\Auth\LoginIdentifierResolver`).
- **RBAC: `spatie/laravel-permission`, not hand-rolled `roles`/`permissions` tables.** Idiomatic, well-tested, and still stores the exact permission strings the spec calls for (`employees.view`, `tasks.assign`, ...). `super-admin` bypasses every check via a `Gate::before` in `AppServiceProvider` rather than being granted every permission row — see `App\Models\User::hasCentralAccess()` for the one place that bypass has to be duplicated for query-level (non-Gate) scoping.
- **Organization-level data isolation is enforced by Policies** (`App\Policies\*`, sharing `App\Policies\Concerns\ChecksOrganizationScope`), not global Eloquent scopes — explicit, testable, and it's what the mandatory cross-organization security test asserts against.
- **Sensitive PII** (`passport_number`, `pinfl` on `employees`) use Eloquent's `encrypted` cast and are excluded from `EmployeeResource` entirely — no Phase 1 workflow needs them over the API.
- **Password reset links point at the Vue SPA**, not a Laravel web route (`ResetPassword::createUrlUsing` in `AppServiceProvider`, using `config('app.frontend_url')` / `FRONTEND_URL`), since there is no server-rendered reset page.
- **Queues/cache use the `database` driver locally** — no Redis in this environment. Redis is the recommended driver for Docker/production but isn't wired up.

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

`php artisan route:list --path=api` is the source of truth as more phases land.

## Conventions

This project uses [Laravel Boost](https://github.com/laravel/boost) — see `CLAUDE.md` / `AGENTS.md` for the coding conventions (PHP 8.3 attribute-based `#[Fillable]`/`#[Hidden]` on models, Pint formatting, Pest testing, `make:` artisan commands, etc.) followed throughout.
