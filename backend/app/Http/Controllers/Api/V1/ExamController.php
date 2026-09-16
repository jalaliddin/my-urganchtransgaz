<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamRequest;
use App\Http\Requests\UpdateExamRequest;
use App\Http\Resources\Api\V1\ExamResource;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExamController extends Controller
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
        Gate::authorize('viewAny', Exam::class);

        $user = request()->user();
        $employee = $user->employee;

        $exams = QueryBuilder::for(Exam::class)
            ->with(['organization', 'department'])
            ->withCount('questions')
            ->when(
                $employee,
                fn ($query) => $query->with(['attempts' => fn ($attemptsQuery) => $attemptsQuery->where('employee_id', $employee->id)])
            )
            ->allowedFilters(
                'status',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('department_id'),
            )
            ->defaultSort('-created_at')
            ->when(! $this->isAdmin($user), fn ($query) => $this->scopeToEligible($query, $employee))
            ->paginate(request()->integer('per_page', 15));

        return $this->success(ExamResource::collection($exams), meta: $this->paginationMeta($exams));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        Gate::authorize('manage', Exam::class);

        $data = $request->validated();

        $exam = Exam::create([
            ...$data,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? ExamStatus::Draft,
            'attempts_allowed' => $data['attempts_allowed'] ?? 1,
        ]);

        $this->auditLog->log('created', 'exams', $exam, newValues: ['title' => $exam->title]);

        return $this->success(new ExamResource($exam->load(['organization', 'department'])), 'Imtihon yaratildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Exam $exam): JsonResponse
    {
        Gate::authorize('view', $exam);

        $user = request()->user();
        $exam->load(['organization', 'department'])->loadCount('questions');

        if ($this->isAdmin($user)) {
            $exam->load('questions.answers');
        }

        if ($user->employee) {
            $exam->load(['attempts' => fn ($query) => $query->where('employee_id', $user->employee->id)]);
        }

        return $this->success(new ExamResource($exam));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        Gate::authorize('manage', $exam);

        $data = $request->validated();
        $exam->update($data);

        $this->auditLog->log('updated', 'exams', $exam, newValues: $data);

        return $this->success(new ExamResource($exam->load(['organization', 'department'])), 'Imtihon yangilandi.');
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('safety-manager') || $user->hasCentralAccess();
    }

    /**
     * A plain employee sees an exam if it currently applies to them (org/
     * department scope, and only while it's active) or if they already
     * have an attempt on it, regardless of the exam's current status —
     * their own past results shouldn't disappear once an exam closes.
     */
    private function scopeToEligible(Builder $query, ?Employee $employee): Builder
    {
        return $query->where(function ($outer) use ($employee) {
            $outer->where(function ($applies) use ($employee) {
                $applies->where('status', ExamStatus::Active->value)
                    ->where(function ($scope) use ($employee) {
                        $scope->whereNull('organization_id')
                            ->orWhere(function ($orgScope) use ($employee) {
                                $orgScope->where('organization_id', $employee?->organization_id)
                                    ->where(function ($deptScope) use ($employee) {
                                        $deptScope->whereNull('department_id')
                                            ->orWhere('department_id', $employee?->department_id);
                                    });
                            });
                    });
            });

            if ($employee) {
                $outer->orWhereHas('attempts', fn ($attemptsQuery) => $attemptsQuery->where('employee_id', $employee->id));
            }
        });
    }
}
