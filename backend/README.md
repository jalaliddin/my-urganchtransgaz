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

`documents:check-expiration` runs daily at 07:00 (`routes/console.php`), notifying employees at 30/7/1 days before a document's `expiry_date` and once when it expires. `attendance:mark-absentees` runs daily at 00:30, giving every currently-employed staff member with no attendance record for *yesterday* one — `absent`, or their current vacation/business-trip/sick-leave status if that's what `Employee.status` says. `tasks:check-deadlines` runs daily at 07:15, notifying every assignee of a task due in 3/1 days or today, and flipping any past-due `new`/`in_progress` task to `overdue`. `exams:send-reminders` runs daily at 07:30, notifying eligible employees 3/1 days before an exam's `start_date` and 3/1/0 days before its `end_date`. In local development, either run these once manually or start the scheduler loop:

```bash
php artisan documents:check-expiration    # one-off run
php artisan attendance:mark-absentees     # one-off run
php artisan tasks:check-deadlines         # one-off run
php artisan exams:send-reminders          # one-off run
php artisan schedule:work                 # runs the scheduler continuously
```

## Attendance devices

A biometric/integration device is provisioned with its own revocable token (never a Sanctum user token):

```bash
php artisan attendance:create-device DEV-001 "Main entrance" --organization_id=1
# prints a plaintext token once — save it, it is stored only as a SHA-256 hash
```

The device then authenticates with `Authorization: Bearer <token>` against `POST /api/v1/integrations/attendance/events`:

```jsonc
{ "device_id": "DEV-001", "employee_number": "EMP00001", "event_type": "check_in", "event_time": "2026-09-16 09:00:00" }
```

The employee is always resolved server-side from `employee_number` — a raw `employee_id` is never accepted from the payload.

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

**Phase 3:**

- **One attendance row per employee per calendar day** (`unique(employee_id, date)`), not a multi-session log — the spec's Module 8 only asks for a single daily check-in/check-out pair.
- **`status` is computed, not freely chosen**, except for three administrative overrides (`business_trip`, `vacation`, `sick_leave`) settable via a manual correction. Otherwise: late check-in beats early leave when a day is both, since a single record can only hold one status (`App\Actions\Attendance\CalculateAttendanceStatus`). `absent` is never produced by this calculator — see the next point.
- **A nightly command (`attendance:mark-absentees`), not a live computation, produces `absent`.** There is no event to react to when someone simply never shows up, so a day is only "no record yet" until the day has fully elapsed; the command then gives every active/vacationing/traveling/sick employee with no record for *yesterday* one, taking the status from `Employee.status` when it's a leave state. This is also how business-trip status reaches attendance (Module 16) without building the full Business Trips workflow (a later phase) — it just reads the employee record's existing status field.
- **Work hours live in `config/attendance.php`** (env-overridable), not a hard-coded constant and not yet a database-backed Setting — the Settings module is a later phase.
- **Biometric/device auth is its own scheme** (`AttendanceDevice` + `AuthenticateAttendanceDevice` middleware, alias `attendance.device`), not Sanctum — a device isn't a user. The token is stored as a SHA-256 hash and looked up by exact match, the same shape as Sanctum's own token storage. The employee is always resolved from `employee_number` server-side; a device is provisioned via `php artisan attendance:create-device`, not an admin UI, since there is nothing else yet to manage for a device that doesn't exist in this environment.
- **`GET /attendance/today` is built from `Employee`, not `attendance_records`** (`Employee::todayAttendance()`, a date-constrained `hasOne`), so an employee who is absent all day still appears instead of silently vanishing from the board.
- **The report is one endpoint, not separate daily/weekly/monthly ones** — `GET /attendance/report?group_by=employee|department|organization&from=&to=` — with every aggregate (`total_days`, `total_worked_minutes`, `late_count`, `absent_count`, `early_leave_count`) computed in SQL (`SUM`/`COUNT` with `GROUP BY`), never by loading records into PHP.
- **Scoping follows the same convention as Documents (Phase 2)**: `attendance.view` is granted broadly (including the base `employee` role) and, combined with `hasCentralAccess()`/`department-manager` checks, controls the org/department breadth of the index/today/report queries — it is not "self vs. everyone," matching the precedent already shipped for documents rather than inventing a stricter model just for this module.

**Phase 4:**

- **`task_assignees` is a real many-to-many table** (`Task belongsToMany Employee`), not the single `assignee_id` column Module 9's field list shows — §21 explicitly lists `task_assignees` as its own minimum table and says not to duplicate data, so the field list is treated as illustrative (as with every other module). Progress/status/result stay on the `tasks` row as one shared source of truth; the common case is one assignee, and multiple assignees simply collaborate on the same shared state.
- **`technical-policy` was added to `User::hasCentralAccess()`**, the same justified exception as `hr` in Phase 2 — per §68's org chart it's also a Central Office service, and Module 9 explicitly requires it to assign tasks across subordinate organizations. This gap was flagged in `hasCentralAccess()`'s own docblock back in Phase 2, in anticipation of this exact phase.
- **No hard delete for tasks.** The seeded `tasks` permission set is `view/create/update/complete/assign` — there's no `tasks.delete`, and the status enum has a first-class `cancelled` value instead. A task's end-of-life is `POST /tasks/{task}/cancel`.
- **Manager-tier actions (edit/cancel/approve/reopen) are gated on `tasks.assign`, not `tasks.update`.** The base `employee` role also holds `tasks.update` (for updating its own progress), so that permission alone can't distinguish a manager from a plain assignee — using it as the gate let any assignee approve or cancel their own task. Every manager-tier role holds `tasks.assign` and `employee` does not, which is exactly the line Module 9 draws.
- **Updating one's own progress or marking assigned work complete needs no permission check** beyond currently being an assignee — the same inherent-right precedent as attendance's check-in/check-out. `tasks.complete` is seeded for the base role but isn't the actual gate.
- **"Mark completed" moves a task to `waiting`, not `completed`.** Module 9 splits "employee marks completed" from "manager approves completion" — the assignee's action submits the task for review; only a manager's `approve` sets `completed_at`. `reopen` moves `waiting` or `completed` back to `in_progress`.
- **The nightly `tasks:check-deadlines` only reminds/auto-overdues `new`/`in_progress` tasks**, never `waiting` ones — a task already submitted for a manager's approval shouldn't have its state silently overwritten by a passing deadline.
- **Every task action response eager-loads `creator`/`organization`/`department`/`assignees`** before serializing. An earlier draft of `updateProgress`/`complete`/`approve`/`reopen`/`cancel` returned `TaskResource` without loading these relations, which Laravel's `whenLoaded()` then silently drops from the JSON — caught during browser verification when the assignee-only "mark complete" control vanished from the UI right after a progress update. Regression-guarded in `TaskControllerTest` by asserting `data.assignees` on every action response.

**Phase 5:**

- **An exam's `organization_id`/`department_id` (both nullable) are its audience**, not a per-employee assignee list — §21's minimum-tables list has no `exam_assignees` table, so "Safety Department can... assign exams" (§60) means setting this scope (and activating the exam), the same field-list-is-illustrative reasoning as Phase 4's `task_assignees`. `Exam::appliesTo(Employee $employee)` is the one place that scope match is evaluated.
- **Only `safety-manager` administers exams; every other role is a plain exam-taker.** Verified directly in the live seeder before building this phase: `exams.view/create/manage/evaluate` are granted in full only to `safety-manager` (plus central-admin via `*`) — every other non-central role has no exam permission at all, and `employee` has `exams.view` only. This matches Module 7, which only ever mentions "Safety Department" and "Employee," so — unlike the HR/technical-policy gaps found in earlier phases — nothing needed adding to the seeder.
- **Safety Department's company-wide oversight is scoped narrowly to the exams module**, via an explicit `hasRole('safety-manager') || hasCentralAccess()` check inside `ExamPolicy`/the exam controllers — not by adding `safety-manager` to `User::hasCentralAccess()`, which would incorrectly grant it broad reach into employees/attendance/tasks it has no business rule for.
- **One `exam_answers` table serves every question type.** A `true_false` question is just two answer rows ("To'g'ri"/"Noto'g'ri") with one `is_correct` — no per-type schema branching, and `multiple_choice` grading is exact-match (the selected answer set must equal the correct set exactly; no partial credit).
- **A late submission is still accepted and graded**, just flagged (`is_late`) — there's no live server push to force-submit a client at the deadline, so rejecting a slightly-late submit would only lose the employee's work for no benefit.
- **No "auto-expire abandoned attempts" job.** §37 only asks for reminder notifications; an attempt that's started but never submitted just stays `in_progress` forever and still counts against `attempts_allowed` — simpler than inventing an expiry job the spec doesn't call for.
- **Every exam question/answer response is one of two Resources depending on audience**: `ExamQuestionResource` (admin authoring, includes `is_correct`) vs. `ExamAttemptQuestionResource` (the employee taking the exam, `is_correct` omitted entirely) — the same "never leak the answer key" boundary `EmployeeDocumentResource` draws around `file_path`.
- **Frontend: `v-radio-group`'s aggregated `@update:model-value` did not reliably register clicks** when verified in a real browser (confirmed via direct DOM inspection: the underlying native `<input type="radio">` never flipped to `checked`, so every submitted answer came back empty). Fixed by using the same direct-`@click`-computes-new-state pattern already proven to work in the question-authoring form's standalone `v-radio`/`v-checkbox` controls, for both the exam-taking radios and checkboxes — see `ExamAttemptView.vue`.

**Phase 6:**

- **`kpi_templates` is a named, reusable group of indicators; the field list in the spec (weight/target/unit/calculation type/period) belongs to `kpi_indicators`, not the template.** §21 lists both tables separately and warns against duplicating data, so a template only carries its own scope (`organization_id`/`department_id`, both nullable — null means company-wide, the same convention as `Exam`) and each indicator underneath it carries the per-metric mechanics.
- **`kpi_periods` are actual date-bounded instances** (e.g. "2026-Sentabr", `start_date`/`end_date`), company-wide with no scope of their own — a period is just a calendar window. An indicator's own `period` field is its cadence (monthly/quarterly/semiannual/annual), saying which kind of `kpi_periods` row it's evaluated against.
- **"Do not hard-code KPI formulas" is satisfied by one calculation-type-dispatched action (`App\Actions\Kpi\CalculateKpiScore`), not a formula DSL.** `manual` and `formula` both take the entered value as the score directly; `percentage`/`quantity`/`rating` all score as `clamp(actual/target*100, 0, 100)`. A safe arbitrary-formula evaluator (parsing + sandboxed eval) is a substantial, security-sensitive feature the spec gives no syntax for, so it's out of scope for this phase — building it later only touches this one action.
- **No hard delete for templates/indicators/periods**, matching Tasks/Exams' lifecycle philosophy — an `active`/`inactive` status field instead of a destroy endpoint. `employee_kpis` rows aren't deleted either; a mis-entered value is corrected via update before approval.
- **`approved_at`/`approved_by` on `employee_kpis` is the "publish" step.** An admin enters `actual_value` first (a draft, invisible to the employee); approving it computes the score, stamps the approver, and fires `KpiPublished` to the employee. Before approval, a result is visible only to `kpi.manage` holders — the same pending-queue precedent documents and change requests already established.
- **`POST /kpi/generate` bulk-creates draft results, idempotently.** Given a period and a template, it creates one `employee_kpis` row per (eligible employee in the template's scope × each of the template's indicators whose cadence matches the period's type), pre-filling `target_value` from the indicator and skipping any combination that already exists. Running it twice never duplicates rows — this is what makes the module usable for an actual evaluation round instead of one-row-at-a-time entry.
- **Employees see only their own KPI results — a deliberately narrower rule than every other module's broad org-wide visibility precedent** (documents/attendance/tasks all let a base `employee` holding a `.view` permission see org-wide data). Module 11 explicitly says "Employees should see their own KPI results," so `EmployeeKpiPolicy::view()` gates the "see a coworker's record" branch behind an `isSupervisor()` role check (`manager`/`department-manager`/`organization-admin` or `hasCentralAccess()`) rather than the plain `kpi.view` permission alone. Caught in review before running tests, since a first draft would have let any `kpi.view` holder — including a plain employee — read a scoped-but-published coworker's record.
- **Verified in the live seeder before designing scope**: `kpi.manage` is granted only to `organization-admin` (+ central via `*`); `kpi.view` reaches `organization-admin`, `department-manager`, `manager`, and `employee`. `hr`/`safety-manager`/`technical-policy` have no KPI permission at all — Module 11 only ever mentions "Managers," "Employees," and "Central administrators," so (unlike Phases 2 and 4) nothing needed adding to `hasCentralAccess()` or the seeder this phase.
- **The report endpoint aggregates only approved (published) results, in SQL** (`GROUP BY department`, `AVG(score)`), the same never-loop-summing-in-PHP convention as `AttendanceController::report()` — a department with only draft, unapproved results correctly shows no row until at least one result there is published.

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
| GET/POST/PUT/DELETE | `/api/v1/attendance[/{id}]` | `POST`/`PUT` are manual HR/admin corrections (`attendance.manage`); filters: `filter[status]`, `filter[employee_id]`, `from`, `to` |
| GET | `/api/v1/attendance/today` | Scoped board built from Employee, not attendance_records |
| POST | `/api/v1/attendance/check-in` \| `/check-out` | Always the authenticated user's own employee record; 409 if already done |
| GET | `/api/v1/attendance/report` | `?group_by=employee\|department\|organization&from=&to=`, SQL-aggregated |
| POST | `/api/v1/integrations/attendance/events` | Biometric device webhook; device Bearer token, not Sanctum — see "Attendance devices" above |
| GET/POST/PUT | `/api/v1/tasks[/{id}]` | `POST`/`PUT` require `tasks.create`/`tasks.assign`; `assignee_ids` (array) on both; filters: `filter[status]`, `filter[priority]`, `filter[organization_id]`, `filter[department_id]` |
| PATCH | `/api/v1/tasks/{id}/progress` | Any current assignee; `{ progress: 0-100 }` |
| POST | `/api/v1/tasks/{id}/complete` | Any current assignee; `{ result? }`; moves status to `waiting` |
| POST | `/api/v1/tasks/{id}/approve` \| `/reopen` | Creator/manager-in-scope only; `approve` requires `waiting`, both 409 otherwise |
| POST | `/api/v1/tasks/{id}/cancel` | Creator/manager-in-scope; no hard delete exists |
| POST/DELETE | `/api/v1/tasks/{id}/comments[/{comment}]` | Anyone who can view the task; delete is author or manager-in-scope |
| POST/DELETE | `/api/v1/tasks/{id}/attachments[/{attachment}]` | Same visibility rule; `GET .../download` streams the file |
| GET/POST/PUT | `/api/v1/exams[/{id}]` | `POST`/`PUT` require `exams.manage` (safety-manager/central); `index` doubles as the employee-facing list, each exam annotated with the viewer's own `my_attempt_summary` |
| GET/POST/PUT/DELETE | `/api/v1/exams/{id}/questions[/{question}]` | Admin authoring only; `POST`/`PUT` take a nested `answers` array, replaced wholesale on update |
| POST | `/api/v1/exams/{id}/attempts` | Starts (or resumes) the caller's own attempt; 409 once `attempts_allowed` is exhausted or already passed |
| POST | `/api/v1/exams/{id}/attempts/{attempt}/submit` | `{ answers: [{ question_id, answer_ids: [] }] }`; grades and finalizes |
| GET | `/api/v1/exams/{id}/attempts/{attempt}` | Review a finished attempt, correctness included |
| GET | `/api/v1/exams/{id}/results` | Roster + pass/fail/not-taken stats; `exams.evaluate` (safety-manager/central) only |
| GET/POST/PUT | `/api/v1/kpi-templates[/{id}]` | `kpi.manage` only; nullable `organization_id`/`department_id` (null = company-wide) |
| GET/POST/PUT | `/api/v1/kpi-templates/{id}/indicators[/{indicator}]` | `kpi.manage` only; `calculation_type`, `period` (cadence), `weight`, `target`, `measurement_unit` |
| GET/POST/PUT | `/api/v1/kpi/periods[/{id}]` | `kpi.manage` only; date-bounded, company-wide |
| POST | `/api/v1/kpi/generate` | `{ kpi_period_id, kpi_template_id }`; bulk-creates draft results, idempotent |
| GET/POST/PUT | `/api/v1/kpi[/{id}]` | `index`/`store` scoped like other modules; `PUT` sets `actual_value`/`comment` before approval |
| GET | `/api/v1/kpi/my` | The authenticated employee's own results across periods |
| GET | `/api/v1/kpi/report` | `?kpi_period_id=`; SQL-aggregated department ranking, approved results only |
| POST | `/api/v1/kpi/{id}/approve` | Computes the score, stamps the approver, fires `KpiPublished`; 409 if already approved |

`php artisan route:list --path=api` is the source of truth as more phases land.

## Conventions

This project uses [Laravel Boost](https://github.com/laravel/boost) — see `CLAUDE.md` / `AGENTS.md` for the coding conventions (PHP 8.3 attribute-based `#[Fillable]`/`#[Hidden]` on models, Pint formatting, Pest testing, `make:` artisan commands, etc.) followed throughout.
