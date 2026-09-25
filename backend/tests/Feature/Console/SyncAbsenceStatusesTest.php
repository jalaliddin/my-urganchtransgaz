<?php

use App\Enums\AbsenceType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAbsence;

it('puts an employee whose business trip starts today on business trip', function () {
    $this->travelTo('2026-10-10');
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    EmployeeAbsence::factory()->for($employee)->ofType(AbsenceType::BusinessTrip)->between('2026-10-10', '2026-10-14')->create();

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::BusinessTrip);
});

it('returns an employee to active once their leave has ended', function () {
    $this->travelTo('2026-10-10');
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Vacation]);
    EmployeeAbsence::factory()->for($employee)->between('2026-09-20', '2026-10-09')->create();

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('keeps an employee active during an "other" excused absence', function () {
    $this->travelTo('2026-10-10');
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Vacation]);
    EmployeeAbsence::factory()->for($employee)->ofType(AbsenceType::Other)->between('2026-10-10', '2026-10-10')->create();

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('leaves a hand-set status alone for an employee with no absence records', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::SickLeave]);

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::SickLeave);
});

it('never reactivates a terminated employee', function () {
    $this->travelTo('2026-10-10');
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Terminated]);
    EmployeeAbsence::factory()->for($employee)->between('2026-10-08', '2026-10-12')->create();

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Terminated);
});

it('ignores a cancelled absence covering today', function () {
    $this->travelTo('2026-10-10');
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);
    EmployeeAbsence::factory()->for($employee)->between('2026-10-08', '2026-10-12')->cancelled()->create();

    $this->artisan('absences:sync-statuses')->assertSuccessful();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
});
