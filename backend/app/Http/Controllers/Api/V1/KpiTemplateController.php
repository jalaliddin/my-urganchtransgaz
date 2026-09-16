<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKpiTemplateRequest;
use App\Http\Requests\UpdateKpiTemplateRequest;
use App\Http\Resources\Api\V1\KpiTemplateResource;
use App\Models\KpiTemplate;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class KpiTemplateController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', KpiTemplate::class);

        $user = request()->user();

        $templates = QueryBuilder::for(KpiTemplate::class)
            ->with(['organization', 'department'])
            ->withCount('indicators')
            ->allowedFilters('status', AllowedFilter::exact('organization_id'))
            ->defaultSort('-created_at')
            ->when(! $user->hasCentralAccess(), function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->whereNull('organization_id')
                        ->orWhere(function ($orgScope) use ($user) {
                            $orgScope->where('organization_id', $user->employee?->organization_id)
                                ->when(
                                    $user->hasRole('department-manager'),
                                    fn ($deptScope) => $deptScope->where(function ($d) use ($user) {
                                        $d->whereNull('department_id')->orWhere('department_id', $user->employee?->department_id);
                                    })
                                );
                        });
                });
            })
            ->paginate(request()->integer('per_page', 15));

        return $this->success(KpiTemplateResource::collection($templates), meta: $this->paginationMeta($templates));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKpiTemplateRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // A non-central creator's template always belongs to their own
        // organization when not explicitly given — only a central role
        // may leave it genuinely null (company-wide), the same rule
        // Task/Exam creation already follows.
        $organizationId = $data['organization_id'] ?? (! $user->hasCentralAccess() ? $user->employee?->organization_id : null);

        $template = KpiTemplate::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'organization_id' => $organizationId,
            'department_id' => $data['department_id'] ?? null,
            'status' => $data['status'] ?? ActiveStatus::Active,
            'created_by' => $user->id,
        ]);

        $this->auditLog->log('created', 'kpi_templates', $template, newValues: ['name' => $template->name]);

        return $this->success(new KpiTemplateResource($template->load(['organization', 'department'])), 'Shablon yaratildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(KpiTemplate $kpiTemplate): JsonResponse
    {
        Gate::authorize('view', $kpiTemplate);

        $kpiTemplate->load(['organization', 'department', 'indicators']);

        return $this->success(new KpiTemplateResource($kpiTemplate));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKpiTemplateRequest $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        Gate::authorize('manage', $kpiTemplate);

        $data = $request->validated();
        $kpiTemplate->update($data);

        $this->auditLog->log('updated', 'kpi_templates', $kpiTemplate, newValues: $data);

        return $this->success(new KpiTemplateResource($kpiTemplate->load(['organization', 'department'])), 'Shablon yangilandi.');
    }
}
