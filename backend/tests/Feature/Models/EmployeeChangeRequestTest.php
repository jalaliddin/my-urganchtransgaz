<?php

use App\Enums\ChangeRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;

it('casts status to its backed enum and changes to an array', function () {
    $changeRequest = EmployeeChangeRequest::factory()->create([
        'status' => 'pending',
        'changes' => ['first_name' => 'Test'],
    ]);

    expect($changeRequest->status)->toBe(ChangeRequestStatus::Pending)
        ->and($changeRequest->changes)->toBe(['first_name' => 'Test']);
});

it('resolves its employee, requester, and reviewer relationships', function () {
    $employee = Employee::factory()->create();
    $requester = User::factory()->create();
    $reviewer = User::factory()->create();

    $changeRequest = EmployeeChangeRequest::factory()->create([
        'employee_id' => $employee->id,
        'requested_by' => $requester->id,
        'reviewed_by' => $reviewer->id,
    ]);

    expect($changeRequest->employee->is($employee))->toBeTrue()
        ->and($changeRequest->requester->is($requester))->toBeTrue()
        ->and($changeRequest->reviewer->is($reviewer))->toBeTrue();
});
