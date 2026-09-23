<?php

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

it('requires --before', function () {
    $this->artisan('attendance:fix-biometric-timezone')
        ->assertExitCode(1)
        ->expectsOutputToContain('Pass --before');
});

it('previews the correction without saving anything, by default', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'source' => AttendanceSource::Biometric,
        'date' => '2026-09-23',
        'check_in' => '2026-09-23 08:56:00',
        'check_out' => '2026-09-23 10:20:00',
        'status' => AttendanceStatus::Absent,
        'created_at' => '2026-09-23 10:30:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-23 15:45:00"')
        ->assertExitCode(0)
        ->expectsOutputToContain('1 record(s) would be corrected');

    $record->refresh();
    expect($record->check_in->format('H:i:s'))->toBe('08:56:00');
    expect($record->check_out->format('H:i:s'))->toBe('10:20:00');
});

it('adds 5 hours to check-in/check-out and recomputes date and status with --apply', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'source' => AttendanceSource::Biometric,
        'date' => '2026-09-23',
        'check_in' => '2026-09-23 08:56:00',
        'check_out' => '2026-09-23 10:20:00',
        'status' => AttendanceStatus::Absent,
        'created_at' => '2026-09-23 10:30:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-23 15:45:00" --apply')
        ->assertExitCode(0)
        ->expectsOutputToContain('Corrected 1 record(s).');

    $record->refresh();
    expect($record->date->toDateString())->toBe('2026-09-23');
    expect($record->check_in->format('H:i:s'))->toBe('13:56:00');
    expect($record->check_out->format('H:i:s'))->toBe('15:20:00');
});

it('shifts the date forward when the correction crosses midnight', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'source' => AttendanceSource::Biometric,
        'date' => '2026-09-23',
        'check_in' => '2026-09-23 20:15:00',
        'check_out' => null,
        'status' => AttendanceStatus::Present,
        'created_at' => '2026-09-23 20:20:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-24 00:00:00" --apply');

    $record->refresh();
    expect($record->date->toDateString())->toBe('2026-09-24');
    expect($record->check_in->format('Y-m-d H:i:s'))->toBe('2026-09-24 01:15:00');
});

it('never touches a manual/web-sourced record', function () {
    $record = AttendanceRecord::factory()->create([
        'source' => AttendanceSource::Web,
        'check_in' => '2026-09-23 08:56:00',
        'created_at' => '2026-09-23 08:57:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-23 15:45:00" --apply');

    expect($record->fresh()->check_in->format('H:i:s'))->toBe('08:56:00');
});

it('never touches a biometric record created after the given cutoff', function () {
    $record = AttendanceRecord::factory()->create([
        'source' => AttendanceSource::Biometric,
        'check_in' => '2026-09-23 13:56:00',
        'created_at' => '2026-09-23 16:00:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-23 15:45:00" --apply');

    expect($record->fresh()->check_in->format('H:i:s'))->toBe('13:56:00');
});

it('skips a record whose corrected date would collide with an existing one, without crashing', function () {
    $employee = Employee::factory()->create();
    $late = AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'source' => AttendanceSource::Biometric,
        'date' => '2026-09-23',
        'check_in' => '2026-09-23 20:15:00',
        'check_out' => null,
        'created_at' => '2026-09-23 20:20:00',
    ]);
    // Already a (correct) record for the employee on the date this would shift into.
    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id,
        'source' => AttendanceSource::Web,
        'date' => '2026-09-24',
        'check_in' => '2026-09-24 09:00:00',
    ]);

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-24 00:00:00" --apply')
        ->expectsOutputToContain('Skipped (needs manual review)')
        ->assertExitCode(0);

    expect($late->fresh()->check_in->format('H:i:s'))->toBe('20:15:00');
});

it('handles a record with only a check-out (no check-in)', function () {
    Carbon::setTestNow('2026-09-23 20:00:00');
    $record = AttendanceRecord::factory()->create([
        'source' => AttendanceSource::Biometric,
        'date' => '2026-09-23',
        'check_in' => null,
        'check_out' => '2026-09-23 10:20:00',
        'created_at' => '2026-09-23 10:21:00',
    ]);
    Carbon::setTestNow();

    $this->artisan('attendance:fix-biometric-timezone --before="2026-09-23 15:45:00" --apply');

    $record->refresh();
    expect($record->check_in)->toBeNull();
    expect($record->check_out->format('H:i:s'))->toBe('15:20:00');
});
