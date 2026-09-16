<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExamResultController extends Controller
{
    /**
     * Who passed, who failed, who hasn't taken it — the roster and
     * summary counts, both scoped to the exam's own organization_id/
     * department_id audience. Summary counts are separate COUNT queries,
     * not derived from the (paginated) roster page, per §53.
     */
    public function index(Exam $exam): JsonResponse
    {
        Gate::authorize('evaluate', $exam);

        $eligible = fn () => Employee::query()
            ->when($exam->organization_id, fn ($query) => $query->where('organization_id', $exam->organization_id))
            ->when($exam->department_id, fn ($query) => $query->where('department_id', $exam->department_id));

        $totalEligible = $eligible()->count();
        $passedCount = $eligible()->whereHas(
            'examAttempts',
            fn ($query) => $query->where('exam_id', $exam->id)->where('passed', true)
        )->count();
        $notTakenCount = $eligible()->whereDoesntHave(
            'examAttempts',
            fn ($query) => $query->where('exam_id', $exam->id)
        )->count();
        $failedCount = $totalEligible - $passedCount - $notTakenCount;

        $roster = $eligible()
            ->with(['examAttempts' => fn ($query) => $query->where('exam_id', $exam->id)->latest()])
            ->orderBy('last_name')
            ->paginate(request()->integer('per_page', 15));

        $rows = $roster->getCollection()->map(function (Employee $employee) {
            $attempts = $employee->examAttempts;
            $best = $attempts->sortByDesc('percentage')->first();
            $passed = $attempts->contains('passed', true);

            return [
                'employee_id' => $employee->id,
                'full_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'attempts_used' => $attempts->count(),
                'best_percentage' => $best?->percentage === null ? null : (float) $best->percentage,
                'passed' => $passed,
                'status' => $best === null ? 'not_taken' : ($passed ? 'passed' : 'failed'),
            ];
        });

        return $this->success($rows, meta: [
            ...$this->paginationMeta($roster),
            'stats' => [
                'total_eligible' => $totalEligible,
                'passed' => $passedCount,
                'failed' => $failedCount,
                'not_taken' => $notTakenCount,
            ],
        ]);
    }
}
