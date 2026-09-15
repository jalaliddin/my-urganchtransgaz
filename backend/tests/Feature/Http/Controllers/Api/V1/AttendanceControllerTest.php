<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing attendance without authentication', function () {
    $this->getJson('/api/v1/attendance')->assertStatus(401);
});

it('lets an employee check in and check out, computing worked minutes', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->travelTo(Carbon::parse('09:00'));
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')
        ->assertOk()
        ->assertJsonPath('data.status', 'present');

    $this->travelTo(Carbon::parse('18:00'));
    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-out');

    $response->assertOk()
        ->assertJsonPath('data.status', 'present')
        ->assertJsonPath('data.worked_minutes', 540);

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $user->employee->id,
        'worked_minutes' => 540,
        'status' => AttendanceStatus::Present->value,
    ]);
});

it('marks a check-in past the grace period as late', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->travelTo(Carbon::parse('09:30'));
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')
        ->assertOk()
        ->assertJsonPath('data.status', 'late');
});

it('marks a check-out before the grace period as early leave', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->travelTo(Carbon::parse('09:00'));
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in');

    $this->travelTo(Carbon::parse('16:00'));
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-out')
        ->assertOk()
        ->assertJsonPath('data.status', 'early_leave');
});

it('rejects a duplicate check-in for the same day', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertStatus(409);
});

it('rejects a check-out before checking in', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-out')->assertStatus(409);
});

it('rejects a duplicate check-out for the same day', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-in')->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-out')->assertOk();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/attendance/check-out')->assertStatus(409);
});

it('forbids a manager from viewing an attendance record outside their organization', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $record = AttendanceRecord::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => Organization::factory()->create()->id])->id,
    ]);

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/attendance/{$record->id}")
        ->assertStatus(403);
});

it('lets hr manually create an attendance record for any employee', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/attendance', [
        'employee_id' => $employee->id,
        'date' => Carbon::today()->toDateString(),
        'check_in' => '09:00',
        'check_out' => '18:00',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'present');
    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'source' => 'manual',
    ]);
});

it('forbids a plain employee from manually creating an attendance record', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/attendance', [
        'employee_id' => $employee->employee->id,
        'date' => Carbon::today()->toDateString(),
    ])->assertStatus(403);
});

it('forbids a manager from creating an attendance record outside their organization', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/attendance', [
        'employee_id' => $employee->id,
        'date' => Carbon::today()->toDateString(),
    ])->assertStatus(403);
});

it('lets hr correct an attendance record', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $record = AttendanceRecord::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => $hr->employee->organization_id])->id,
    ]);

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/attendance/{$record->id}", [
        'check_in' => '09:00',
        'check_out' => '17:30',
        'notes' => 'Corrected after a device outage.',
    ])->assertOk()->assertJsonPath('data.status', 'early_leave');
});

it('lets hr delete an attendance record', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $record = AttendanceRecord::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => $hr->employee->organization_id])->id,
    ]);

    $this->actingAs($hr, 'sanctum')->deleteJson("/api/v1/attendance/{$record->id}")->assertOk();

    $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
});

it('shows the scoped today board including employees with no record yet', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $noShow = Employee::factory()->create(['organization_id' => $organization->id]);
    Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/attendance/today');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('employee_id');
    expect($ids)->toContain($manager->employee->id, $noShow->id);
});

it('aggregates a report by organization using database-level sums', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $organization->id]);

    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => Carbon::today()->toDateString(),
        'status' => AttendanceStatus::Late,
        'worked_minutes' => 480,
    ]);
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => Carbon::yesterday()->toDateString(),
        'status' => AttendanceStatus::Present,
        'worked_minutes' => 540,
    ]);

    $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/attendance/report?group_by=organization&from='.Carbon::yesterday()->toDateString().'&to='.Carbon::today()->toDateString());

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('group_id', $organization->id);
    expect($row)
        ->not->toBeNull()
        ->total_days->toBe(2)
        ->total_worked_minutes->toBe(1020)
        ->late_count->toBe(1);
});
