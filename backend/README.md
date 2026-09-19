# Urganchtransgaz Korporativ Portali — API

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

`documents:check-expiration` runs daily at 07:00 (`routes/console.php`), notifying employees at 30/7/1 days before a document's `expiry_date` and once when it expires. `leave:sync-employee-status` runs daily at 00:15, recomputing each employee's status from approved leave requests and scheduled business trips covering today (reverting to `active` once a range passes) — this is what makes `attendance:mark-absentees`, right after it at 00:30, pick up the correct vacation/business-trip/sick-leave status for *yesterday*. `tasks:check-deadlines` runs daily at 07:15, notifying every assignee of a task due in 3/1 days or today, and flipping any past-due `new`/`in_progress` task to `overdue`. `exams:send-reminders` runs daily at 07:30, notifying eligible employees 3/1 days before an exam's `start_date` and 3/1/0 days before its `end_date`. `announcements:process-schedule` runs every 5 minutes, auto-publishing any draft whose `publish_at` has arrived and auto-archiving any published announcement past its `expire_at`. In local development, either run these once manually or start the scheduler loop:

```bash
php artisan documents:check-expiration      # one-off run
php artisan leave:sync-employee-status      # one-off run
php artisan attendance:mark-absentees       # one-off run
php artisan tasks:check-deadlines           # one-off run
php artisan exams:send-reminders            # one-off run
php artisan announcements:process-schedule  # one-off run
php artisan schedule:work                   # runs the scheduler continuously
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

**Phase 7:**

- **`announcement_targets` is a real one-to-many table, not inline nullable columns on `announcements`.** Unlike `Exam`'s single nullable organization/department pair, §21 lists a separate targets table, so one announcement can carry several simultaneous audiences at once (e.g. "Organization A" and "role: safety-manager"). `Announcement::appliesTo(Employee $employee)` OR-checks every target row in PHP for a single-instance check (the `AnnouncementPolicy::view()` gate); `AnnouncementController::scopeToAudience()` expresses the identical rule in SQL for the audience-feed listing — the same dual-representation precedent as `Exam::appliesTo()` vs. `ExamController::scopeToEligible()`.
- **Creation is split from publishing by permission, on purpose.** The live seeder already grants `announcements.create` to `organization-admin`/`hr`/central, but `announcements.publish` to no one except central-admin — matching §7's literal "Administrators must have centralized control... over announcements." An organization-admin composes and edits a draft but cannot publish or archive even their own; only a central-admin can. `AnnouncementController::index()` therefore gives `announcements.publish` holders every announcement awaiting review (not just their own authored ones), so there's actually something for them to find and act on.
- **A non-central creator's target choices are restricted at the `StoreAnnouncementRequest`/`UpdateAnnouncementRequest` level** — the same per-field scope-check shape as `StoreTaskRequest`'s per-assignee loop — to their own organization, its departments, or its employees; `everyone`/`central`/`role` targets require `hasCentralAccess()`. Without this, `announcements.create` alone would let an organization-admin broadcast company-wide.
- **`publish_at` drives real scheduled publishing, not just a display timestamp.** A draft's `publish_at` can be left null (goes live only via the explicit `publish` action, which stamps it `now()`) or set to a future time; `announcements:process-schedule` (every 5 minutes — more responsive than the app's existing daily jobs, since "scheduled publishing" implies real timeliness) auto-publishes any draft whose time has arrived and auto-archives any published announcement past its `expire_at`. Both the explicit action and the command share one `PublishAnnouncement::handle()`, so notifying the audience and audit-logging only happens in one place.
- **"Rich text" is authored plain text rendered with preserved line breaks, not a WYSIWYG/HTML editor** — no rich-text package exists in the frontend today, and adding one is a dependency change outside this phase's actual need. Same treatment as Phase 6's "no formula DSL" call.
- **Read tracking is its own table (`announcement_reads`), separate from the generic `notifications.read_at`.** Module 12 calls out "read/unread status" as its own feature distinct from Module 13's notification delivery; opening a published, currently-applicable announcement (`GET /announcements/{id}`) marks it read — no separate "mark as read" click, unlike the notifications bell.
- **Image/attachment uploads are their own multipart endpoints**, not bundled into the JSON `store`/`update` payload — mirrors `POST /profile/photo` being separate from `PUT /profile`, and avoids a nested-array-plus-file multipart payload for the `targets` array. Both stream back only through authenticated endpoints, never a public URL.
- **Priority reuses the existing `TaskPriority` enum** instead of a new one — same four values, same `status.*` i18n keys already shipped for Tasks.
- **A small `GET /api/v1/roles` lookup was added** (mirroring `GET /document-types`), purely to populate the "specific role" target picker — there was no role-listing endpoint anywhere in the app before this phase.
- **A real bug caught by the test suite, not manually**: chaining Spatie `QueryBuilder`'s own `->when()` inside `AnnouncementController::index()` silently downgraded the query to a bare Eloquent `Builder` inside the callback (Spatie forwards unrecognized methods like `when()` to the underlying builder via `__call`, so `$this` inside `when()`'s own logic is the *inner* builder, not the QueryBuilder wrapper) — calling `allowedFilters()` afterward then threw `BadMethodCallException`. Fixed by never chaining through `->when()` on a QueryBuilder instance: plain `if`/`else` statements mutate the same held builder in place instead.

**Phase 8 (revised — Issues module replaced Leave Requests/Business Trips):**

- **Leave Requests and Business Trips (the original Phase 8) were removed outright**, not hidden — migrations, models, controllers, policies, enums, notifications, the `leave:sync-employee-status` daily command, seeder permissions, and every corresponding frontend/mobile screen. The removal is safe because `Employee.status` (an HR-editable field since Phase 2) and `MarkAbsentAttendance` (Phase 3) never depended on the request/approval machinery — only on whatever `Employee.status` currently holds — so attendance behavior is unaffected once that machinery is gone.
- **In its place: a location-based Issues module ("Muammolar")**, built to mirror the existing Task/TaskActivity/TaskComment shape exactly rather than inventing new structure — same migration shape, same model relations, same Policy/Controller/FormRequest/Resource conventions, a dedicated `RecordIssueActivity` action, and a comment sub-controller.
- **Two states only — `open`/`resolved`** — matching exactly what was asked (report → visible until resolved → resolved), not an invented "in progress" stage. Only Technical Policy Service's own response (`resolution_note`, required) marks an issue resolved; no other status change counts.
- **Permissions**: `issues.create`/`issues.view`/`issues.resolve`. `department-manager` gets create+view (scoped to their own department via the existing `ChecksOrganizationScope::withinScope()` trait); `technical-policy` gets all three, including targeting any department. `IssuePolicy::resolve()` is `hasRole('technical-policy')` only — central-admin/super-admin still pass through the app's universal `Gate::before`/wildcard bypass, the same as every other "only role X" rule in this codebase, so no special-casing was added.
- **"Leadership" map visibility is deliberately narrower than the app's usual `hasCentralAccess()` helper.** The spec named exactly Technical Policy Service + central-admin/super-admin as who should see the live map of unresolved issues — not the broader `hasCentralAccess()` set (which also includes hr and safety-manager) — so `IssueController`/`IssuePolicy` each have their own explicit `isLeadership()` check instead of reusing that helper.
- **One endpoint serves both "my reported issues" and the leadership map.** `GET /api/v1/issues` is scoped identically for every role (full list for leadership, `department_id`-scoped for everyone else); the map is just this same list filtered to `status=open` and rendered as pins instead of table rows. No separate leadership-only endpoint exists.
- **Issues carry an organization, a category, and one or more executors.** Every role can report an issue (`issues.create` + `issues.view` are granted to all of them). The form picks an organization (roles with company-wide reach — technical-policy, central-admin, super-admin, hr — may file against any active organization, head office included; everyone else only against their own, enforced by the same `withinScope()` check every module uses), a category from `issue_categories`, and **one or several executors** (`issue_executors` pivot, same shape as `task_assignees`) chosen from *all current employees* of that organization — including people with no login, since the work happens in the field. `StoreIssueRequest::after()` re-checks that every executor belongs to the chosen organization and is still employed, so a crafted request can't attach someone from elsewhere. `GET /issues/options` and `GET /issues/executor-candidates` feed the form so web and mobile don't re-derive these rules; a department-manager is *offered* themselves as the starting executor (`default_executor_id`) but can change it — this replaced an earlier rule that made them the sole responsible person. `department_id` on an issue is now simply the reporter's department. Visibility: leadership sees everything; everyone else sees what they reported or execute; a department-manager also what their department raised or is executing; an organization-admin their whole organization (`IssueController::index()` and `IssuePolicy::view()` apply the identical rule). Executors are notified when assigned (except the reporter) and again when the issue is resolved.
- **Issue categories are their own CRUD** (`/issue-categories`, permission `issue_categories.manage` — technical-policy, plus central-admin/super-admin through the wildcard/`Gate::before`). The `code` is generated from the name and never edited; a category that issues use can't be deleted (409) — deactivate it instead, which hides it from the report form but keeps it on old issues so historical reports still group correctly. Seeded reference data lives in `IssueCategorySeeder` (production-safe, like `DocumentTypeSeeder`).
- **A related ask — department managers assigning tasks to their own employees — needed no code change.** `department-manager` already held `tasks.create`/`tasks.assign`, `StoreTaskRequest::authorize()` already scoped every `assignee_id` through `withinScope()`, and the employee picker already only loaded department-scoped employees. Verified directly against the code before concluding no change was needed.

**Phase 9 (final phase):**

- **Audit logging was already comprehensive going into this phase** (`AuditLogService::log()` is called from auth, employees, documents, tasks, kpi, exams, announcements, leave requests, and business trips) — what this phase actually adds is the missing admin UI to browse/filter it, plus two real gaps found by auditing the live code rather than the spec: exam-attempt completion was never logged, and there was no code path anywhere that could fire a "permission changed" entry, since there was no way to change a user's role after initial account creation. Both are closed: `ExamAttemptController::submit()` now logs `'completed'`/`'exams'`, and a new `PUT /api/v1/employees/{id}/role` (`EmployeeController::updateRole()`) logs `'permission_changed'`/`'users'`. It's gated by the *existing* `users.update` permission rather than a new one — that permission is already central-office-only (hr/central-admin) per the Phase 1 seeder, which happens to be exactly the right boundary for who should be allowed to change someone's role, so no seeder change was needed.
- **Export is one reusable action, applied to three representative endpoints, not every table.** `App\Actions\Export\ExportRecords::stream(Builder $query, array $columns, string $format, string $filename)` handles `csv` (native `fputcsv` over `$query->cursor()`, O(1) memory — Employees/Attendance/Audit Logs never materialize a full result set), `xlsx` (`maatwebsite/excel`'s `FromQuery`), and `pdf` (`barryvdh/laravel-dompdf`, capped at 1000 rows since a PDF has to lay out its whole document up front and can't stream row-by-row). A generalization to any other table later is just another `?export=` branch calling the same action.
- **A `BackedEnum` column (e.g. `Employee::status`, cast to `EmployeeStatus`) breaks CSV/XLSX export outright unless unwrapped first.** Caught in real-browser verification, not by the test suite: PhpSpreadsheet's cell writer throws "Unable to bind unstringable object" and `fputcsv` fatals on a bare enum instance — Blade's `{{ }}` (used by the PDF path) is the *only* one of the three that already unwraps a `BackedEnum` on its own (via Laravel's `e()` helper), which is exactly why the PDF export worked on the first try while CSV/XLSX didn't. Fixed with one shared `ExportRecords::formatValue()` helper, called from both `ExportRecords::streamCsv()` and `App\Exports\QueryExport::map()`, so any future export column that happens to be enum-cast is safe by construction rather than by remembering to unwrap it per-column. Regression-covered in `EmployeeControllerTest` (csv/xlsx/pdf export, asserting the *unwrapped* status string is actually present in the CSV body).
- **Employee import is stateless across preview → commit, and deliberately excludes sensitive/binary/credential fields.** `POST /api/v1/employees/import?dry_run=1` parses and validates every row without writing anything; the same endpoint with `dry_run=0` re-parses and writes only the individually-valid rows — never all-or-nothing. Organizations/departments/positions are resolved by human-readable `code` (usable in a real spreadsheet), with a cross-field check that a given department/position actually belongs to the stated organization (rows that fail only this check are reported as "skipped," separate from declarative per-column validation failures). `passport_number`/`pinfl`/`photo` (sensitive/binary) and `create_account`/`username`/`password`/`role` (credential provisioning) are not importable columns — bulk-importing PII or granting login access from a spreadsheet is a risk this phase doesn't take on; those stay one-by-one, deliberate actions. Valid rows are created through the existing `CreateEmployeeAction`, so an imported employee gets the identical "employee created" audit entry as a manually-created one.
- **Global search is one endpoint that reuses each module's own existing scoping rule inline, not a parallel search index.** `GET /api/v1/search?q=` runs a small, capped (5 results per type) query per type (Employees/Organizations/Departments/Tasks/Announcements/Documents) using the exact same authorization/scope code each module's own `index()` already runs — so "search results respect permissions" holds by construction, verified with the same mandatory cross-organization isolation test every other module carries (`SearchControllerTest`).
- **Settings covers every concretely hard-coded business rule the codebase actually had, not a full settings wishlist.** A generic key-value `settings` table (`Setting::get($key, $default)`/`::set()`) backs organization info/logo, attendance work hours + grace periods + **working days** (previously nonexistent as a concept at all — `attendance:mark-absentees` used to mark employees absent even on weekends), document upload size limits, and exam reminder thresholds; `Setting::get()` falls back to the pre-existing `config()` value when never touched, so nothing changes in behavior before an admin opens the settings page. Timezone is shown read-only (`config('app.timezone')`) — changing `APP_TIMEZONE` at runtime mid-request isn't safe, so it's informational only. Deliberately **not** built: notification-channel settings (there's no per-channel notification infrastructure to toggle — only in-app notifications exist) and KPI settings (KPI is already fully data-driven per-template/indicator; there's no hard-coded KPI-wide rule left to expose).
- **Caching a `Collection` (or any non-array object) through the `database` cache driver risks an "incomplete object... unserialize()" error that is structurally invisible to the test suite.** `phpunit.xml` runs tests under `CACHE_STORE=array` (an in-process, non-serializing store), while the real `.env` uses `database` — so `Setting`'s cached settings map is deliberately a plain `array` (`self::query()->pluck('value','key')->all()`), never a `Collection`, and looked up with `array_key_exists()` rather than `->get()`. Caught only by real-browser verification (three separate 500s on the Settings page), reinforcing why this project always browser-verifies rather than trusting the test suite alone for anything driver-dependent.
- **`Announcement::scopeAudienceFor()` is now a proper Eloquent local scope on the model**, not a private controller method — extracted so `AnnouncementController::index()` and the new `SearchController`'s announcement search share exactly one source of truth for "does this announcement target this employee," instead of two copies of the same targeting logic drifting apart over time.
- **The Spatie `QueryBuilder`/`.when()` gotcha from Phase 7 recurred, in a new shape, this phase.** `EmployeeController::index()` chains two `.when()` calls on a `QueryBuilder::for(...)` instance; because Spatie's `QueryBuilder` doesn't extend Eloquent's `Builder` and forwards unrecognized methods (`when()` included) to its internal Eloquent builder via `__call`, the *actual* runtime type of the resulting chain is unpredictable — it silently downgrades to a bare Eloquent `Builder` whenever either `.when()` condition is true, and stays the Spatie wrapper when both are false. This is exactly why `ExportRecords::stream(Builder $query, ...)`'s strict Eloquent `Builder` type hint threw a `TypeError` for one specific account (both conditions false) but not others — a bug the Phase 7 fix pattern (plain `if` statements instead of `.when()`, so the wrapper type never changes) also fixes here, followed by one explicit, now-safe `$query->getEloquentBuilder()` call right before the export branch.

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
| GET | `/api/v1/roles` | Read-only lookup (id/name); populates the announcement "specific role" target picker |
| GET/POST/PUT | `/api/v1/announcements[/{id}]` | `announcements.create`; `index` shows authored-or-reviewable items to creators/`announcements.publish` holders, the live targeted audience feed to everyone else |
| POST | `/api/v1/announcements/{id}/publish` \| `/archive` | `announcements.publish` only, regardless of author; both 409 on an invalid current status |
| POST | `/api/v1/announcements/{id}/image` \| `/attachment` | Multipart upload; author or central only |
| GET | `/api/v1/announcements/{id}/image` \| `/attachment` | Streams/downloads the file; never a public URL |
| GET/POST | `/api/v1/issues[/{id}]` | `index` (filterable by `status`, `issue_category_id`, `organization_id`, `executor_id`): leadership sees all, everyone else what they reported/execute (plus department/organization scope for department-managers/organization-admins); `store` requires `issues.create`, `issue_category_id`, `executor_ids[]` (≥1, all current employees of the organization) and — for company-wide roles — `organization_id` |
| GET | `/api/v1/issues/options` \| `/executor-candidates?organization_id=` | Feed the report form: allowed organizations / active categories / `default_executor_id`, and every current employee of an organization (`issues.create`; a non-central user gets 403 for any organization but their own) |
| GET/POST/PUT/DELETE | `/api/v1/issue-categories[/{id}]` | `issue_categories.manage`; index includes inactive categories with `issues_count`; `DELETE` is 409 while any issue uses the category |
| POST | `/api/v1/issues/{id}/resolve` | `issues.resolve` (technical-policy only); requires `resolution_note`; 409 if already resolved |
| POST | `/api/v1/issues/{id}/comments` | Same comment shape as Tasks; appears in the issue's timeline alongside auto-logged activities |
| GET | `/api/v1/search?q=` | Top 5 per type across employees/organizations/departments/tasks/announcements/documents; reuses each module's own scoping |
| GET | `/api/v1/audit-logs` | `audit_logs.view` (central-admin) only; filters `user_id`/`module`/`action`/`from`/`to`; `?export=csv\|xlsx\|pdf` |
| GET | `/api/v1/reports/overview` | Central/HR-scoped; SQL-aggregated employee counts by organization and task counts by status — powers two dashboard charts |
| GET/PUT | `/api/v1/employees[?export=csv\|xlsx\|pdf]` | `index` now also streams an export instead of a paginated JSON page when `export` is given |
| PUT | `/api/v1/employees/{id}/role` | `users.update` (hr/central-admin) only; the only place a role ever changes after account creation; audit-logged as `permission_changed` |
| GET | `/api/v1/employees/import/template` | Downloads a ready-made CSV with the exact expected import headers |
| POST | `/api/v1/employees/import` | Multipart `file` + `dry_run` (1 or 0); same endpoint previews (no writes) and commits (valid rows only) |
| GET | `/api/v1/attendance/report?export=csv\|xlsx\|pdf` | Same aggregated query as the JSON report, streamed instead of returned |
| GET/PUT | `/api/v1/settings` | `settings.manage` (central-admin) only; grouped values, falls back to `config()` until an admin edits something |
| POST | `/api/v1/settings/logo` | Multipart logo upload; `GET /api/v1/settings/logo` streams it back — never a public URL |

`php artisan route:list --path=api` is the source of truth as more phases land.

## Conventions

This project uses [Laravel Boost](https://github.com/laravel/boost) — see `CLAUDE.md` / `AGENTS.md` for the coding conventions (PHP 8.3 attribute-based `#[Fillable]`/`#[Hidden]` on models, Pint formatting, Pest testing, `make:` artisan commands, etc.) followed throughout.
