# “Urganchtransgaz” MCHJ — Employee Portal
## Claude Code Development Specification

You are a senior full-stack software architect and developer.

Build a production-ready enterprise employee portal for:

**Organization:** “Urganchtransgaz” MCHJ  
**Domain:** `my.urtg.uz`

The application will be used by employees of the central office and 14 subordinate organizations.

The application must be designed so that the web application can later be extended into native Android/iOS mobile applications without rewriting the backend.

---

# 1. PROJECT GOAL

Create a modern enterprise HR and employee self-service portal.

The main purpose is to provide one centralized system where employees can:

- view and update their personal information;
- upload and manage personal documents;
- see their organization, department and position;
- view attendance;
- see tasks assigned to them;
- complete periodic occupational safety exams;
- view KPI indicators;
- see announcements;
- receive notifications;
- communicate with managers through structured workflows;
- eventually use the same backend through a mobile application.

Administrators must have centralized control over organizations, departments, employees, documents, attendance, exams, tasks, KPI, announcements and system settings.

---

# 2. ORGANIZATIONAL STRUCTURE

The organization has:

- 1 Central Office
- 14 subordinate organizations

The hierarchy must be:

```text
Urganchtransgaz MCHJ
│
├── Central Office
│   ├── Department
│   │   ├── Employee
│   │   ├── Employee
│   │   └── Employee
│   └── Department
│
├── Subordinate Organization 1
│   ├── Department
│   │   └── Employees
│   └── Department
│
├── Subordinate Organization 2
│   └── ...
│
└── Subordinate Organization 14
    └── ...
```

The system must support an unlimited number of organizations and departments even though currently there are 15 organizations total.

Do not hard-code the number 14 in the database architecture.

---

# 3. TECHNOLOGY STACK

## Backend

Use:

- Laravel
- PHP
- MySQL
- Laravel API
- Laravel Sanctum or another secure token-based authentication mechanism
- Laravel Policies/Gates
- Laravel Form Requests
- Laravel Resources
- Laravel Jobs/Queues where appropriate
- Laravel Notifications
- Laravel Scheduler
- Laravel Storage

Database:

```text
MySQL
host: 127.0.0.1
port: 3306
database: my_urtg
username: root
password: root
```

Use environment variables.

Never hard-code database credentials into source code.

Example `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_urtg
DB_USERNAME=root
DB_PASSWORD=root
```

---

# 4. FRONTEND

Use:

- Vue 3
- Vuetify
- Vite
- Vue Router
- Pinia
- Axios
- TypeScript if practical

The frontend must communicate with Laravel through REST API endpoints.

Do NOT build business logic directly inside Vue components.

Use:

```text
components/
views/
layouts/
stores/
services/
composables/
types/
utils/
```

---

# 5. ARCHITECTURE PRINCIPLE

Use an API-first architecture.

The backend API must be usable by:

```text
Web Application
       │
       ▼
Laravel REST API
       │
 ┌─────┼─────────┐
 ▼     ▼         ▼
Web   Mobile    Future integrations
```

The future mobile application must be able to use the same API.

Use versioned APIs:

```text
/api/v1/...
```

---

# 6. AUTHENTICATION

Implement secure authentication.

Required features:

- Login
- Logout
- Current user
- Change password
- Forgot password
- Reset password
- Session/token management
- Account status
- Last login
- Login history

Possible login identifiers:

- employee ID
- username
- corporate email

Design the database so authentication can later integrate with:

- Active Directory
- LDAP
- corporate SSO

Do not implement AD integration unless explicitly requested, but keep the architecture ready for it.

---

# 7. ROLE AND PERMISSION SYSTEM

Implement granular RBAC.

Recommended roles:

```text
super-admin
central-admin
organization-admin
department-manager
hr
safety-manager
technical-policy
manager
employee
```

Permissions should be granular.

Examples:

```text
users.view
users.create
users.update
users.delete

organizations.view
organizations.create
organizations.update

departments.view
departments.create
departments.update

documents.view
documents.upload
documents.approve
documents.delete

attendance.view
attendance.manage

tasks.view
tasks.create
tasks.update
tasks.complete
tasks.assign

exams.view
exams.create
exams.manage
exams.evaluate

kpi.view
kpi.manage

announcements.view
announcements.create
announcements.publish
```

An administrator must NOT automatically see everything unless the role grants the permission.

Use Laravel Policies/Gates.

---

# 8. MULTI-ORGANIZATION ACCESS

Implement organization-level data isolation.

For example:

```text
Central Admin
    ↓
Can see all organizations

Organization Admin
    ↓
Can see only their organization

Department Manager
    ↓
Can see only their department

Employee
    ↓
Can see their own information
```

A user from Organization A must not be able to access Organization B data by changing an ID in the URL/API request.

This must be enforced on the backend.

Never rely only on frontend restrictions.

---

# 9. MAIN MODULES

Create the following modules.

---

# MODULE 1 — DASHBOARD

Create a modern dashboard.

Employee dashboard should show:

- Employee name
- Position
- Organization
- Department
- Profile completion percentage
- Today's attendance
- Working hours
- Pending tasks
- Upcoming exams
- KPI score
- Latest announcements
- Notifications
- Important documents

Manager dashboard:

- Department employee count
- Today's attendance
- Late employees
- Pending tasks
- Completed tasks
- KPI overview
- Upcoming exams
- Announcements

Central administrator dashboard:

- Total employees
- Organizations
- Departments
- Today's attendance
- Absent employees
- Late employees
- Pending documents
- Upcoming exams
- KPI statistics
- Task statistics
- System notifications

Use attractive dashboard cards and charts.

---

# MODULE 2 — ORGANIZATIONS

CRUD:

```text
Organizations
```

Fields:

```text
id
name
short_name
code
type
parent_id
address
phone
email
director_name
status
created_at
updated_at
```

Organization types:

```text
central
subordinate
```

Support hierarchical organizations.

---

# MODULE 3 — DEPARTMENTS

Each organization can have departments.

Fields:

```text
id
organization_id
name
short_name
code
description
manager_id
status
created_at
updated_at
```

Relationships:

```text
Organization
    hasMany Departments

Department
    belongsTo Organization
    hasMany Employees
```

---

# MODULE 4 — EMPLOYEES

Employee profile must be comprehensive.

Recommended fields:

```text
id
user_id
organization_id
department_id

employee_number
first_name
last_name
middle_name

birth_date
birth_place
gender

phone
email
corporate_email

address
passport_number
pinfl

position
employment_type

hire_date
termination_date

photo

status

created_at
updated_at
```

Status:

```text
active
vacation
business_trip
sick_leave
inactive
terminated
```

Do not expose sensitive employee information to unauthorized users.

---

# MODULE 5 — EMPLOYEE SELF-SERVICE

Employees must be able to update their own permitted information.

For example:

```text
Phone
Email
Address
Photo
Emergency contact
Bank information if required
Personal documents
```

Sensitive official fields such as:

```text
organization
department
position
employee_number
hire_date
```

must require administrator/HR approval.

Show:

```text
Pending approval
Approved
Rejected
```

for employee-submitted changes.

---

# MODULE 6 — EMPLOYEE DOCUMENTS

Create a document management system.

Examples:

```text
Passport
ID Card
Diploma
Certificate
Employment document
Qualification certificate
Medical document
Safety certificate
Training certificate
Other
```

Database:

```text
employee_documents
```

Fields:

```text
id
employee_id
document_type_id
title
document_number
issue_date
expiry_date
file_path
mime_type
file_size
status
uploaded_by
approved_by
approved_at
created_at
updated_at
```

Employees can upload documents.

Admins/HR can:

- view
- approve
- reject
- download
- delete
- request replacement

Implement document expiry tracking.

For example:

```text
Expires in 30 days
Expires in 7 days
Expired
```

Generate notifications.

---

# MODULE 7 — MEHNAT MUHOFAZASI VA SANOAT XAVFSIZLIGI

Create a periodic employee examination system.

The Safety Department can create exams.

Exam structure:

```text
Exam
 ├── Questions
 │    ├── Question
 │    ├── Answer
 │    └── Correct answer
 └── Employee attempts
```

Exam fields:

```text
title
description
organization_id
department_id
duration_minutes
passing_score
attempts_allowed
start_date
end_date
status
```

Question types:

```text
single_choice
multiple_choice
true_false
```

Employee sees:

```text
Available exams
Upcoming exams
Completed exams
Failed exams
Passed exams
```

After completion:

```text
Score
Percentage
Passed/Failed
Attempt date
```

Safety administrator can see:

- employees who passed;
- employees who failed;
- employees who have not taken the exam;
- expiration dates;
- statistics.

Automatically notify employees about upcoming exams.

---

# MODULE 8 — ATTENDANCE / KELDI-KETDI

Create attendance module.

Required concepts:

```text
check-in
check-out
late
early-leave
absence
business-trip
vacation
sick-leave
```

Database:

```text
attendance_records
```

Fields:

```text
id
employee_id
date
check_in
check_out
worked_minutes
status
source
notes
created_at
updated_at
```

Possible sources:

```text
biometric
manual
mobile
web
api
```

Design the API so biometric terminals can later send attendance data.

Reports:

```text
Daily
Weekly
Monthly
Employee
Department
Organization
```

Show:

- total working days
- worked hours
- late count
- absence count
- early leave count

Future integration must be possible with Hikvision biometric devices.

Do not implement Hikvision integration unless necessary, but create a clean API boundary for it.

---

# MODULE 9 — TASK MANAGEMENT

Create enterprise task management.

Authorized users:

- managers
- department managers
- Technical Policy Service employees
- administrators

can assign tasks.

Task fields:

```text
id
title
description
creator_id
assignee_id
organization_id
department_id

priority
status

start_date
due_date
completed_at

progress
result
attachment

created_at
updated_at
```

Priority:

```text
low
normal
high
urgent
```

Status:

```text
new
in_progress
waiting
completed
cancelled
overdue
```

Employees can:

- view assigned tasks
- update progress
- attach files
- add comments
- mark completed

Managers can:

- create tasks
- assign tasks
- monitor progress
- approve completion
- reopen tasks

Technical Policy Service must be able to assign tasks to employees across subordinate organizations according to permissions.

---

# MODULE 10 — TASK COMMENTS AND HISTORY

Every task must have activity history.

Example:

```text
Task created
Task assigned
Status changed
Progress changed
Comment added
File uploaded
Task completed
Task approved
```

Store:

```text
task_activities
```

This creates a full audit trail.

---

# MODULE 11 — KPI SYSTEM

Create a flexible KPI system.

Do not hard-code KPI formulas.

Architecture:

```text
KPI Template
     ↓
KPI Indicators
     ↓
Employee KPI Period
     ↓
Employee KPI Results
```

KPI fields:

```text
name
description
organization_id
department_id
weight
target
measurement_unit
calculation_type
period
status
```

Possible calculation types:

```text
manual
percentage
quantity
rating
formula
```

KPI period:

```text
monthly
quarterly
semiannual
annual
```

Employee KPI:

```text
employee_id
period_id
indicator_id
target_value
actual_value
score
weight
weighted_score
comment
approved_by
approved_at
```

Dashboard:

```text
Overall KPI
Target
Actual
Score
Department ranking
Monthly trend
```

Employees should see their own KPI results.

Managers should see their permitted employees.

Central administrators can see organization-wide statistics.

---

# MODULE 12 — ANNOUNCEMENTS

Create announcement system.

Announcements can be targeted to:

```text
Everyone
Central Office
Specific Organization
Specific Department
Specific Employee
Specific Role
```

Fields:

```text
title
content
image
attachment
author_id
publish_at
expire_at
status
priority
```

Statuses:

```text
draft
published
archived
```

Support:

- rich text
- attachments
- images
- scheduled publishing
- read/unread status

---

# MODULE 13 — NOTIFICATIONS

Create centralized notification system.

Notifications for:

```text
New task
Task deadline approaching
Task overdue
Exam assigned
Exam reminder
Document expiring
Document rejected
Document approved
KPI published
Announcement
Attendance issue
Profile update approval
```

Support:

```text
In-app notifications
Email-ready architecture
Push notification-ready architecture
```

For now implement in-app notifications.

Prepare backend architecture for Firebase/mobile push notifications later.

---

# MODULE 14 — CALENDAR

Add an employee calendar.

Show:

```text
Exams
Tasks
Task deadlines
Announcements
Vacation
Business trips
Important events
```

Use monthly/weekly/daily views.

---

# MODULE 15 — LEAVE / ABSENCE REQUESTS

Add optional employee request module.

Employee can submit:

```text
Vacation request
Business trip request
Sick leave information
Other absence request
```

Workflow:

```text
Employee
   ↓
Department Manager
   ↓
HR / Administrator
   ↓
Approved / Rejected
```

Keep this module extensible because exact HR workflow may be defined later.

---

# MODULE 16 — BUSINESS TRIPS

Create business trip records.

Fields:

```text
employee_id
destination
purpose
start_date
end_date
order_number
order_file
status
```

Employee dashboard should show current and upcoming business trips.

Attendance must recognize business-trip status.

---

# MODULE 17 — PROFILE COMPLETION

Show employee profile completion percentage.

Example:

```text
Profile completed: 85%
```

Required sections:

```text
Personal information
Contact information
Employment information
Photo
Documents
Emergency contact
```

Show missing fields.

---

# MODULE 18 — SEARCH

Implement global search.

Search:

```text
Employees
Organizations
Departments
Tasks
Announcements
Documents
```

Employee search results must respect permissions.

---

# MODULE 19 — AUDIT LOG

Create a complete audit system.

Track important actions:

```text
Login
Logout
Employee created
Employee updated
Document uploaded
Document approved
Document rejected
Task created
Task assigned
Task completed
KPI changed
Exam created
Exam completed
Announcement published
Permission changed
```

Fields:

```text
user_id
action
module
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
```

Admins can filter audit logs.

---

# MODULE 20 — FILE STORAGE

Use Laravel filesystem.

Recommended:

```text
storage/app/private/
```

Do not expose employee documents through public URLs.

Files must be served through authorized controller endpoints.

Validate:

```text
MIME type
extension
file size
authorization
```

Prevent unauthorized access to documents.

---

# 21. DATABASE DESIGN

Create proper migrations and relationships.

Minimum tables:

```text
users
roles
permissions

organizations
departments
positions

employees
employee_contacts
employee_documents
document_types

attendance_records

tasks
task_assignees
task_comments
task_activities
task_attachments

kpi_templates
kpi_indicators
kpi_periods
employee_kpis

exams
exam_questions
exam_answers
exam_attempts
exam_attempt_answers

announcements
announcement_targets
announcement_reads

notifications

business_trips
leave_requests

audit_logs
```

Use:

- foreign keys
- indexes
- unique constraints
- soft deletes where appropriate
- timestamps

Do not create unnecessarily duplicated data.

---

# 22. API STRUCTURE

Use:

```text
/api/v1/auth/login
/api/v1/auth/logout
/api/v1/auth/me

/api/v1/organizations
/api/v1/departments
/api/v1/employees
/api/v1/employees/{employee}

/api/v1/employees/{employee}/documents
/api/v1/documents

/api/v1/attendance
/api/v1/attendance/today
/api/v1/attendance/report

/api/v1/tasks
/api/v1/tasks/{task}
/api/v1/tasks/{task}/comments
/api/v1/tasks/{task}/progress

/api/v1/kpi
/api/v1/kpi/periods
/api/v1/kpi/my

/api/v1/exams
/api/v1/exams/{exam}
/api/v1/exams/{exam}/start
/api/v1/exams/{exam}/submit

/api/v1/announcements

/api/v1/notifications

/api/v1/business-trips
/api/v1/leave-requests

/api/v1/search
```

Use Laravel API Resources.

Use consistent JSON response format.

Example:

```json
{
  "success": true,
  "message": "Success",
  "data": {},
  "meta": {}
}
```

Validation error:

```json
{
  "success": false,
  "message": "Validation error",
  "errors": {}
}
```

---

# 23. FRONTEND DESIGN

Use Vuetify.

Design must look like a modern enterprise SaaS application.

Do NOT create an old-fashioned admin panel.

Visual style:

```text
Primary sidebar:
Dark navy / dark blue

Main content:
Light background

Cards:
Clean
Rounded
Subtle shadows

Typography:
Modern and readable

Buttons:
Rounded but professional

Tables:
Clean
Dense enough for enterprise usage
Responsive
```

Sidebar must be dark blue.

Recommended layout:

```text
┌────────────────────────────────────────────────────┐
│ Top Header                                          │
├───────────────┬────────────────────────────────────┤
│               │                                    │
│ Dark Navy     │             Main Content           │
│ Sidebar       │                                    │
│               │                                    │
│ Dashboard     │                                    │
│ Employees     │                                    │
│ Organizations │                                    │
│ Attendance    │                                    │
│ Tasks         │                                    │
│ KPI           │                                    │
│ Exams         │                                    │
│ Announcements │                                    │
│ Documents     │                                    │
│               │                                    │
│ Settings      │                                    │
└───────────────┴────────────────────────────────────┘
```

Sidebar should be collapsible.

On mobile/tablet:

```text
Sidebar → Drawer
```

---

# 24. RESPONSIVE DESIGN

The application must work on:

```text
Desktop
Laptop
Tablet
Mobile
```

Mobile-first principles should be considered even though the first version is web-based.

Do not create desktop-only tables.

Use responsive:

```text
Data tables
Cards
Forms
Dialogs
Navigation
Charts
```

---

# 25. FUTURE MOBILE APP

The backend must be mobile-ready.

Future mobile application may use:

```text
Flutter
React Native
Native Android/iOS
```

Therefore:

- all business logic must stay in Laravel;
- Vue must not contain business rules;
- APIs must be versioned;
- authentication must be token-based;
- file upload APIs must be reusable;
- notification architecture must support push notifications;
- attendance APIs must support mobile;
- task APIs must support mobile;
- exam APIs must support mobile.

---

# 26. SECURITY REQUIREMENTS

Implement:

```text
Authentication
Authorization
RBAC
Policies
CSRF protection where applicable
Rate limiting
Request validation
File validation
SQL injection protection
XSS protection
Mass assignment protection
Secure password hashing
Audit logs
```

Never trust:

```text
organization_id
department_id
employee_id
user_id
```

sent from the frontend.

Always validate authorization on the backend.

Prevent IDOR vulnerabilities.

---

# 27. PASSWORD SECURITY

Use Laravel's secure password hashing.

Never store plain-text passwords.

Password requirements:

```text
Minimum 8 characters
```

Optionally support stronger requirements.

Create password reset functionality.

---

# 28. UI LANGUAGE

Primary language:

```text
Uzbek
```

Architecture must support future:

```text
Russian
English
```

Use localization files.

Do not hard-code all interface strings directly into components.

Example:

```text
resources/lang/uz/
resources/lang/ru/
resources/lang/en/
```

Frontend should also use localization architecture.

---

# 29. DATE AND TIME

Application timezone:

```text
Asia/Tashkent
```

Date format:

```text
DD.MM.YYYY
```

Time:

```text
HH:mm
```

Store timestamps consistently.

---

# 30. EMPLOYEE ID / PERSONNEL NUMBER

Every employee should have a unique personnel number:

```text
employee_number
```

It should be indexed and unique.

Do not use database ID as the visible personnel number.

---

# 31. DASHBOARD STATISTICS

Use charts for:

```text
Attendance
KPI
Tasks
Exam results
Employee distribution
Organization distribution
```

Use Vuetify-compatible chart solution.

Charts must be responsive.

---

# 32. TABLE FEATURES

Enterprise tables should support:

```text
Search
Filtering
Sorting
Pagination
Column selection where useful
Export
```

Exports:

```text
Excel
CSV
PDF
```

Implement export architecture carefully.

Do not load thousands of records into browser memory.

Use backend pagination and server-side filtering.

---

# 33. IMPORT EMPLOYEES

Add employee import functionality.

Admin can import employees using:

```text
Excel
CSV
```

Provide:

```text
Download template
Upload
Validate
Preview
Import
Error report
```

Do not import invalid records silently.

---

# 34. EMPLOYEE PHOTO

Employee profile should support photo.

Display:

```text
Avatar
Full profile photo
```

Resize/compress uploaded images.

Validate image MIME type and size.

---

# 35. DOCUMENT EXPIRATION

Create scheduled job:

```text
documents:check-expiration
```

Run daily.

Notifications:

```text
30 days before expiry
7 days before expiry
1 day before expiry
Expired
```

Avoid sending duplicate notifications.

---

# 36. TASK DEADLINES

Create scheduled job:

```text
tasks:check-deadlines
```

Notify:

```text
3 days before deadline
1 day before deadline
On deadline
After deadline
```

Mark overdue tasks automatically.

---

# 37. EXAM REMINDERS

Create scheduled job:

```text
exams:send-reminders
```

Notify employees about:

```text
Upcoming exam
Exam deadline
Failed exam
Retake availability
```

---

# 38. NOTIFICATION CENTER

Header should contain notification bell:

```text
🔔 5
```

Dropdown:

```text
Latest notifications
Mark as read
Mark all as read
View all
```

Notifications page:

```text
All
Unread
Tasks
Exams
Documents
Announcements
```

---

# 39. USER MENU

Top-right user menu:

```text
My Profile
My Documents
My Tasks
My KPI
My Exams
My Attendance
Settings
Logout
```

---

# 40. ADMIN PANEL

Admin navigation should dynamically depend on permissions.

Example:

```text
Dashboard

Organization
  ├── Organizations
  ├── Departments
  ├── Positions

Employees
  ├── Employees
  ├── Documents
  ├── Import

Attendance
  ├── Today
  ├── Reports

Tasks

KPI

Safety & Exams

Announcements

Business Trips

Leave Requests

Notifications

Audit Logs

Settings
```

---

# 41. SETTINGS

Create system settings architecture.

Possible settings:

```text
Organization name
Logo
Contact information
Working hours
Working days
Timezone
Notification settings
Document limits
Attendance settings
Exam settings
KPI settings
```

Do not hard-code configurable business rules.

---

# 42. ORGANIZATION LOGO / BRANDING

Use:

```text
Urganchtransgaz
```

branding.

Allow admin to configure logo.

Use professional corporate design.

Do not make the application visually childish.

---

# 43. ACCESSIBILITY

Use accessible:

```text
Buttons
Forms
Inputs
Dialogs
Tables
Navigation
```

Provide readable contrast.

Keyboard navigation should work where practical.

---

# 44. ERROR HANDLING

Frontend must display friendly error messages.

Examples:

```text
Ma'lumotlarni yuklashda xatolik yuz berdi.
Sizda ushbu amalni bajarish uchun ruxsat mavjud emas.
Fayl hajmi juda katta.
Hujjat muvaffaqiyatli yuklandi.
```

Backend errors must be logged.

Never expose stack traces in production.

---

# 45. LOGGING

Use Laravel logging.

Log:

```text
Errors
Critical exceptions
Security-related events
Scheduled jobs
Integration failures
```

Do not log passwords or sensitive personal information.

---

# 46. SEED DATA

Create realistic seed data.

Seed:

```text
1 Central Office
14 subordinate organizations

Example departments
Example positions

Admin
Central Admin
Organization Admin
Manager
Safety Manager
Technical Policy user
Employee

Example employees
Example tasks
Example KPI
Example exam
Example announcement
```

Clearly mark demo/test data.

---

# 47. DEVELOPMENT ENVIRONMENT

The project must run locally in VS Code.

Provide:

```text
README.md
.env.example
database migrations
seeders
API documentation
installation instructions
```

Expected development commands:

```bash
composer install
npm install

php artisan migrate
php artisan db:seed

php artisan serve
npm run dev
```

---

# 48. DOCKER SUPPORT

Prepare Docker support.

Recommended:

```text
PHP
Nginx
MySQL
Redis
Node
```

Redis can be used later for:

```text
Queues
Caching
Notifications
```

Provide:

```text
docker-compose.yml
```

but ensure the project can also run without Docker.

---

# 49. PRODUCTION DEPLOYMENT

Prepare architecture for:

```text
my.urtg.uz
```

Recommended production architecture:

```text
Internet
   │
   ▼
Nginx
   │
   ├── Vue frontend
   │
   └── Laravel API
           │
           ├── MySQL
           ├── Redis
           └── Storage
```

Use HTTPS.

Do not put MySQL directly on the public internet.

---

# 50. BACKUP

Prepare database backup architecture.

Important data:

```text
MySQL
Employee documents
Application configuration
```

Document a backup strategy in README.

---

# 51. API DOCUMENTATION

Create API documentation.

Use OpenAPI/Swagger if practical.

Document:

```text
Authentication
Employees
Organizations
Departments
Documents
Attendance
Tasks
KPI
Exams
Announcements
Notifications
```

---

# 52. TESTING

Create automated tests.

Backend:

```text
Feature tests
Unit tests
Authorization tests
API tests
```

Important security test:

```text
Organization A user must not access Organization B employee.
```

Test:

```text
Login
Employee CRUD
Document upload
Task assignment
Task completion
Exam submission
KPI visibility
Announcement targeting
Permissions
```

---

# 53. PERFORMANCE

The application may eventually contain thousands of employees.

Therefore:

- use database indexes;
- eager load relationships;
- avoid N+1 queries;
- use pagination;
- cache suitable data;
- queue heavy jobs;
- optimize file handling;
- use server-side filtering;
- use database-level aggregation for reports.

Do not retrieve all employees into memory for dashboard statistics.

---

# 54. CODE QUALITY

Follow:

```text
SOLID
DRY
KISS
Clean Architecture principles where useful
Laravel conventions
Vue conventions
REST principles
```

Use:

```text
Form Requests
Policies
Services
Actions
Resources
Repositories only when actually useful
```

Do not create unnecessary abstraction.

Keep the code readable.

---

# 55. IMPORTANT DEVELOPMENT RULE

Do NOT generate the whole project as one giant file.

Separate functionality properly.

Backend example:

```text
app/
├── Actions/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Models/
├── Policies/
├── Services/
├── Notifications/
├── Jobs/
└── Console/
```

Frontend:

```text
src/
├── components/
├── layouts/
├── views/
├── router/
├── stores/
├── services/
├── composables/
├── types/
├── plugins/
└── utils/
```

---

# 56. DEVELOPMENT APPROACH

Implement the system incrementally.

DO NOT try to create every module in one step without verifying the architecture.

Use this order:

## Phase 1

Project foundation:

```text
Laravel
Vue
Vuetify
MySQL
Authentication
RBAC
Organizations
Departments
Employees
```

## Phase 2

Employee self-service:

```text
Profile
Documents
Document approval
Notifications
```

## Phase 3

Attendance:

```text
Attendance
Reports
API foundation for biometric devices
```

## Phase 4

Tasks:

```text
Tasks
Assignments
Comments
History
Attachments
Notifications
```

## Phase 5

Safety exams:

```text
Exam
Questions
Attempts
Results
Reports
Reminders
```

## Phase 6

KPI:

```text
KPI templates
Indicators
Periods
Employee KPI
Reports
Charts
```

## Phase 7

Announcements:

```text
Announcements
Targeting
Read status
Notifications
```

## Phase 8

Business trips / leave:

```text
Business trips
Leave requests
Approval workflows
```

## Phase 9

Advanced features:

```text
Global search
Audit logs
Import/export
Advanced reports
System settings
```

---

# 57. CLAUDE CODE WORKFLOW

When working on this project:

1. First inspect the existing repository.
2. Do not overwrite existing working functionality without reason.
3. Identify the current Laravel/Vue versions.
4. Check existing migrations and models.
5. Propose the architecture before implementing large changes.
6. Implement one phase at a time.
7. After each phase run tests.
8. Run migrations.
9. Run frontend build.
10. Fix errors before continuing.
11. Keep README updated.
12. Never create fake functionality just to make the UI look complete.

---

# 58. CLAUDE CODE COMMANDS

Useful commands:

```bash
php artisan migrate
php artisan migrate:fresh --seed

php artisan test

php artisan route:list

php artisan optimize:clear

npm run dev
npm run build
```

Before finishing a feature, run appropriate tests and build commands.

---

# 59. GIT

Use Git properly.

Recommended commits:

```text
feat: initialize employee portal
feat: add authentication and RBAC
feat: add organization management
feat: add employee management
feat: add document management
feat: add attendance module
feat: add task management
feat: add safety exams
feat: add KPI module
feat: add announcements
feat: add notifications
feat: add audit logs
```

Do not make giant meaningless commits.

---

# 60. IMPORTANT BUSINESS RULES

### Employee

An employee can:

```text
view own profile
update allowed personal fields
upload documents
view own attendance
view own tasks
update task progress
take assigned exams
view own KPI
read announcements
view notifications
```

### Manager

A manager can:

```text
view permitted employees
assign tasks
monitor tasks
review KPI
view attendance
```

### Safety Department

Can:

```text
create exams
manage questions
assign exams
review results
monitor failed employees
```

### Technical Policy Service

Can:

```text
create tasks
assign tasks
monitor task execution
```

Access must respect permissions and organizational scope.

### Central Administrator

Can manage the entire organization.

### Employee

Must NEVER be able to:

```text
change their own role
change their organization
change their department without approval
change their employee number
view another employee's private documents
view unauthorized KPI
view unauthorized attendance
```

---

# 61. FUTURE INTEGRATIONS

Keep architecture ready for:

```text
Active Directory / LDAP
Hikvision attendance terminals
Telegram notifications
Email
Firebase push notifications
Mobile application
Corporate SSO
External government systems
```

Do not implement integrations until explicitly requested.

Create clean service boundaries.

---

# 62. API INTEGRATION FOR BIOMETRIC DEVICES

Prepare an endpoint such as:

```text
POST /api/v1/integrations/attendance/events
```

Expected conceptual payload:

```json
{
  "device_id": "DEVICE001",
  "employee_number": "EMP00125",
  "event_type": "check_in",
  "event_time": "2026-09-15T08:31:00+05:00"
}
```

Authenticate integration requests securely.

Do not rely only on employee-submitted IDs.

---

# 63. FUTURE MOBILE FEATURES

Design APIs to eventually support:

```text
Mobile login
Push notifications
Attendance
Tasks
Exam
KPI
Announcements
Documents
Profile
```

Potential future mobile home screen:

```text
Profile
Attendance
Tasks
Exams
KPI
Announcements
Notifications
```

---

# 64. UI COMPONENT SYSTEM

Create reusable components:

```text
AppDataTable
AppForm
AppDialog
AppConfirmDialog
AppFileUpload
AppStatusChip
AppEmptyState
AppLoading
AppError
AppPagination
AppSearch
AppDatePicker
AppNotification
AppAvatar
AppPageHeader
AppStatCard
```

Avoid duplicating UI logic across pages.

---

# 65. STATUS COLORS

Use Vuetify semantic colors rather than manually defining dozens of custom colors.

Examples:

```text
success
warning
error
info
primary
secondary
```

Sidebar should remain dark navy/blue.

---

# 66. DARK MODE

Prepare the application for dark mode.

The initial default theme should be:

```text
Light content area
Dark navy sidebar
```

Architecture should allow dark mode later.

---

# 67. EMPLOYEE DIRECTORY

Create an employee directory.

Display:

```text
Photo
Full name
Position
Organization
Department
Phone
Status
```

Search/filter:

```text
Name
Employee number
Organization
Department
Position
Status
```

Private information must remain hidden according to permissions.

---

# 68. ORGANIZATION TREE

Create a visual organization tree.

Example:

```text
Urganchtransgaz MCHJ
├── Central Office
│   ├── AT Service
│   ├── HR
│   ├── Technical Policy Service
│   └── ...
│
├── Organization 1
├── Organization 2
...
└── Organization 14
```

Clicking an organization should show:

```text
Departments
Employee count
Manager
Statistics
```

---

# 69. REPORTING

Create a reporting architecture.

Reports:

```text
Employee report
Attendance report
Task report
KPI report
Exam report
Document expiration report
Organization report
```

Reports must support filters.

Example:

```text
Organization
Department
Date range
Employee
Status
```

---

# 70. AUDITABILITY

All important administrative actions must be traceable.

Example:

```text
Who?
What?
When?
Which record?
Old value?
New value?
IP?
```

This is especially important for:

```text
Employee data
Documents
Attendance
KPI
Exam results
Tasks
Permissions
```

---

# 71. FINAL ACCEPTANCE CRITERIA

The project is considered successful when:

### Authentication

- User can login/logout.
- Unauthorized users cannot access protected pages.

### Organization

- Central Office and subordinate organizations work.
- Departments belong to organizations.
- Employees belong to departments.

### Employees

- Employee profiles work.
- Employee can update permitted information.
- HR/admin can manage employees.

### Documents

- Employees can upload documents.
- Admin can approve/reject.
- Expiration tracking works.

### Attendance

- Attendance records work.
- Reports work.
- API is ready for biometric integration.

### Tasks

- Manager/authorized user can create tasks.
- Employees can receive tasks.
- Employees can update progress.
- Task history works.
- Deadline notifications work.

### Exams

- Safety department can create exams.
- Employees can take exams.
- Results are calculated.
- Passing/failing works.
- Reports work.

### KPI

- KPI indicators can be configured.
- KPI periods work.
- Employee KPI can be calculated/stored.
- Employee can see permitted KPI results.

### Announcements

- Admin can publish announcements.
- Targeting works.
- Read/unread works.

### Notifications

- In-app notifications work.
- Notification center works.

### Security

- RBAC works.
- Organization-level isolation works.
- Unauthorized API access is blocked.
- Private documents are protected.

### UI

- Modern Vuetify interface.
- Dark navy sidebar.
- Responsive desktop/tablet/mobile.
- Clean dashboard.
- Consistent components.

### Future

- API is versioned.
- Backend is mobile-ready.
- Documentation exists.
- Tests exist.
- Docker support exists.

---

# 72. FIRST TASK FOR CLAUDE CODE

When this prompt is provided to Claude Code, DO NOT immediately implement all modules.

First:

1. Inspect the repository.
2. Determine whether Laravel/Vue already exists.
3. Determine Laravel and Vue versions.
4. Inspect existing package.json and composer.json.
5. Inspect existing database structure.
6. Inspect existing routes.
7. Inspect existing authentication.
8. Inspect existing frontend architecture.
9. Create an implementation plan.

Then report:

```text
Current project state
Architecture proposal
Database proposal
Authentication proposal
RBAC proposal
Frontend structure
Implementation phases
Potential risks
```

After the plan is approved, start with Phase 1.

If the repository is empty, initialize the project according to this specification.

---

# 73. IMPORTANT CLAUDE CODE BEHAVIOR

You are responsible for writing real working code.

Do not:

- create placeholder pages without functionality;
- hard-code demo values into production logic;
- bypass authorization;
- put business logic in Vue;
- expose private documents publicly;
- use fake API responses;
- ignore validation;
- ignore database relationships;
- duplicate business logic;
- skip tests.

When a requirement is ambiguous, choose the most maintainable enterprise architecture and document the decision.

Prioritize:

```text
Security
Correctness
Maintainability
Scalability
User experience
Performance
Mobile readiness
```

---

# 74. PROJECT NAME

Use:

```text
my-urtg
```

Application name:

```text
Urganchtransgaz Employee Portal
```

Domain:

```text
https://my.urtg.uz
```

---

# 75. FINAL RESULT

The final application should feel like a professional corporate HR/employee management platform rather than a simple CRUD admin panel.

It should be suitable for use by:

```text
Urganchtransgaz MCHJ
Central Office
14 subordinate organizations
Departments
Managers
HR
Safety Department
Technical Policy Service
All Employees
```

The architecture must be capable of growing into a larger corporate digital platform.

Start by inspecting the existing project and preparing the implementation plan.