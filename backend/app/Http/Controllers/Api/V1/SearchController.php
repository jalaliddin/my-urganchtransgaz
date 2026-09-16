<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Results per type — a global search is a "jump to it" shortcut, not
     * a full listing; each module's own page is where a full, paginated,
     * filterable list already lives.
     */
    private const LIMIT = 5;

    /**
     * Global search across Employees/Organizations/Departments/Tasks/
     * Announcements/Documents. Each type reuses that module's own
     * existing scope rule (same Gate/withinScope/hasCentralAccess checks
     * as that module's index()) rather than a parallel authorization
     * path — "Employee search results must respect permissions" holds
     * by construction.
     */
    public function index(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < 2) {
            return $this->success([]);
        }

        $user = $request->user();

        $groups = array_filter([
            $this->employees($user, $term),
            $this->organizations($user, $term),
            $this->departments($user, $term),
            $this->tasks($user, $term),
            $this->announcements($user, $term),
            $this->documents($user, $term),
        ], fn (array $group) => $group['results'] !== []);

        return $this->success(array_values($groups));
    }

    private function employees(User $user, string $term): array
    {
        if (! $user->can('employees.view')) {
            return $this->group('employees', []);
        }

        $query = Employee::query()
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('employee_number', 'like', "%{$term}%");
            })
            ->when(! $user->hasCentralAccess(), fn ($q) => $q->where('organization_id', $user->employee?->organization_id))
            ->when($user->hasRole('department-manager'), fn ($q) => $q->where('department_id', $user->employee?->department_id));

        return $this->group('employees', $query->limit(self::LIMIT)->get()->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'title' => $employee->fullName(),
            'subtitle' => $employee->employee_number,
        ])->all());
    }

    private function organizations(User $user, string $term): array
    {
        $query = Organization::query()
            ->where('name', 'like', "%{$term}%")
            ->when(! $user->hasCentralAccess(), fn ($q) => $q->where('id', $user->employee?->organization_id));

        return $this->group('organizations', $query->limit(self::LIMIT)->get()->map(fn (Organization $organization) => [
            'id' => $organization->id,
            'title' => $organization->name,
            'subtitle' => $organization->code,
        ])->all());
    }

    private function departments(User $user, string $term): array
    {
        $query = Department::query()
            ->with('organization')
            ->where('name', 'like', "%{$term}%")
            ->when(! $user->hasCentralAccess(), fn ($q) => $q->where('organization_id', $user->employee?->organization_id));

        return $this->group('departments', $query->limit(self::LIMIT)->get()->map(fn (Department $department) => [
            'id' => $department->id,
            'title' => $department->name,
            'subtitle' => $department->organization?->name,
        ])->all());
    }

    private function tasks(User $user, string $term): array
    {
        $query = Task::query()
            ->where('title', 'like', "%{$term}%")
            ->when(! $user->hasCentralAccess(), function ($query) use ($user) {
                $query->where(function ($involvedOrScoped) use ($user) {
                    $involvedOrScoped->where('creator_id', $user->id)
                        ->orWhereHas('assignees', fn ($q) => $q->where('employees.id', $user->employee?->id));

                    if ($user->can('tasks.view')) {
                        $involvedOrScoped->orWhere(function ($orgQuery) use ($user) {
                            $orgQuery->where('organization_id', $user->employee?->organization_id);

                            if ($user->hasRole('department-manager')) {
                                $orgQuery->where('department_id', $user->employee?->department_id);
                            }
                        });
                    }
                });
            });

        return $this->group('tasks', $query->limit(self::LIMIT)->get()->map(fn (Task $task) => [
            'id' => $task->id,
            'title' => $task->title,
            'subtitle' => $task->status->value,
        ])->all());
    }

    private function announcements(User $user, string $term): array
    {
        $employee = $user->employee;
        $isReviewer = $user->can('announcements.publish');
        $isAuthor = $user->can('announcements.create');

        $query = Announcement::query()->where('title', 'like', "%{$term}%");

        if ($isAuthor || $isReviewer) {
            if (! $isReviewer) {
                $query->where('author_id', $user->id);
            }
        } elseif ($employee) {
            $query->where('status', 'published')->audienceFor($employee);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $this->group('announcements', $query->limit(self::LIMIT)->get()->map(fn (Announcement $announcement) => [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'subtitle' => $announcement->status->value,
        ])->all());
    }

    private function documents(User $user, string $term): array
    {
        $query = EmployeeDocument::query()
            ->where('title', 'like', "%{$term}%")
            ->when(
                ! $user->can('documents.view'),
                fn ($q) => $q->where('employee_id', $user->employee?->id)
            )
            ->when(
                $user->can('documents.view') && ! $user->hasCentralAccess(),
                fn ($q) => $q->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $employeeQuery->where('department_id', $user->employee?->department_id);
                    }
                })
            );

        return $this->group('documents', $query->limit(self::LIMIT)->get()->map(fn (EmployeeDocument $document) => [
            'id' => $document->id,
            'title' => $document->title,
            'subtitle' => $document->status->value,
        ])->all());
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function group(string $type, array $results): array
    {
        return ['type' => $type, 'results' => $results];
    }
}
