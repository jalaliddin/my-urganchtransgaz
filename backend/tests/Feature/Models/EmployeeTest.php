<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('encrypts passport_number and pinfl at rest', function () {
    $employee = Employee::factory()->create([
        'passport_number' => 'AB1234567',
        'pinfl' => '12345678901234',
    ]);

    $rawRow = DB::table('employees')->where('id', $employee->id)->first();

    expect($rawRow->passport_number)->not->toBe('AB1234567')
        ->and($rawRow->pinfl)->not->toBe('12345678901234')
        ->and($employee->fresh()->passport_number)->toBe('AB1234567')
        ->and($employee->fresh()->pinfl)->toBe('12345678901234');
});

it('hides passport_number and pinfl from array and JSON serialization', function () {
    $employee = Employee::factory()->create([
        'passport_number' => 'AB1234567',
        'pinfl' => '12345678901234',
    ]);

    expect($employee->toArray())
        ->not->toHaveKey('passport_number')
        ->not->toHaveKey('pinfl');
});

it('builds the full name from last, first, and middle name', function () {
    $employee = Employee::factory()->create([
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
        'middle_name' => 'Bekovich',
    ]);

    expect($employee->fullName())->toBe('Karimov Aziz Bekovich');
});

it('resolves its user, organization, department, and position relationships', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    expect($employee->user->is($user))->toBeTrue()
        ->and($employee->organization->is($organization))->toBeTrue()
        ->and($employee->department->is($department))->toBeTrue()
        ->and($employee->position->is($position))->toBeTrue();
});

it('is soft deleted rather than removed from the database', function () {
    $employee = Employee::factory()->create();

    $employee->delete();

    expect(Employee::find($employee->id))->toBeNull();
    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});
