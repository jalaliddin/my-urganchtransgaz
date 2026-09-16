<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // Pin "today" to a Wednesday so "yesterday" is always Tuesday — a
    // working day under the default Mon-Fri setting — keeping these
    // pre-existing tests deterministic regardless of which real-world
    // weekday the suite happens to run on.
    Carbon::setTestNow(Carbon::parse('2026-09-16'));
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
