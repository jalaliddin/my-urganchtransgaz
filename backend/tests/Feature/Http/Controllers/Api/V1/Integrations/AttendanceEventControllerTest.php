<?php

use App\Models\AttendanceDevice;
use App\Models\AttendanceEvent;
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

it('logs a raw attendance_events row for every device scan, even a repeat that the daily summary ignores', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $payload = [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => 'check_in',
        'event_time' => Carbon::today()->setTime(9, 0)->toDateTimeString(),
    ];

    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', $payload)->assertCreated();
    $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', array_merge($payload, [
        'event_time' => Carbon::today()->setTime(9, 5)->toDateTimeString(),
    ]))->assertCreated();

    // The daily summary stays at one row (first arrival wins), but both
    // individual scans are preserved in the raw log.
    expect(AttendanceRecord::where('employee_id', $employee->id)->count())->toBe(1);
    expect(AttendanceEvent::where('employee_id', $employee->id)->where('type', 'check_in')->count())->toBe(2);
});

it('advances the daily check-out to each new device scan instead of keeping only the first', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $checkOut = fn (string $time) => $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => 'check_out',
        'event_time' => Carbon::today()->setTimeFromTimeString($time)->toDateTimeString(),
    ]);

    // A lunch-time exit, then the real end-of-day exit — the record must
    // reflect the later one, not freeze on the first.
    $checkOut('13:00:00')->assertCreated();
    $checkOut('18:00:00')->assertCreated()->assertJsonPath('data.check_out', Carbon::today()->setTime(18, 0)->format('Y-m-d\TH:i:s'));

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'check_out' => Carbon::today()->setTime(18, 0)->toDateTimeString(),
    ]);
    expect(AttendanceEvent::where('employee_id', $employee->id)->where('type', 'check_out')->count())->toBe(2);
});

it('sums each check-in/check-out session separately, excluding the gap between them', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $scan = fn (string $type, string $time) => $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => $type,
        'event_time' => Carbon::today()->setTimeFromTimeString($time)->toDateTimeString(),
    ]);

    // Morning session (9:00-13:00, 240 min) + afternoon session
    // (14:00-18:00, 240 min) = 480 min worked — not 540, which is what
    // (last check-out minus first check-in) would wrongly give by also
    // counting the hour-long lunch gap as worked time.
    $scan('check_in', '09:00:00')->assertCreated();
    $scan('check_out', '13:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 240);
    $scan('check_in', '14:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 240);
    $scan('check_out', '18:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 480);

    $this->assertDatabaseHas('attendance_records', [
        'employee_id' => $employee->id,
        'worked_minutes' => 480,
    ]);
});

it('does not count a session left open with no matching check-out yet', function () {
    AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'DEV-1']);
    $employee = Employee::factory()->create(['employee_number' => 'EMP-001']);

    $scan = fn (string $type, string $time) => $this->withToken('a-token')->postJson('/api/v1/integrations/attendance/events', [
        'device_id' => 'DEV-1',
        'employee_number' => $employee->employee_number,
        'event_type' => $type,
        'event_time' => Carbon::today()->setTimeFromTimeString($time)->toDateTimeString(),
    ]);

    $scan('check_in', '09:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 0);
    $scan('check_out', '13:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 240);
    // A second check-in with no check-out after it yet — the closed
    // morning session still counts, the still-open one does not.
    $scan('check_in', '14:00:00')->assertCreated()->assertJsonPath('data.worked_minutes', 240);
});
