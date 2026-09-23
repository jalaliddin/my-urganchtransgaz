<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tasks\RecordTaskActivity;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskProgressRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Employee;
use App\Models\Task;
use App\Notifications\TaskApproved;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskReopened;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private RecordTaskActivity $recordActivity,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $user = request()->user();

        $tasks = QueryBuilder::for(Task::class)
            ->with(['assignees', 'creator'])
            ->allowedFilters(
                'status',
                'priority',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('department_id'),
                AllowedFilter::callback(
                    'assignee_id',
                    fn ($query, $value) => $query->whereHas('assignees', fn ($assigneeQuery) => $assigneeQuery->where('employees.id', $value))
                ),
            )
            ->defaultSort('-created_at')
            ->when(! $user->hasCentralAccess(), function ($query) use ($user) {
                $query->where(function ($involvedOrScoped) use ($user) {
                    $involvedOrScoped->where('creator_id', $user->id)
                        ->orWhereHas('assignees', fn ($assigneeQuery) => $assigneeQuery->where('employees.id', $user->employee?->id));

                    if ($user->can('tasks.view')) {
                        $involvedOrScoped->orWhere(function ($orgQuery) use ($user) {
                            $orgQuery->where('organization_id', $user->employee?->organization_id);

                            if ($user->hasRole('department-manager')) {
                                $orgQuery->where('department_id', $user->employee?->department_id);
                            }
                        });
                    }
                });
            })
            ->paginate(request()->integer('per_page', 15));

        return $this->success(TaskResource::collection($tasks), meta: $this->paginationMeta($tasks));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // A non-central creator's task always belongs to their own
        // organization when not explicitly given — only a central role
        // (whose scope check always passes) may leave it genuinely null,
        // representing a task that spans multiple organizations.
        $organizationId = $data['organization_id'] ?? (! $user->hasCentralAccess() ? $user->employee?->organization_id : null);

        $task = Task::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'creator_id' => $user->id,
            'organization_id' => $organizationId,
            'department_id' => $data['department_id'] ?? null,
            'priority' => $data['priority'] ?? TaskPriority::Normal,
            'status' => TaskStatus::New,
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        $employees = Employee::with('user')->findMany($data['assignee_ids']);
        $task->assignees()->sync($employees->pluck('id'));

        $this->recordActivity->handle($task, $user, 'created', "{$user->name} vazifani yaratdi.");
        $this->recordActivity->handle($task, $user, 'assigned', 'Biriktirilganlar: '.$employees->pluck('full_name')->implode(', '));

        foreach ($employees as $employee) {
            $employee->user?->notify(new TaskAssigned($task));
        }

        $this->auditLog->log('created', 'tasks', $task, newValues: ['title' => $task->title]);

        return $this->success(new TaskResource($task->load(['creator', 'organization', 'department', 'assignees'])), 'Topshiriq yaratildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $task->load([
            'creator', 'organization', 'department', 'assignees',
            'comments.user', 'activities.causer', 'attachments',
        ]);

        return $this->success(new TaskResource($task));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $task->update(collect($data)->except('assignee_ids')->all());

        if (array_key_exists('assignee_ids', $data)) {
            $employees = Employee::with('user')->findMany($data['assignee_ids']);
            $previousIds = $task->assignees()->pluck('employees.id');
            $task->assignees()->sync($employees->pluck('id'));

            $newlyAssigned = $employees->whereNotIn('id', $previousIds);

            if ($newlyAssigned->isNotEmpty()) {
                $this->recordActivity->handle($task, $user, 'assigned', 'Biriktirilganlar yangilandi: '.$employees->pluck('full_name')->implode(', '));

                foreach ($newlyAssigned as $employee) {
                    $employee->user?->notify(new TaskAssigned($task));
                }
            }
        }

        $this->recordActivity->handle($task, $user, 'updated', "{$user->name} vazifa ma'lumotlarini yangiladi.");
        $this->auditLog->log('updated', 'tasks', $task, newValues: $data);

        return $this->success(new TaskResource($task->load(['creator', 'organization', 'department', 'assignees'])), 'Topshiriq yangilandi.');
    }

    /**
     * An assignee updates their own progress on the task.
     */
    public function updateProgress(UpdateTaskProgressRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('updateProgress', $task);

        if (in_array($task->status, [TaskStatus::Completed, TaskStatus::Cancelled], true)) {
            return $this->error('Bu vazifa allaqachon yakunlangan yoki bekor qilingan.', 409);
        }

        $progress = $request->integer('progress');

        $task->update([
            'progress' => $progress,
            'status' => $task->status === TaskStatus::New ? TaskStatus::InProgress : $task->status,
        ]);

        $this->recordActivity->handle($task, $request->user(), 'progress_changed', "Bajarilishi {$progress}% ga o'zgartirildi.");

        return $this->success(new TaskResource($task->load(['creator', 'organization', 'department', 'assignees'])), 'Bajarilish darajasi yangilandi.');
    }

    /**
     * An assignee marks their assigned work complete — this submits the
     * task for a manager's approval (status: waiting), it does not close
     * the task outright.
     */
    public function complete(CompleteTaskRequest $request, Task $task): JsonResponse
    {
        Gate::authorize('updateProgress', $task);

        if (! in_array($task->status, [TaskStatus::New, TaskStatus::InProgress, TaskStatus::Overdue], true)) {
            return $this->error('Bu vazifa allaqachon yuborilgan, yakunlangan yoki bekor qilingan.', 409);
        }

        $task->update([
            'status' => TaskStatus::Waiting,
            'progress' => 100,
            'result' => $request->string('result')->toString() ?: $task->result,
        ]);

        $this->recordActivity->handle($task, $request->user(), 'completed', "{$request->user()->name} vazifani bajarilgan deb belgiladi.");

        return $this->success(new TaskResource($task->load(['creator', 'organization', 'department', 'assignees'])), 'Vazifa tasdiqlash uchun yuborildi.');
    }

    /**
     * A manager approves the assignee's completion.
     */
    public function approve(Task $task): JsonResponse
    {
        Gate::authorize('review', $task);

        if ($task->status !== TaskStatus::Waiting) {
            return $this->error('Bu vazifa tasdiqlash kutilayotgan holatda emas.', 409);
        }

        $user = request()->user();

        $task->update(['status' => TaskStatus::Completed, 'completed_at' => now()]);
        $this->recordActivity->handle($task, $user, 'approved', "{$user->name} bajarilishni tasdiqladi.");
        $this->auditLog->log('approved', 'tasks', $task);

        $task->load(['creator', 'organization', 'department', 'assignees']);
        $task->assignees->each(fn ($employee) => $employee->user?->notify(new TaskApproved($task)));

        return $this->success(new TaskResource($task), 'Vazifa tasdiqlandi.');
    }

    /**
     * A manager reopens a submitted or completed task.
     */
    public function reopen(Task $task): JsonResponse
    {
        Gate::authorize('review', $task);

        if (! in_array($task->status, [TaskStatus::Waiting, TaskStatus::Completed], true)) {
            return $this->error('Bu vazifani qayta ochib bo\'lmaydi.', 409);
        }

        $user = request()->user();

        $task->update(['status' => TaskStatus::InProgress, 'completed_at' => null]);
        $this->recordActivity->handle($task, $user, 'reopened', "{$user->name} vazifani qayta ochdi.");
        $this->auditLog->log('reopened', 'tasks', $task);

        $task->load(['creator', 'organization', 'department', 'assignees']);
        $task->assignees->each(fn ($employee) => $employee->user?->notify(new TaskReopened($task)));

        return $this->success(new TaskResource($task), 'Vazifa qayta ochildi.');
    }

    /**
     * Cancel the task — tasks have no hard-delete endpoint; "cancelled" is
     * a first-class status instead (no `tasks.delete` permission exists).
     */
    public function cancel(Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        if (in_array($task->status, [TaskStatus::Completed, TaskStatus::Cancelled], true)) {
            return $this->error('Bu vazifa allaqachon yakunlangan yoki bekor qilingan.', 409);
        }

        $user = request()->user();

        $task->update(['status' => TaskStatus::Cancelled]);
        $this->recordActivity->handle($task, $user, 'cancelled', "{$user->name} vazifani bekor qildi.");
        $this->auditLog->log('cancelled', 'tasks', $task);

        return $this->success(new TaskResource($task->load(['creator', 'organization', 'department', 'assignees'])), 'Vazifa bekor qilindi.');
    }
}
