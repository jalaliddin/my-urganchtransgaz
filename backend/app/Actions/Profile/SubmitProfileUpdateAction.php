<?php

namespace App\Actions\Profile;

use App\Enums\ChangeRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SubmitProfileUpdateAction
{
    /**
     * Fields an employee may change immediately, no approval needed.
     *
     * @var string[]
     */
    private const DIRECT_FIELDS = ['phone', 'email', 'address'];

    /**
     * Official-record fields: changing any of these creates one pending
     * EmployeeChangeRequest instead of touching the employee row.
     *
     * @var string[]
     */
    private const APPROVAL_FIELDS = [
        'first_name', 'last_name', 'middle_name', 'birth_date', 'birth_place', 'gender',
        'passport_number', 'pinfl', 'organization_id', 'department_id', 'position_id',
        'employee_number', 'hire_date',
    ];

    /**
     * @var string[]
     */
    private const CONTACT_FIELDS = [
        'type', 'full_name', 'relationship', 'phone', 'address', 'bank_name', 'bank_account_number',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array{employee: Employee, change_request: ?EmployeeChangeRequest}
     */
    public function handle(Employee $employee, User $actor, array $data): array
    {
        return DB::transaction(function () use ($employee, $actor, $data) {
            $direct = Arr::only($data, self::DIRECT_FIELDS);

            if ($direct !== []) {
                $employee->update($direct);
            }

            $changeRequest = $this->createChangeRequestIfNeeded($employee, $actor, $data);

            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($employee, $data['contacts']);
            }

            return [
                'employee' => $employee->fresh(),
                'change_request' => $changeRequest,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createChangeRequestIfNeeded(Employee $employee, User $actor, array $data): ?EmployeeChangeRequest
    {
        $pendingChanges = [];

        foreach (self::APPROVAL_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($this->normalize($employee->{$field}) !== $this->normalize($data[$field])) {
                $pendingChanges[$field] = $data[$field];
            }
        }

        if ($pendingChanges === []) {
            return null;
        }

        return EmployeeChangeRequest::create([
            'employee_id' => $employee->id,
            'requested_by' => $actor->id,
            'changes' => $pendingChanges,
            'status' => ChangeRequestStatus::Pending,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function syncContacts(Employee $employee, array $contacts): void
    {
        $keptIds = [];

        foreach ($contacts as $contactData) {
            $id = $contactData['id'] ?? null;
            $attributes = Arr::only($contactData, self::CONTACT_FIELDS);

            $contact = $id
                ? $employee->contacts()->whereKey($id)->first()
                : null;

            if ($contact) {
                $contact->update($attributes);
            } else {
                $contact = $employee->contacts()->create($attributes);
            }

            $keptIds[] = $contact->id;
        }

        $employee->contacts()->whereNotIn('id', $keptIds)->delete();
    }

    private function normalize(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }
}
