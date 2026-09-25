<?php

use App\Enums\AbsenceType;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Organization;
use App\Models\Setting;
use App\Notifications\AbsenceCancelled;
use App\Notifications\AbsenceRecorded;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing absences without authentication', function () {
    $this->getJson('/api/v1/absences')->assertStatus(401);
});

it('lets hr record annual leave for an employee in any organization', function () {
    $this->travelTo('2026-10-01');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employee->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-18',
        'document_number' => '145-K',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.days', 14)
        ->assertJsonPath('data.state', 'upcoming');
    $this->assertDatabaseHas('employee_absences', [
        'employee_id' => $employee->id,
        'type' => 'annual_leave',
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-18',
        'document_number' => '145-K',
        'created_by' => $hr->id,
    ]);
});

it('notifies the employee and their department manager when an absence is recorded', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization);
    $department = Department::factory()->create(['organization_id' => $organization->id, 'manager_id' => $manager->employee->id]);
    $employeeUser = userWithRole('employee', $organization, $department);
    Notification::fake();

    $this->actingAs(userWithRole('hr', $organization), 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employeeUser->employee->id,
        'type' => AbsenceType::SickLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-07',
    ])->assertCreated();

    Notification::assertSentTo([$employeeUser, $manager], AbsenceRecorded::class);
});

it('forbids a plain employee from recording an absence with 403', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $user->employee->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-10',
    ])->assertForbidden();

    $this->assertDatabaseCount('employee_absences', 0);
});

it('forbids an organization admin from recording an absence for another organization with 403', function () {
    $admin = userWithRole('organization-admin', Organization::factory()->create());
    $outsider = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $outsider->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-10',
    ])->assertForbidden();
});

it('requires the employee, type and both dates', function () {
    $hr = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'type', 'start_date', 'end_date']);
});

it('rejects an end date before the start date', function () {
    $hr = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => Employee::factory()->create()->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-05',
    ])->assertUnprocessable()->assertJsonValidationErrors('end_date');
});

it('requires a destination for a business trip', function () {
    $hr = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => Employee::factory()->create()->id,
        'type' => AbsenceType::BusinessTrip->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-08',
    ])->assertUnprocessable()->assertJsonValidationErrors('destination');
});

it('rejects an absence that overlaps another one for the same employee', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    EmployeeAbsence::factory()->for($employee)->between('2026-10-05', '2026-10-18')->create();

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employee->id,
        'type' => AbsenceType::SickLeave->value,
        'start_date' => '2026-10-18',
        'end_date' => '2026-10-20',
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'start_date' => "Bu davr xodimning boshqa yozuvi bilan kesishadi: Yillik mehnat ta'tili (05.10.2026 — 18.10.2026).",
    ]);

    expect(EmployeeAbsence::count())->toBe(1);
});

it('allows an absence over the dates of a cancelled one', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    EmployeeAbsence::factory()->for($employee)->between('2026-10-05', '2026-10-18')->cancelled()->create();

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employee->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-18',
    ])->assertCreated();
});

it('excuses past unexcused days a late sick leave certificate covers, leaving worked days alone', function () {
    $this->travelTo('2026-10-10');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    $absentDay = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-06', 'status' => AttendanceStatus::Absent]);
    $workedDay = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-07', 'status' => AttendanceStatus::Present]);

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employee->id,
        'type' => AbsenceType::SickLeave->value,
        'start_date' => '2026-10-06',
        'end_date' => '2026-10-08',
    ])->assertCreated();

    expect($absentDay->fresh()->status)->toBe(AttendanceStatus::SickLeave)
        ->and($workedDay->fresh()->status)->toBe(AttendanceStatus::Present);
});

it('switches the employee to vacation when the recorded leave covers today', function () {
    $this->travelTo('2026-10-10');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Active]);

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employee->id,
        'type' => AbsenceType::StudyLeave->value,
        'start_date' => '2026-10-09',
        'end_date' => '2026-10-20',
    ])->assertCreated();

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Vacation);
});

it('cancelling an absence puts excused days back to absent and the employee back to active', function () {
    $this->travelTo('2026-10-10');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $employee = $employeeUser->employee;
    $employee->update(['status' => EmployeeStatus::SickLeave]);
    $absence = EmployeeAbsence::factory()->for($employee)->ofType(AbsenceType::SickLeave)->between('2026-10-06', '2026-10-12')->create();
    $excusedDay = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-06',
        'status' => AttendanceStatus::SickLeave,
        'employee_absence_id' => $absence->id,
    ]);
    Notification::fake();

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/absences/{$absence->id}/cancel", ['reason' => 'Varaqa qalbaki'])
        ->assertOk()
        ->assertJsonPath('data.state', 'cancelled');

    expect($excusedDay->fresh())
        ->status->toBe(AttendanceStatus::Absent)
        ->employee_absence_id->toBeNull();
    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
    $this->assertDatabaseHas('employee_absences', ['id' => $absence->id, 'cancellation_reason' => 'Varaqa qalbaki', 'cancelled_by' => $hr->id]);
    Notification::assertSentTo($employeeUser, AbsenceCancelled::class);
});

it('moving an absence unexcuses the days it no longer covers', function () {
    $this->travelTo('2026-10-20');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    $absence = EmployeeAbsence::factory()->for($employee)->between('2026-10-05', '2026-10-09')->create();
    $droppedDay = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-05', 'status' => AttendanceStatus::Vacation, 'employee_absence_id' => $absence->id]);
    $keptDay = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'date' => '2026-10-08', 'status' => AttendanceStatus::Vacation, 'employee_absence_id' => $absence->id]);

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/absences/{$absence->id}", [
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-07',
        'end_date' => '2026-10-09',
    ])->assertOk()->assertJsonPath('data.days', 3);

    expect($droppedDay->fresh()->status)->toBe(AttendanceStatus::Absent)
        ->and($keptDay->fresh()->status)->toBe(AttendanceStatus::Vacation);
});

it('refuses to edit a cancelled absence with 403', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $absence = EmployeeAbsence::factory()->cancelled()->create();

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/absences/{$absence->id}", [
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-07',
        'end_date' => '2026-10-09',
    ])->assertForbidden();
});

it('only lists an employee their own absences', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    EmployeeAbsence::factory()->for($user->employee)->create();
    EmployeeAbsence::factory()->create();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/absences')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('only lists a department manager the absences in their department', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    EmployeeAbsence::factory()->for(Employee::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]))->create();
    EmployeeAbsence::factory()->for(Employee::factory()->create(['organization_id' => $organization->id]))->create();

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/absences')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});

it('filters absences to the ones in effect today', function () {
    $this->travelTo('2026-10-10');
    $hr = userWithRole('hr', Organization::factory()->create());
    $current = EmployeeAbsence::factory()->between('2026-10-08', '2026-10-12')->create();
    EmployeeAbsence::factory()->between('2026-10-15', '2026-10-20')->create();
    EmployeeAbsence::factory()->between('2026-10-08', '2026-10-12')->cancelled()->create();

    $this->actingAs($hr, 'sanctum')->getJson('/api/v1/absences?filter[state]=current')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $current->id);
});

it('forbids a manager from viewing an absence in another organization with 403', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $absence = EmployeeAbsence::factory()->for(Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]))->create();

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/absences/{$absence->id}")->assertForbidden();
});

it('stores the attached order and lets the employee download it', function () {
    Storage::fake('local');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => $employeeUser->employee->id,
        'type' => AbsenceType::BusinessTrip->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-08',
        'destination' => 'Toshkent',
        'file' => UploadedFile::fake()->create('buyruq.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    $absence = EmployeeAbsence::findOrFail($response->json('data.id'));
    Storage::disk('local')->assertExists($absence->file_path);
    expect($absence->file_name)->toBe('buyruq.pdf');

    $this->actingAs($employeeUser, 'sanctum')
        ->get("/api/v1/absences/{$absence->id}/download")
        ->assertOk()
        ->assertDownload('buyruq.pdf');
});

it('counts only the annual leave days inside the year against the entitlement', function () {
    Setting::set('absences.annual_leave_days', 24, 'absences');
    $hr = userWithRole('hr', Organization::factory()->create());
    $employee = Employee::factory()->create();
    EmployeeAbsence::factory()->for($employee)->between('2026-12-28', '2027-01-05')->create();
    EmployeeAbsence::factory()->for($employee)->between('2026-06-01', '2026-06-10')->create();
    EmployeeAbsence::factory()->for($employee)->between('2026-07-01', '2026-07-10')->cancelled()->create();
    EmployeeAbsence::factory()->for($employee)->ofType(AbsenceType::SickLeave)->between('2026-03-02', '2026-03-04')->create();

    $response = $this->actingAs($hr, 'sanctum')->getJson("/api/v1/employees/{$employee->id}/leave-balance?year=2026");

    $response->assertOk()
        ->assertJsonPath('data.entitlement_days', 24)
        ->assertJsonPath('data.used_days', 14)
        ->assertJsonPath('data.remaining_days', 10)
        ->assertJsonPath('data.days_by_type.sick_leave', 3);
});

it('forbids a plain employee from reading a colleague\'s leave balance with 403', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $colleague = Employee::factory()->create(['organization_id' => $user->employee->organization_id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/employees/{$colleague->id}/leave-balance")
        ->assertForbidden();
});

it('returns the absences as a spreadsheet export', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    EmployeeAbsence::factory()->create();

    $this->actingAs($hr, 'sanctum')
        ->get('/api/v1/absences?export=csv')
        ->assertOk()
        ->assertDownload('absences.csv');
});

it('forbids a manager who can only view absences from recording one with 403', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/absences', [
        'employee_id' => Employee::factory()->create(['organization_id' => $organization->id])->id,
        'type' => AbsenceType::AnnualLeave->value,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-10',
    ])->assertForbidden();
});
