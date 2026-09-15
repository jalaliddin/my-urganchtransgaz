<?php

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

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
