<?php

namespace App\Console\Commands;

use App\Actions\Attendance\CalculateAttendanceStatus;
use App\Models\AttendanceRecord;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * One-off correction for a since-fixed dahua-bridge bug: every
 * check-in/check-out it sent before the fix was five hours early (it
 * sent UTC-labelled time; this app stores naive Asia/Tashkent wall-clock
 * text with no timezone marker at all). Only ever touches
 * `source = 'biometric'` records created before the given cutoff — never
 * a manual/web/mobile-sourced one, and never anything from after the
 * bridge was restarted with the fix.
 */
#[Signature('attendance:fix-biometric-timezone {--before=} {--apply}')]
#[Description('Add 5 hours to biometric-sourced check-in/check-out times recorded by the dahua-bridge before its timezone fix. Prints a preview unless --apply is given.')]
class FixBiometricAttendanceTimezone extends Command
{
    public function handle(CalculateAttendanceStatus $calculateStatus): int
    {
        $beforeOption = $this->option('before');

        if (! $beforeOption) {
            $this->error('Pass --before="<date and time the bridge was restarted>", e.g. --before="2026-09-23 15:45:00".');

            return self::FAILURE;
        }

        $before = Carbon::parse($beforeOption);
        $apply = (bool) $this->option('apply');

        $records = AttendanceRecord::where('source', 'biometric')
            ->where('created_at', '<', $before)
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            $this->info('No biometric-sourced records found before that time — nothing to do.');

            return self::SUCCESS;
        }

        $rows = [];
        $skipped = [];

        foreach ($records as $record) {
            $newCheckIn = $record->check_in?->copy()->addHours(5);
            $newCheckOut = $record->check_out?->copy()->addHours(5);
            $newDate = ($newCheckIn ?? $newCheckOut)->toDateString();

            if ($newDate !== $record->date->toDateString()) {
                $conflict = AttendanceRecord::where('employee_id', $record->employee_id)
                    ->where('date', $newDate)
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($conflict) {
                    $skipped[] = [$record->id, $record->employee_id, $record->date->toDateString(), $newDate, 'a record already exists for that employee on the corrected date — needs manual review'];

                    continue;
                }
            }

            $newStatus = $calculateStatus->handle($newCheckIn, $newCheckOut);

            $rows[] = [
                $record->id,
                $record->employee_id,
                $record->date->toDateString().' -> '.$newDate,
                ($record->check_in?->format('H:i:s') ?? '—').' -> '.($newCheckIn?->format('H:i:s') ?? '—'),
                ($record->check_out?->format('H:i:s') ?? '—').' -> '.($newCheckOut?->format('H:i:s') ?? '—'),
                $record->status->value.' -> '.$newStatus->value,
            ];

            if ($apply) {
                $record->forceFill([
                    'check_in' => $newCheckIn,
                    'check_out' => $newCheckOut,
                    'date' => $newDate,
                    'status' => $newStatus,
                ])->save();
            }
        }

        $this->table(['id', 'employee_id', 'date', 'check_in', 'check_out', 'status'], $rows);

        if ($skipped !== []) {
            $this->warn('Skipped (needs manual review):');
            $this->table(['id', 'employee_id', 'old date', 'new date', 'reason'], $skipped);
        }

        $this->info($apply
            ? \sprintf('Corrected %d record(s).', \count($rows))
            : \sprintf('%d record(s) would be corrected — re-run with --apply to actually save.', \count($rows)));

        return self::SUCCESS;
    }
}
