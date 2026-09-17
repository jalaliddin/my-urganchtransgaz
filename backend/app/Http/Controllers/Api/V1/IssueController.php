<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Issues\RecordIssueActivity;
use App\Enums\IssueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveIssueRequest;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Resources\Api\V1\IssueResource;
use App\Models\Issue;
use App\Models\User;
use App\Notifications\IssueReported;
use App\Notifications\IssueResolved;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IssueController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private RecordIssueActivity $recordActivity,
    ) {
        //
    }

    /**
     * Display a listing of the resource. This one endpoint powers both
     * "my reported issues" (department-manager) and the leadership map
     * (Technical Policy Service / central-admin / super-admin): the
     * latter simply gets every row back, filtered client-side to
     * `status=open` for the map. Never `.when()` on this QueryBuilder —
     * see the Phase 7/9 gotcha (Spatie's QueryBuilder forwards `when()`
     * to the underlying Eloquent builder, silently downgrading the
     * chain) — plain `if` statements instead.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $user = request()->user();

        $query = QueryBuilder::for(Issue::class)
            ->with(['reporter', 'organization', 'department'])
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-created_at');

        if (! $this->isLeadership($user)) {
            $query->where('department_id', $user->employee?->department_id ?? 0);
        }

        $issues = $query->paginate(request()->integer('per_page', 50));

        return $this->success(IssueResource::collection($issues), meta: $this->paginationMeta($issues));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $issue = Issue::create([
            'reporter_employee_id' => $user->employee->id,
            'organization_id' => $data['organization_id'] ?? $user->employee->organization_id,
            'department_id' => $data['department_id'] ?? $user->employee->department_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'object_name' => $data['object_name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status' => IssueStatus::Open,
        ]);

        $this->recordActivity->handle($issue, $user, 'created', "{$user->name} muammoni qayd etdi.");

        Notification::send(User::role('technical-policy')->get(), new IssueReported($issue));

        $this->auditLog->log('created', 'issues', $issue, newValues: ['title' => $issue->title]);

        return $this->success(
            new IssueResource($issue->load(['reporter', 'organization', 'department'])),
            'Muammo qayd etildi.',
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue): JsonResponse
    {
        Gate::authorize('view', $issue);

        $issue->load([
            'reporter', 'organization', 'department', 'resolvedBy',
            'comments.user', 'activities.causer',
        ]);

        return $this->success(new IssueResource($issue));
    }

    /**
     * Mark the issue resolved — the only state transition that counts as
     * "completed," and only Technical Policy Service's own response
     * (`resolution_note`) can make it happen (see `IssuePolicy::resolve`).
     */
    public function resolve(ResolveIssueRequest $request, Issue $issue): JsonResponse
    {
        Gate::authorize('resolve', $issue);

        if ($issue->status === IssueStatus::Resolved) {
            return $this->error('Bu muammo allaqachon bartaraf etilgan.', 409);
        }

        $user = $request->user();

        $issue->update([
            'status' => IssueStatus::Resolved,
            'resolution_note' => $request->string('resolution_note')->toString(),
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->recordActivity->handle($issue, $user, 'resolved', "{$user->name} muammoni bartaraf etdi.");

        $issue->reporter->user?->notify(new IssueResolved($issue));

        $this->auditLog->log('resolved', 'issues', $issue, newValues: ['resolution_note' => $issue->resolution_note]);

        return $this->success(
            new IssueResource($issue->load(['reporter', 'organization', 'department', 'resolvedBy'])),
            'Muammo bartaraf etildi.'
        );
    }

    private function isLeadership(User $user): bool
    {
        return $user->hasRole('technical-policy')
            || $user->hasRole('central-admin')
            || $user->hasRole('super-admin');
    }
}
