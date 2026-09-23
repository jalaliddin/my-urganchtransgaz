<?php

use App\Models\AttendanceDevice;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

it('rejects an event with no token', function () {
    $this->postJson('/api/v1/integrations/attendance/events', [])->assertStatus(401);
});

it('rejects an event with an invalid token', function () {
    AttendanceDevice::factory()->withToken('correct-token')->create(['device_id' => 'DEV-1']);

    $this->withToken('wrong-token')
        ->postJson('/api/v1/integrations/attendance/events', [])
        ->assertStatus(401);
});

it('rejects an event from an inactive device', function () {
    AttendanceDevice::factory()->inactive()->withToken('a-token')->create(['device_id' => 'DEV-1']);

    $this->withToken('a-token')
        ->postJson('/api/v1/integrations/attendance/events', [])
        ->assertStatus(401);
});

it('rejects an event for an unknown employee_number', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => 'NOPE-000',
        'event_type' => 'check_in',
        'event_time' => Carbon::now()->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors('employee_number');
});

it('rejects an event whose device_id does not match the authenticated device', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-2',
        'employee_number' => $employee->employee_number,
        'event_type' => 'check_in',
        'event_time' => Carbon::now()->toDateTimeString(),
    ])->assertStatus(422);
});

it('records a check-in event from an authenticated device by employee_number', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => 'check_in',
        'event_time' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
    ])->assertCreated();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'source' => 'biometric',
    ]);
});

it('rejects an event for an unknown dahua_person_id', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'dahua_person_id' => '9999',
        'event_type' => 'check_in',
        'event_time' => Carbon::now()->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors('dahua_person_id');
});

it('rejects an event with neither employee_number nor dahua_person_id', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'event_type' => 'check_in',
        'event_time' => Carbon::now()->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['employee_number', 'dahua_person_id']);
});

it('rejects an event that sends both employee_number and dahua_person_id', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001', 'dahua_person_id' => '1001']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'dahua_person_id' => $employee->dahua_person_id,
        'event_type' => 'check_in',
        'event_time' => Carbon::now()->toDateTimeString(),
    ])->assertStatus(422)->assertJsonValidationErrors(['employee_number']);
});

it('records a check-in event from the Dahua bridge by dahua_person_id', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-TURNSTILE']);
    $employee = Employee::factory()->create(['dahua_person_id' => '1001']);

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-TURNSTILE',
        'dahua_person_id' => '1001',
        'event_type' => 'check_in',
        'event_time' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
    ])->assertCreated();

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'source' => 'biometric',
    ]);
});

it('lets a repeated device check-in for the same day pass through as a no-op', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $payload = [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => 'check_in',
        'event_time' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
    ];

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', $payload)->assertCreated();
    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', $payload)->assertCreated();

    expect(AttendanceRecord::where('employee_id', $employee->id)->count())->toBe(1);
});
