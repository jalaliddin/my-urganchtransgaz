<?php

use App\Enums\ActiveStatus;
use App\Enums\OrganizationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;

it('casts type and status to their backed enums', function () {
    $organization = Organization::factory()->create(['type' => 'subordinate', 'status' => 'active']);

    expect($organization->type)->toBe(OrganizationType::Subordinate)
        ->and($organization->status)->toBe(ActiveStatus::Active);
});

it('resolves its parent and children relationship', function () {
    $central = Organization::factory()->central()->create();
    $subordinate = Organization::factory()->create(['parent_id' => $central->id]);

    expect($subordinate->parent->is($central))->toBeTrue()
        ->and($central->children->pluck('id'))->toContain($subordinate->id);
});

it('supports organizations with no parent for unlimited-depth hierarchies', function () {
    $organization = Organization::factory()->create(['parent_id' => null]);

    expect($organization->parent)->toBeNull();
});

it('lists its departments and employees', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $employee = Employee::factory()->create(['organization_id' => $organization->id]);

    expect($organization->departments->pluck('id'))->toContain($department->id)
        ->and($organization->employees->pluck('id'))->toContain($employee->id);
});

it('is soft deleted rather than removed from the database', function () {
    $organization = Organization::factory()->create();

    $organization->delete();

    expect(Organization::find($organization->id))->toBeNull();
    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});
