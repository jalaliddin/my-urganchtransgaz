<?php

use App\Enums\AbsenceType;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Organization;
use App\Models\Setting;
use App\Notifications\AttendanceIssue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin "today" to a Wednesday so "yesterday" is always Tuesday — a
    // working day under the default Mon-Fri setting — keeping these
    // pre-existing tests deterministic regardless of which real-world
    // weekday the suite happens to run on.
    Carbon::setTestNow(Carbon::parse('2026-09-16'));
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('marks an active employee with no record yesterday as absent', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'date' => Carbon::yesterday()->toDateString(),
        'status' => AttendanceStatus::Absent->value,
        'source' => 'system',
    ]);
});

it('marks an employee on vacation with the vacation status instead of absent', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Vacation]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'status' => AttendanceStatus::Vacation->value,
    ]);
});

it('does not touch an employee who already has a record for yesterday', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => Carbon::yesterday()->toDateString(),
        'status' => AttendanceStatus::Present,
    ]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    expect(AttendanceRecord::where('employee_id', $employee->id)->count())->toBe(1);
});

it('skips terminated and inactive employees', function () {
    $terminated = Employee::factory()->create(['status' => EmployeeStatus::Terminated]);
    $inactive = Employee::factory()->create(['status' => EmployeeStatus::Inactive]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseMissing('attendance_records', ['employee_id' => $terminated->id]);
    $this->assertDatabaseMissing('attendance_records', ['employee_id' => $inactive->id]);
});

it('creates no records at all when yesterday was not a working day', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-14')); // Monday — yesterday is Sunday
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseMissing('attendance_records', ['employee_id' => $employee->id]);
});

it('respects a Settings-configured working-days list instead of the Mon-Fri default', function () {
    Setting::set('attendance.working_days', [1, 2, 3, 4, 5, 6, 7], 'attendance');
    Carbon::setTestNow(Carbon::parse('2026-09-14')); // yesterday is Sunday, now a working day
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('attendance_records', ['employee_id' => $employee->id]);
});

it('notifies the department manager about an unexcused absence', function () {
    $organization = Organization::factory()->create();
    $managerUser = userWithRole('department-manager', $organization);
    $manager = $managerUser->employee;
    $department = Department::factory()->create(['organization_id' => $organization->id, 'manager_id' => $manager->id]);
    $employee = Employee::factory()->create([
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'status' => EmployeeStatus::Active,
    ]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $manager->user_id,
        'type' => AttendanceIssue::class,
    ]);
});

it('does not notify anyone for an excused vacation/business-trip/sick-leave absence', function () {
    $organization = Organization::factory()->create();
    $managerUser = userWithRole('department-manager', $organization);
    $manager = $managerUser->employee;
    $department = Department::factory()->create(['organization_id' => $organization->id, 'manager_id' => $manager->id]);
    Employee::factory()->create([
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'status' => EmployeeStatus::Vacation,
    ]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseMissing('notifications', ['type' => AttendanceIssue::class]);
});

it('does not fail when the absent employee has no department or manager', function () {
    $employee = Employee::factory()->create(['department_id' => null, 'status' => EmployeeStatus::Active]);

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseMissing('notifications', ['type' => AttendanceIssue::class]);
});

it('records a day covered by an "other" excused absence as excused and links it, without notifying anyone', function () {
    $organization = Organization::factory()->create();
    $managerUser = userWithRole('department-manager', $organization);
    $department = Department::factory()->create(['organization_id' => $organization->id, 'manager_id' => $managerUser->employee->id]);
    $employee = Employee::factory()->create([
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'status' => EmployeeStatus::Active,
    ]);
    $absence = EmployeeAbsence::factory()->for($employee)->ofType(AbsenceType::Other)->between('2026-09-15', '2026-09-15')->create();

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'date' => '2026-09-15',
        'status' => AttendanceStatus::Excused->value,
        'employee_absence_id' => $absence->id,
    ]);
    $this->assertDatabaseMissing('notifications', ['type' => AttendanceIssue::class]);
});

it('marks yesterday absent for an employee whose recorded leave only starts today', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Vacation]);
    EmployeeAbsence::factory()->for($employee)->between('2026-09-16', '2026-09-30')->create();

    $this->artisan('attendance:mark-absentees')->assertSuccessful();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'date' => '2026-09-15',
        'status' => AttendanceStatus::Absent->value,
        'employee_absence_id' => null,
    ]);
});
