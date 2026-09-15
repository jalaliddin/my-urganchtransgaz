<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when viewing the profile without authentication', function () {
    $this->getJson('/api/v1/profile')->assertStatus(401);
});

it('shows the authenticated user own employee profile', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $user->employee->id);
});

it('applies a direct-edit field immediately', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['phone' => '+998901112233'])
        ->assertOk()
        ->assertJsonPath('data.change_request', null)
        ->assertJsonPath('data.employee.phone', '+998901112233');

    expect($user->employee->fresh()->phone)->toBe('+998901112233');
});

it('creates a pending change request for an official-record field instead of applying it', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $originalLastName = $user->employee->last_name;

    $response = $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['last_name' => 'Changed']);

    $response->assertOk()
        ->assertJsonPath('data.change_request.status', 'pending')
        ->assertJsonPath('data.change_request.changes.last_name', 'Changed')
        ->assertJsonPath('data.employee.last_name', $originalLastName);

    expect($user->employee->fresh()->last_name)->toBe($originalLastName);
    $this->assertDatabaseHas('employee_change_requests', [
        'employee_id' => $user->employee->id,
        'status' => 'pending',
    ]);
});

it('does not create a change request when the submitted value matches the current one', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['last_name' => $user->employee->last_name])
        ->assertOk()
        ->assertJsonPath('data.change_request', null);

    $this->assertDatabaseCount('employee_change_requests', 0);
});

it('serializes birth_date as a bare calendar date, not a UTC-shifted datetime', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $user->employee->update(['birth_date' => '1990-06-15']);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.birth_date', '1990-06-15');
});

it('does not create a phantom change request when birth_date round-trips unchanged', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $user->employee->update(['birth_date' => '1990-06-15']);

    $shownDate = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/profile')
        ->json('data.birth_date');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['birth_date' => $shownDate])
        ->assertOk()
        ->assertJsonPath('data.change_request', null);

    $this->assertDatabaseCount('employee_change_requests', 0);
});

it('applies safe fields and creates a change request in the same request', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['phone' => '+998900000000', 'first_name' => 'NewFirst'])
        ->assertOk()
        ->assertJsonPath('data.employee.phone', '+998900000000')
        ->assertJsonPath('data.change_request.changes.first_name', 'NewFirst');

    expect($user->employee->fresh())
        ->phone->toBe('+998900000000')
        ->first_name->not->toBe('NewFirst');
});

it('syncs emergency contacts as part of a profile update', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', [
            'contacts' => [
                ['type' => 'emergency', 'full_name' => 'Jane Doe', 'phone' => '+998911111111'],
            ],
        ])
        ->assertOk();

    $this->assertDatabaseHas('employee_contacts', [
        'employee_id' => $user->employee->id,
        'full_name' => 'Jane Doe',
    ]);
});

it('removes a contact omitted from a subsequent profile update', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $contact = $user->employee->contacts()->create([
        'type' => 'emergency',
        'full_name' => 'Old Contact',
    ]);

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['contacts' => []])
        ->assertOk();

    $this->assertDatabaseMissing('employee_contacts', ['id' => $contact->id]);
});

it('reports profile completion percentage and missing sections', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/profile/completion');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['percentage', 'sections', 'missing_sections']]);

    expect($response->json('data.missing_sections'))->toContain('emergency_contact');
});

it('returns 403 when the authenticated user has no employee record', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/v1/profile', ['phone' => '123'])
        ->assertStatus(403);
});
