<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class OrganizationController extends Controller
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
        Gate::authorize('viewAny', Organization::class);

        $organizations = QueryBuilder::for(Organization::class)
            ->withCount(['departments', 'employees'])
            ->allowedFilters(
                'type', 'status',
                AllowedFilter::exact('parent_id'),
                AllowedFilter::partial('search', 'name'),
            )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('name')
            ->when(
                ! request()->user()->hasCentralAccess(),
                fn ($query) => $query->where('id', request()->user()->employee?->organization_id)
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            OrganizationResource::collection($organizations),
            meta: $this->paginationMeta($organizations)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = Organization::create($request->validated())->refresh();

        $this->auditLog->log('created', 'organizations', $organization, newValues: $organization->toArray());

        return $this->success(new OrganizationResource($organization), 'Organization created.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization): JsonResponse
    {
        Gate::authorize('view', $organization);

        $organization->loadCount(['departments', 'employees']);

        return $this->success(new OrganizationResource($organization));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        $oldValues = $organization->toArray();

        $organization->update($request->validated());

        $this->auditLog->log('updated', 'organizations', $organization, $oldValues, $organization->toArray());

        return $this->success(new OrganizationResource($organization), 'Organization updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);

        $oldValues = $organization->toArray();

        $organization->delete();

        $this->auditLog->log('deleted', 'organizations', $organization, oldValues: $oldValues);

        return $this->success(message: 'Organization deleted.');
    }
}
