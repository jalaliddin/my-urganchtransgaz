<?php

namespace App\Actions\Absences;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\User;
use App\Notifications\AbsenceRecorded;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveEmployeeAbsence
{
    public function __construct(
        private ApplyAbsenceToAttendance $attendance,
        private SyncEmployeeAbsenceStatus $status,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $data  validated absence fields
     */
    public function create(Employee $employee, User $user, array $data, ?UploadedFile $file): EmployeeAbsence
    {
        $absence = $this->persist($employee, $file, function () use ($employee, $user, $data) {
            return $employee->absences()->create([
                ...$this->fields($data),
                'created_by' => $user->id,
            ]);
        });

        $this->notify($absence);

        return $absence;
    }

    /**
     * @param  array<string, mixed>  $data  validated absence fields
     */
    public function update(EmployeeAbsence $absence, array $data, ?UploadedFile $file): EmployeeAbsence
    {
        $previousFile = $absence->file_path;

        $absence = $this->persist($absence->employee, $file, function () use ($absence, $data) {
            $this->attendance->revert($absence);
            $absence->update($this->fields($data));

            return $absence;
        });

        if ($file && $previousFile) {
            Storage::disk('local')->delete($previousFile);
        }

        return $absence;
    }

    /**
     * Runs the write under a lock on the employee row, so two HR users
     * saving overlapping absences for the same person at once can't both
     * pass the overlap check.
     *
     * @param  callable(): EmployeeAbsence  $write
     */
    private function persist(Employee $employee, ?UploadedFile $file, callable $write): EmployeeAbsence
    {
        $path = $file?->store("employee-absences/{$employee->id}", 'local');

        try {
            return DB::transaction(function () use ($employee, $file, $path, $write) {
                Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

                $absence = $write();

                $this->ensureNoOverlap($absence);

                if ($path) {
                    $absence->update(['file_path' => $path, 'file_name' => $file->getClientOriginalName()]);
                }

                $this->attendance->apply($absence);
                $this->status->handle($employee->refresh(), today());

                return $absence;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    private function ensureNoOverlap(EmployeeAbsence $absence): void
    {
        $clash = EmployeeAbsence::query()
            ->where('employee_id', $absence->employee_id)
            ->whereKeyNot($absence->id)
            ->notCancelled()
            ->overlapping($absence->start_date, $absence->end_date)
            ->first();

        if ($clash) {
            throw ValidationException::withMessages([
                'start_date' => sprintf(
                    'Bu davr xodimning boshqa yozuvi bilan kesishadi: %s (%s — %s).',
                    $clash->type->label(),
                    $clash->start_date->format('d.m.Y'),
                    $clash->end_date->format('d.m.Y'),
                ),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        return [
            'type' => $data['type'],
            'start_date' => Carbon::parse($data['start_date'])->toDateString(),
            'end_date' => Carbon::parse($data['end_date'])->toDateString(),
            'document_number' => $data['document_number'] ?? null,
            'document_date' => $data['document_date'] ?? null,
            'destination' => $data['destination'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }

    /**
     * The employee learns what was recorded for them; their department
     * manager learns someone on their team will be away.
     */
    private function notify(EmployeeAbsence $absence): void
    {
        $employee = $absence->employee;
        $employee->user?->notify(new AbsenceRecorded($absence, forManager: false));

        $manager = $employee->department?->manager;

        if ($manager && $manager->id !== $employee->id) {
            $manager->user?->notify(new AbsenceRecorded($absence, forManager: true));
        }
    }
}
