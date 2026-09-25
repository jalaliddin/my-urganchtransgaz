<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Export\ExportRecords;
use App\Actions\Issues\BuildIssueReport;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class IssueReportController extends Controller
{
    /**
     * The leaders' map report. `?export=csv|xlsx|pdf` downloads the
     * per-organization table instead of returning JSON, the same export
     * contract as the KPI and attendance reports.
     */
    public function __invoke(Request $request, BuildIssueReport $report, ExportRecords $export): JsonResponse|Response
    {
        Gate::authorize('viewReport', Issue::class);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'issue_category_id' => ['nullable', 'integer', Rule::exists('issue_categories', 'id')],
            'export' => ['nullable', Rule::in(['csv', 'xlsx', 'pdf'])],
        ]);

        if ($format = $filters['export'] ?? null) {
            return $export->stream($report->byOrganizationQuery($request->user(), $filters), [
                'organization_name' => 'Tashkilot',
                'total' => 'Jami muammolar',
                'open_count' => 'Ochiq',
                'resolved_count' => 'Bartaraf etilgan',
                'stale_open_count' => BuildIssueReport::STALE_AFTER_DAYS.' kundan ortiq ochiq',
                'resolution_rate' => 'Bartaraf etish ulushi (%)',
                'avg_resolution_hours' => 'O\'rtacha bartaraf etish vaqti (soat)',
            ], $format, 'muammolar-hisoboti');
        }

        return $this->success($report->handle($request->user(), $filters));
    }
}
