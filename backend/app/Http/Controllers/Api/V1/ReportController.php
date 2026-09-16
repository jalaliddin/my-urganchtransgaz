<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    /**
     * Company-wide overview stats for the central-admin/HR dashboard —
     * "Employee distribution" and "Task status breakdown" charts (§31).
     * Both aggregated entirely in SQL.
     */
    public function overview(): JsonResponse
    {
        $user = request()->user();
        Gate::authorize('viewAny', Employee::class);

        if (! $user->hasCentralAccess()) {
            return $this->error('Bu bo\'lim faqat markaziy rollarga ochiq.', 403);
        }

        $employeesByOrganization = Employee::query()
            ->join('organizations', 'organizations.id', '=', 'employees.organization_id')
            ->selectRaw('organizations.name as label, COUNT(*) as total')
            ->groupBy('organizations.id', 'organizations.name')
            ->orderByDesc('total')
            ->get();

        $tasksByStatus = Task::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get();

        return $this->success([
            'employees_by_organization' => $employeesByOrganization,
            'tasks_by_status' => $tasksByStatus,
        ]);
    }
}
