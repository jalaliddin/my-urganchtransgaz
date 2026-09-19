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
            ->with(['reporter', 'organization', 'department', 'category', 'responsible'])
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('issue_category_id'),
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('responsible_employee_id'),
            )
            ->defaultSort('-created_at');

        // Everyone below leadership sees their own department's issues,
        // plus any issue they were named responsible for.
        if (! $this->isLeadership($user)) {
            $query->where(fn ($scope) => $scope
                ->where('department_id', $user->employee?->department_id ?? 0)
                ->orWhere('responsible_employee_id', $user->employee?->id ?? 0));
        }

        $issues = $query->paginate(request()->integer('per_page', 50));

        return $this->success(IssueResource::collection($issues), meta: $this->paginationMeta($issues));
    }

    /**
     * What the "report an issue" form needs: the categories, the
     * organizations this user may file against (every active subordinate
     * organization for Technical Policy Service and central roles; only their
     * own for a department-manager), and whether they must name a
     * responsible employee (a department-manager is responsible for what
     * they report themselves). Computed here so every client applies the
     * same rules instead of each re-deriving them from roles.
     */
    public function options(Request $request): JsonResponse
    {
        Gate::authorize('create', Issue::class);

        $user = $request->user();
        $mustChooseResponsible = $user->mustChooseIssueResponsible();

        $organizations = Organization::query()
            ->where('status', ActiveStatus::Active)
            ->when(
                $mustChooseResponsible,
                fn ($query) => $query->where('type', OrganizationType::Subordinate),
                fn ($query) => $query->whereKey($user->employee?->organization_id),
            )
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $categories = IssueCategory::where('status', ActiveStatus::Active)->orderBy('sort_order')->get();

        return $this->success([
            'organizations' => $organizations,
            'categories' => IssueCategoryResource::collection($categories),
            'must_choose_responsible' => $mustChooseResponsible,
        ]);
    }

    /**
     * Employees who can be named responsible for an issue in the given
     * organization — see `Employee::scopeIssueHandlers()`. Only asked for
     * by users who must choose one.
     */
    public function responsibleCandidates(Request $request): JsonResponse
    {
        Gate::authorize('create', Issue::class);

        abort_unless($request->user()->mustChooseIssueResponsible(), 403);

        $organizationId = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ])['organization_id'];

        $employees = Employee::issueHandlers()
            ->where('organization_id', $organizationId)
            ->with(['department', 'position'])
            ->orderBy('last_name')
            ->get();

        return $this->success($employees->map(fn (Employee $employee) => [
            'id' => $employee->id,
            'full_name' => $employee->full_name,
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

        // A department-manager is responsible for what they report;
        // everyone else names someone (already validated as eligible).
        $responsible = $user->mustChooseIssueResponsible()
            ? Employee::findOrFail($data['responsible_employee_id'])
            : $user->employee;

        $issue = Issue::create([
            'reporter_employee_id' => $user->employee->id,
            'organization_id' => $request->targetOrganizationId(),
            'department_id' => $responsible->department_id,
            'issue_category_id' => $data['issue_category_id'],
            'responsible_employee_id' => $responsible->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'object_name' => $data['object_name'] ?? null,
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status' => IssueStatus::Open,
        ]);

        $this->recordActivity->handle($issue, $user, 'created', "{$user->name} muammoni qayd etdi.");

        Notification::send(User::role('technical-policy')->get(), new IssueReported($issue));

        if ($responsible->id !== $user->employee->id) {
            $this->recordActivity->handle($issue, $user, 'assigned', "Mas'ul xodim: {$responsible->full_name}.");
            $responsible->user?->notify(new IssueAssigned($issue));
        }

        $this->auditLog->log('created', 'issues', $issue, newValues: [
            'title' => $issue->title,
            'issue_category_id' => $issue->issue_category_id,
            'responsible_employee_id' => $issue->responsible_employee_id,
        ]);

        return $this->success(
            new IssueResource($issue->load(['reporter', 'organization', 'department', 'category', 'responsible'])),
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
            'reporter', 'organization', 'department', 'category', 'responsible', 'resolvedBy',
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
            new IssueResource($issue->load(['reporter', 'organization', 'department', 'category', 'responsible', 'resolvedBy'])),
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
