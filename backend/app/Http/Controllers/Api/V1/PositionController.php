<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePositionRequest;
use App\Http\Requests\UpdatePositionRequest;
use App\Http\Resources\Api\V1\PositionResource;
use App\Models\Position;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PositionController extends Controller
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
        Gate::authorize('viewAny', Position::class);

        $user = request()->user();

        $positions = QueryBuilder::for(Position::class)
            ->with(['organization'])
            ->withCount('employees')
            ->allowedFilters(
                'status',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::partial('search', 'title'),
            )
            ->allowedSorts('title', 'code', 'created_at')
            ->defaultSort('title')
            ->when(
                ! $user->hasCentralAccess(),
                fn ($query) => $query->where('organization_id', $user->employee?->organization_id)
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            PositionResource::collection($positions),
            meta: $this->paginationMeta($positions)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = Position::create($request->validated())->refresh();

        $this->auditLog->log('created', 'positions', $position, newValues: $position->toArray());

        return $this->success(new PositionResource($position), 'Position created.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Position $position): JsonResponse
    {
        Gate::authorize('view', $position);

        $position->load('organization')->loadCount('employees');

        return $this->success(new PositionResource($position));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $oldValues = $position->toArray();

        $position->update($request->validated());

        $this->auditLog->log('updated', 'positions', $position, $oldValues, $position->toArray());

        return $this->success(new PositionResource($position), 'Position updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Position $position): JsonResponse
    {
        Gate::authorize('delete', $position);

        $oldValues = $position->toArray();

        $position->delete();

        $this->auditLog->log('deleted', 'positions', $position, oldValues: $oldValues);

        return $this->success(message: 'Position deleted.');
    }
}
