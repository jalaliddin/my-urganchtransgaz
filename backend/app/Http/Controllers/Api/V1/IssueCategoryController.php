<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueCategoryRequest;
use App\Http\Requests\UpdateIssueCategoryRequest;
use App\Http\Resources\Api\V1\IssueCategoryResource;
use App\Models\IssueCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IssueCategoryController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Every category, active or not, with how many issues use it — the
     * management list. (The report form gets only active ones, from
     * `GET /issues/options`.)
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', IssueCategory::class);

        $categories = QueryBuilder::for(IssueCategory::class)
            ->withCount('issues')
            ->allowedFilters('status', AllowedFilter::partial('search', 'name'))
            ->allowedSorts('name', 'sort_order', 'created_at')
            ->defaultSort('sort_order', 'name')
            ->paginate(request()->integer('per_page', 15));

        return $this->success(IssueCategoryResource::collection($categories), meta: $this->paginationMeta($categories));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = IssueCategory::create([
            ...$data,
            'code' => $this->uniqueCode($data['name']),
            'sort_order' => $data['sort_order'] ?? ((int) IssueCategory::max('sort_order') + 1),
        ])->refresh();

        $this->auditLog->log('created', 'issue_categories', $category, newValues: $category->toArray());

        return $this->success(new IssueCategoryResource($category), 'Kategoriya qo\'shildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(IssueCategory $issueCategory): JsonResponse
    {
        Gate::authorize('view', $issueCategory);

        return $this->success(new IssueCategoryResource($issueCategory->loadCount('issues')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIssueCategoryRequest $request, IssueCategory $issueCategory): JsonResponse
    {
        $oldValues = $issueCategory->toArray();

        $issueCategory->update($request->validated());

        $this->auditLog->log('updated', 'issue_categories', $issueCategory, $oldValues, $issueCategory->toArray());

        return $this->success(new IssueCategoryResource($issueCategory->loadCount('issues')), 'Kategoriya yangilandi.');
    }

    /**
     * A category that issues already use can't be deleted — that would strip
     * the category from historical reports. Deactivate it instead (it then
     * disappears from the report form but stays on the old issues).
     */
    public function destroy(IssueCategory $issueCategory): JsonResponse
    {
        Gate::authorize('delete', $issueCategory);

        if ($issueCategory->issues()->exists()) {
            return $this->error('Bu kategoriya muammolarda ishlatilgan, o\'chirib bo\'lmaydi. Uni faolsiz qiling.', 409);
        }

        $oldValues = $issueCategory->toArray();

        $issueCategory->delete();

        $this->auditLog->log('deleted', 'issue_categories', $issueCategory, oldValues: $oldValues);

        return $this->success(message: 'Kategoriya o\'chirildi.');
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'category';
        $code = $base;
        $suffix = 2;

        while (IssueCategory::where('code', $code)->exists()) {
            $code = "{$base}_{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
