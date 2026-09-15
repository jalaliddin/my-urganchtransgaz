<?php

use App\Enums\ContactType;
use App\Models\Employee;
use App\Models\EmployeeContact;

it('casts type to its backed enum', function () {
    $contact = EmployeeContact::factory()->create(['type' => 'bank']);

    expect($contact->type)->toBe(ContactType::Bank);
});

it('belongs to an employee', function () {
    $employee = Employee::factory()->create();
    $contact = EmployeeContact::factory()->create(['employee_id' => $employee->id]);

    expect($contact->employee->is($employee))->toBeTrue();
});
