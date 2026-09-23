<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Department;
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

it('lists the raw scan log behind one daily record, in chronological order', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => $hr->employee->organization_id]);
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-23']);

    AttendanceEvent::factory()->checkOut()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 18:00:00']);
    AttendanceEvent::factory()->checkIn()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 09:00:00']);
    AttendanceEvent::factory()->checkOut()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 13:00:00']);
    // A different day for the same employee must not leak into the list.
    AttendanceEvent::factory()->checkIn()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-22 09:00:00']);

    $response = $this->actingAs($hr, 'sanctum')->getJson("/api/v1/attendance/{$record->id}/events");

    $response->assertOk();
    $times = collect($response->json('data'))->pluck('occurred_at');
    expect($times)->toHaveCount(3)
        ->and($times->first())->toContain('09:00:00')
        ->and($times->last())->toContain('18:00:00');
});

it('forbids viewing another organization\'s attendance events', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $record = AttendanceRecord::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => Organization::factory()->create()->id])->id,
    ]);

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/attendance/{$record->id}/events")
        ->assertStatus(403);
});

it('attaches each listed record\'s visits_count, computed from the raw scan log', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => $hr->employee->organization_id]);
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-23']);

    AttendanceEvent::factory()->checkIn()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 09:00:00']);
    AttendanceEvent::factory()->checkOut()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 13:00:00']);
    AttendanceEvent::factory()->checkIn()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 14:00:00']);
    AttendanceEvent::factory()->checkOut()->create(['employee_id' => $employee->id, 'occurred_at' => '2026-09-23 18:00:00']);

    $response = $this->actingAs($hr, 'sanctum')->getJson("/api/v1/attendance?filter[employee_id]={$employee->id}");

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('employee_id', $employee->id);
    expect($row['visits_count'])->toBe(4);
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
        ->present_count->toBe(1)
        ->late_count->toBe(1)
        ->absent_count->toBe(0)
        ->business_trip_count->toBe(0)
        ->vacation_count->toBe(0)
        ->sick_leave_count->toBe(0);
});

it('builds a monthly timesheet grid with one cell per calendar day and correct totals', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();

    // September 2026 has 30 days; days 3 and 4 get real records, everything
    // else stays "no record" so the grid's blanks/weekends can be checked.
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-09-03',
        'status' => AttendanceStatus::Present,
        'worked_minutes' => 540,
    ]);
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-09-04',
        'status' => AttendanceStatus::Late,
        'worked_minutes' => 500,
    ]);

    $response = $this->actingAs($hr, 'sanctum')
        ->getJson("/api/v1/attendance/timesheet?month=2026-09&employee_id={$employee->id}");

    $response->assertOk();
    $data = $response->json('data');

    expect($data['day_count'])->toBe(30)
        ->and($data['from'])->toBe('2026-09-01')
        ->and($data['to'])->toBe('2026-09-30');

    $row = collect($data['employees'])->firstWhere('employee_id', $employee->id);
    expect($row)->not->toBeNull();
    expect($row['days'])->toHaveCount(30);

    $day3 = collect($row['days'])->firstWhere('day', 3);
    $day4 = collect($row['days'])->firstWhere('day', 4);
    $day1 = collect($row['days'])->firstWhere('day', 1);

    expect($day3)->status->toBe('present')->short_code->toBe('K')->worked_minutes->toBe(540);
    expect($day4)->status->toBe('late')->short_code->toBe('K/K')->worked_minutes->toBe(500);
    expect($day1)->status->toBeNull()->worked_minutes->toBeNull();

    expect($row['totals'])
        ->present_count->toBe(1)
        ->late_count->toBe(1)
        ->absent_count->toBe(0)
        ->total_worked_minutes->toBe(1040);
});

it('marks non-working days as weekends in the timesheet, using the configured working days', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();

    $response = $this->actingAs($hr, 'sanctum')
        ->getJson("/api/v1/attendance/timesheet?month=2026-09&employee_id={$employee->id}");

    $row = collect($response->json('data.employees'))->firstWhere('employee_id', $employee->id);

    foreach ($row['days'] as $day) {
        $expectedWeekend = ! in_array(Carbon::create(2026, 9, $day['day'])->isoWeekday(), [1, 2, 3, 4, 5], true);
        expect($day['is_weekend'])->toBe($expectedWeekend);
    }
});

it('defaults the timesheet to the current month when none is given', function () {
    $hr = userWithRole('hr', Organization::factory()->create());

    $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/attendance/timesheet');

    $response->assertOk()
        ->assertJsonPath('data.from', now()->startOfMonth()->toDateString())
        ->assertJsonPath('data.to', now()->endOfMonth()->toDateString());
});

it('scopes the timesheet to a department-manager\'s own department', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $inDepartment = Employee::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]);
    $outsideDepartment = Employee::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/attendance/timesheet');

    $ids = collect($response->json('data.employees'))->pluck('employee_id');
    expect($ids)->toContain($manager->employee->id, $inDepartment->id)
        ->not->toContain($outsideDepartment->id);
});

it('shows a plain employee the whole organization\'s timesheet, same breadth as the today board', function () {
    // attendance.view is granted broadly, including the base employee
    // role (see backend/README.md's Phase 3 notes) — "self vs. everyone"
    // is not the distinction; org/department breadth is. This mirrors
    // today()'s own scoping exactly.
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $colleague = Employee::factory()->create(['organization_id' => $organization->id]);
    $otherOrgEmployee = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $response = $this->actingAs($employee, 'sanctum')->getJson('/api/v1/attendance/timesheet');

    $ids = collect($response->json('data.employees'))->pluck('employee_id');
    expect($ids)->toContain($employee->employee->id, $colleague->id)
        ->not->toContain($otherOrgEmployee->id);
});

it('exports the timesheet as a downloadable csv with day and total columns', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-09-03', 'status' => AttendanceStatus::Present]);

    $response = $this->actingAs($hr, 'sanctum')
        ->get("/api/v1/attendance/timesheet?month=2026-09&employee_id={$employee->id}&export=csv");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain($employee->employee_number)->toContain('Tabel raqami');
});
