<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);

    expect($department->organization->is($organization))->toBeTrue();
});

it('resolves its manager as an employee', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = Employee::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]);
    $department->update(['manager_id' => $manager->id]);

    expect($department->fresh()->manager->is($manager))->toBeTrue();
});

it('lists the employees that belong to it', function () {
    $department = Department::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $department->organization_id, 'department_id' => $department->id]);

    expect($department->employees->pluck('id'))->toContain($employee->id);
});

it('is soft deleted rather than removed from the database', function () {
    $department = Department::factory()->create();

    $department->delete();

    expect(Department::find($department->id))->toBeNull();
    $this->assertSoftDeleted('departments', ['id' => $department->id]);
});
