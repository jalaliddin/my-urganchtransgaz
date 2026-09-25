<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskCategoryRequest;
use App\Http\Requests\UpdateTaskCategoryRequest;
use App\Http\Resources\Api\V1\TaskCategoryResource;
use App\Models\TaskCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TaskCategoryController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Every category, active or not, with how many tasks use it — the
     * management list. (The task form gets only active ones, from
     * `GET /tasks/options`.)
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', TaskCategory::class);

        $categories = QueryBuilder::for(TaskCategory::class)
            ->withCount('tasks')
            ->allowedFilters('status', AllowedFilter::partial('search', 'name'))
            ->allowedSorts('name', 'sort_order', 'created_at')
            ->defaultSort('sort_order', 'name')
            ->paginate(request()->integer('per_page', 15));

        return $this->success(TaskCategoryResource::collection($categories), meta: $this->paginationMeta($categories));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTaskCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = TaskCategory::create([
            ...$data,
            'code' => $this->uniqueCode($data['name']),
            'color' => $data['color'] ?? '#1E3A5F',
            'sort_order' => $data['sort_order'] ?? ((int) TaskCategory::max('sort_order') + 1),
        ])->refresh();

        $this->auditLog->log('created', 'task_categories', $category, newValues: $category->toArray());

        return $this->success(new TaskCategoryResource($category), 'Kategoriya qo\'shildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TaskCategory $taskCategory): JsonResponse
    {
        Gate::authorize('view', $taskCategory);

        return $this->success(new TaskCategoryResource($taskCategory->loadCount('tasks')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTaskCategoryRequest $request, TaskCategory $taskCategory): JsonResponse
    {
        $oldValues = $taskCategory->toArray();

        $taskCategory->update($request->validated());

        $this->auditLog->log('updated', 'task_categories', $taskCategory, $oldValues, $taskCategory->toArray());

        return $this->success(new TaskCategoryResource($taskCategory->loadCount('tasks')), 'Kategoriya yangilandi.');
    }

    /**
     * A category that tasks already use can't be deleted — that would strip
     * it from historical tasks and their reports. Deactivate it instead (it
     * then disappears from the task form but stays on the old tasks).
     */
    public function destroy(TaskCategory $taskCategory): JsonResponse
    {
        Gate::authorize('delete', $taskCategory);

        if ($taskCategory->tasks()->exists()) {
            return $this->error('Bu kategoriya topshiriqlarda ishlatilgan, o\'chirib bo\'lmaydi. Uni faolsiz qiling.', 409);
        }

        $oldValues = $taskCategory->toArray();

        $taskCategory->delete();

        $this->auditLog->log('deleted', 'task_categories', $taskCategory, oldValues: $oldValues);

        return $this->success(message: 'Kategoriya o\'chirildi.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'category';
        $code = $base;
        $suffix = 2;

        while (TaskCategory::where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
