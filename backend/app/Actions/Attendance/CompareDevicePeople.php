<?php

namespace App\Actions\Attendance;

use App\Enums\EmployeeStatus;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * The people enrolled on the terminals next to the employees in the app:
 * who is linked (an employee carries their Dahua UserID), who isn't yet,
 * and which employees carry an ID no terminal knows.
 */
class CompareDevicePeople
{
    private const MAX_SUGGESTIONS = 3;

    /**
     * @return array{
     *     summary: array{on_terminals: int, linked: int, unlinked: int, missing_on_terminals: int},
     *     devices: array<int, array<string, mixed>>,
     *     people: array<int, array<string, mixed>>,
     *     missing_on_terminals: array<int, array<string, mixed>>
     * }
     */
    public function handle(): array
    {
        $devices = AttendanceDevice::withCount('users')->orderBy('device_id')->get();
        $deviceIds = $devices->pluck('device_id', 'id');

        // One row per person, though they're usually on several terminals.
        $people = AttendanceDeviceUser::orderBy('user_id')->get()
            ->groupBy('user_id')
            ->map(fn (Collection $rows, string $userId) => [
                'user_id' => (string) $userId,
                'name' => $rows->pluck('name')->filter()->first(),
                'devices' => $rows->map(fn ($row) => $deviceIds[$row->attendance_device_id])->sort()->values()->all(),
            ]);

        $employees = Employee::with('organization:id,name,short_name')
            ->where('status', '!=', EmployeeStatus::Terminated)
            ->get(['id', 'organization_id', 'employee_number', 'dahua_person_id', 'first_name', 'last_name', 'middle_name', 'status']);
        $linked = $employees->whereNotNull('dahua_person_id')->keyBy('dahua_person_id');
        $candidates = $employees->whereNull('dahua_person_id')->groupBy(fn (Employee $employee) => self::normalize($employee->last_name));

        $rows = $people->map(function (array $person) use ($linked, $candidates) {
            $employee = $linked->get($person['user_id']);

            return [
                ...$person,
                'employee' => $employee ? self::summary($employee) : null,
                'suggestions' => $employee ? [] : $this->suggest($person['name'], $candidates),
            ];
        })->sortBy('user_id', SORT_NATURAL)->values(); // 2 before 10

        $missing = $people->isEmpty() ? collect() : $linked->reject(fn (Employee $employee) => $people->has($employee->dahua_person_id));

        return [
            'summary' => [
                'on_terminals' => $rows->count(),
                'linked' => $rows->whereNotNull('employee')->count(),
                'unlinked' => $rows->whereNull('employee')->count(),
                'missing_on_terminals' => $missing->count(),
            ],
            'devices' => $devices->map(fn (AttendanceDevice $device) => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'people_count' => $device->users_count,
                'roster_synced_at' => $device->roster_synced_at,
                'roster_complete' => $device->roster_complete,
            ])->all(),
            'people' => $rows->all(),
            'missing_on_terminals' => $missing->map(fn (Employee $employee) => [
                ...self::summary($employee),
                'dahua_person_id' => $employee->dahua_person_id,
            ])->values()->all(),
        ];
    }

    /**
     * Terminals hold "Surname", "Surname N", "Surname N.O.", "Surname NO"
     * or "Surname Nodir" (all seen on this site's terminals). A
     * same-surname employee whose first name fits comes first; a
     * same-surname one with another first name after.
     *
     * @param  Collection<string, Collection<int, Employee>>  $candidates
     * @return array<int, array<string, mixed>>
     */
    private function suggest(?string $terminalName, Collection $candidates): array
    {
        $parts = preg_split('/\s+/u', trim((string) $terminalName)) ?: [];
        $surname = self::normalize($parts[0] ?? '');
        $given = $parts[1] ?? '';

        if ($surname === '') {
            return [];
        }

        // "N.", "N.O." and "NO" are initials (first name, patronymic): only
        // the first letter says anything about the first name.
        $isInitials = str_contains($given, '.') || (mb_strlen($given) <= 3 && mb_strtoupper($given) === $given);
        $rest = self::normalize($isInitials ? mb_substr($given, 0, 1) : $given);

        return $candidates->get($surname, collect())
            ->map(fn (Employee $employee) => [
                'employee' => $employee,
                'score' => $rest !== '' && str_starts_with(self::normalize($employee->first_name), $rest) ? 2 : 1,
            ])
            ->sortBy([['score', 'desc'], [fn ($a, $b) => strcmp($a['employee']->fullName(), $b['employee']->fullName())]])
            ->take(self::MAX_SUGGESTIONS)
            ->map(fn (array $match) => [...self::summary($match['employee']), 'strong' => $match['score'] === 2])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, full_name: string, employee_number: string, organization: ?string}
     */
    private static function summary(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'full_name' => $employee->fullName(),
            'employee_number' => $employee->employee_number,
            'organization' => $employee->organization?->short_name ?: $employee->organization?->name,
        ];
    }

    /** Case and the many apostrophes of Uzbek Latin (o‘, g', ʻ…) don't count. */
    private static function normalize(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = str_replace(['ʻ', 'ʼ', '’', '‘', '`', '´'], "'", $value);

        return (string) preg_replace('/\s+/u', ' ', $value);
    }
}
