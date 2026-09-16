<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKpiIndicatorRequest;
use App\Http\Requests\UpdateKpiIndicatorRequest;
use App\Http\Resources\Api\V1\KpiIndicatorResource;
use App\Models\KpiIndicator;
use App\Models\KpiTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class KpiIndicatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(KpiTemplate $kpiTemplate): JsonResponse
    {
        Gate::authorize('view', $kpiTemplate);

        return $this->success(KpiIndicatorResource::collection($kpiTemplate->indicators));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKpiIndicatorRequest $request, KpiTemplate $kpiTemplate): JsonResponse
    {
        Gate::authorize('manage', $kpiTemplate);

        $data = $request->validated();

        $indicator = $kpiTemplate->indicators()->create([
            ...$data,
            'status' => $data['status'] ?? ActiveStatus::Active,
        ]);

        return $this->success(new KpiIndicatorResource($indicator), 'Ko\'rsatkich qo\'shildi.', 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKpiIndicatorRequest $request, KpiTemplate $kpiTemplate, KpiIndicator $indicator): JsonResponse
    {
        Gate::authorize('manage', $kpiTemplate);
        abort_unless($indicator->kpi_template_id === $kpiTemplate->id, 404);

        $data = $request->validated();
        $indicator->update($data);

        return $this->success(new KpiIndicatorResource($indicator), 'Ko\'rsatkich yangilandi.');
    }
}
