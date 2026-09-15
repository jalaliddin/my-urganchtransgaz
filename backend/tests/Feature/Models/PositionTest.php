<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);

    expect($position->organization->is($organization))->toBeTrue();
});

it('lists the employees that hold it', function () {
    $position = Position::factory()->create();
    $employee = Employee::factory()->create([
        'organization_id' => $position->organization_id,
        'position_id' => $position->id,
    ]);

    expect($position->employees->pluck('id'))->toContain($employee->id);
});
