<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Issues\RecordIssueActivity;
use App\Enums\ActiveStatus;
use App\Enums\IssueStatus;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveIssueRequest;
use App\Http\Requests\StoreIssueRequest;
use App\Http\Resources\Api\V1\IssueCategoryResource;
use App\Http\Resources\Api\V1\IssueResource;
use App\Models\Employee;
use App\Models\Issue;
use App\Models\IssueCategory;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\IssueAssigned;
use App\Notifications\IssueReported;
use App\Notifications\IssueResolved;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * `status=open` for the map. The role scoping is `Issue::visibleTo()`,
     * applied to the base query before Spatie's QueryBuilder wraps it.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Issue::class);

        $query = QueryBuilder::for(Issue::query()->visibleTo(request()->user()))
            ->with(['reporter', 'organization', 'department', 'category', 'executors'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('issue_category_id'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('executor_id', 'executors.id'),
                AllowedFilter::partial('search', 'title'),
            )
            ->defaultSort('-created_at');

        $issues = $query->paginate(request()->integer('per_page', 50));

        return $this->success(IssueResource::collection($issues), meta: $this->paginationMeta($issues));
    }

    /**
     * What the "report an issue" form needs: the categories, and the
     * organizations this user may file against — every active organization
     * (head office and subordinates) for roles with company-wide reach,
     * only their own for everyone else. A department-manager gets
     * themselves as the suggested executor. Computed here so every client
     * applies the same rules instead of each re-deriving them from roles.
     */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('create', Issue::class);

        $user = $request->user();

        $organizations = Organization::query()
            ->where('status', ActiveStatus::Active)
            ->when(
                $user->hasCentralAccess(),
                fn ($query) => $query->orderByRaw('type = ? desc', [OrganizationType::Central->value]),
                fn ($query) => $query->whereKey($user->employee?->organization_id),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $categories = IssueCategory::where('status', ActiveStatus::Active)->orderBy('sort_order')->get();

        return $this->success([
            'organizations' => $organizations,
            'categories' => IssueCategoryResource::collection($categories),
            'default_executor_id' => $user->hasRole('department-manager') ? $user->employee?->id : null,
        ]);
    }

    /**
     * Everyone who can be named an executor in the given organization — all
     * of its current employees (see `Employee::scopeIssueExecutors()`). A
     * user may only look inside their own organization unless they have
     * company-wide reach, so this can't be used to enumerate another
     * organization's staff.
     */
    public function executorCandidates(Request $request): JsonResponse
    {
        Gate::authorize('create', Issue::class);

        $user = $request->user();

        $organizationId = (int) $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ])['organization_id'];

        abort_unless($user->hasCentralAccess() || $user->employee?->organization_id === $organizationId, 403);

        $employees = Employee::issueExecutors()
            ->where('organization_id', $organizationId)
            ->with(['department', 'position'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $this->success($employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'full_name' => $employee->fullName(),
            'department' => $employee->department?->name,
            'position' => $employee->position?->title,
        ])->values());
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
            'organization_id' => $request->targetOrganizationId(),
            // The department that raised it (the reporter's own) — what a
            // department-manager's list is scoped by. The executors may
            // belong to other departments of the organization.
            'department_id' => $user->employee->department_id,
            'issue_category_id' => $data['issue_category_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'object_name' => $data['object_name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status' => IssueStatus::Open,
        ]);

        $issue->executors()->attach(array_unique($data['executor_ids']));
        $issue->load('executors.user');

        $this->recordActivity->handle($issue, $user, 'created', "{$user->name} muammoni qayd etdi.");
        $this->recordActivity->handle(
            $issue,
            $user,
            'assigned',
            'Ijrochilar: '.$issue->executors->map->fullName()->join(', ').'.'
        );

        Notification::send(User::role('technical-policy')->get(), new IssueReported($issue));

        // Executors are told they were assigned — except the reporter, who
        // already knows (a department-manager typically names themselves).
        $assignees = $issue->executors
            ->where('id', '!=', $user->employee->id)
            ->map->user
            ->filter();

        Notification::send($assignees, new IssueAssigned($issue));

        $this->auditLog->log('created', 'issues', $issue, newValues: [
            'title' => $issue->title,
            'issue_category_id' => $issue->issue_category_id,
            'executor_ids' => $issue->executors->pluck('id')->all(),
        ]);

        return $this->success(
            new IssueResource($issue->load(['reporter', 'organization', 'department', 'category', 'executors'])),
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
            'reporter', 'organization', 'department', 'category', 'executors', 'resolvedBy',
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

        // The reporter and everyone working on it hear the outcome.
        $issue->load('executors.user');
        $recipients = $issue->executors->map->user
            ->push($issue->reporter->user)
            ->filter()
            ->unique('id');

        Notification::send($recipients, new IssueResolved($issue));

        $this->auditLog->log('resolved', 'issues', $issue, newValues: ['resolution_note' => $issue->resolution_note]);

        return $this->success(
            new IssueResource($issue->load(['reporter', 'organization', 'department', 'category', 'executors', 'resolvedBy'])),
            'Muammo bartaraf etildi.'
        );
    }
}
