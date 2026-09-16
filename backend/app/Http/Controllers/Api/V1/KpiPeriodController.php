<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKpiPeriodRequest;
use App\Http\Requests\UpdateKpiPeriodRequest;
use App\Http\Resources\Api\V1\KpiPeriodResource;
use App\Models\KpiPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class KpiPeriodController extends Controller
{
    /**
     * Display a listing of the resource. Periods are company-wide, so
     * every `kpi.view` holder sees the full list (there's nothing
     * organization-specific to scope here).
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', KpiPeriod::class);

        $periods = QueryBuilder::for(KpiPeriod::class)
            ->allowedFilters('status', AllowedFilter::exact('period_type'))
            ->defaultSort('-start_date')
            ->paginate(request()->integer('per_page', 50));

        return $this->success(KpiPeriodResource::collection($periods), meta: $this->paginationMeta($periods));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKpiPeriodRequest $request): JsonResponse
    {
        Gate::authorize('create', KpiPeriod::class);

        $data = $request->validated();
        $period = KpiPeriod::create([...$data, 'status' => $data['status'] ?? ActiveStatus::Active]);

        return $this->success(new KpiPeriodResource($period), 'Davr yaratildi.', 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKpiPeriodRequest $request, KpiPeriod $kpiPeriod): JsonResponse
    {
        Gate::authorize('update', $kpiPeriod);

        $data = $request->validated();
        $kpiPeriod->update($data);

        return $this->success(new KpiPeriodResource($kpiPeriod), 'Davr yangilandi.');
    }
}
