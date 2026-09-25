<?php

namespace App\Actions\Issues;

use App\Enums\IssueStatus;
use App\Models\Issue;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The leaders' map report: headline numbers, a per-organization and
 * per-category breakdown, a monthly created-vs-resolved trend, and the map
 * points — every figure aggregated in SQL over exactly the issues
 * `Issue::visibleTo()` lets this user see.
 *
 * @phpstan-type Filters array{date_from?: string|null, date_to?: string|null, organization_id?: int|string|null, issue_category_id?: int|string|null}
 */
class BuildIssueReport
{
    /**
     * An open issue older than this counts as "stale" — the number leaders
     * most need to chase.
     */
    public const STALE_AFTER_DAYS = 7;

    /**
     * The map shows individual pins up to this many; past it the newest are
     * kept and the response says the set was cut.
     */
    public const POINT_LIMIT = 2000;

    /**
     * @param  Filters  $filters
     * @return array<string, mixed>
     */
    public function handle(User $user, array $filters): array
    {
        $points = $this->points($user, $filters);

        return [
            'totals' => $this->totals($user, $filters),
            'by_organization' => $this->byOrganizationQuery($user, $filters)->toBase()->get()->map(fn (object $row) => [
                'organization_id' => (int) $row->organization_id,
                'organization_name' => $row->organization_name,
                'total' => (int) $row->total,
                'open_count' => (int) $row->open_count,
                'resolved_count' => (int) $row->resolved_count,
                'stale_open_count' => (int) $row->stale_open_count,
                'resolution_rate' => (int) $row->resolution_rate,
                'avg_resolution_hours' => $row->avg_resolution_hours !== null ? (float) $row->avg_resolution_hours : null,
                'latitude' => (float) $row->latitude,
                'longitude' => (float) $row->longitude,
            ]),
            'by_category' => $this->byCategory($user, $filters),
            'monthly' => $this->monthly($user, $filters),
            'points' => $points->take(self::POINT_LIMIT)->values(),
            'points_truncated' => $points->count() > self::POINT_LIMIT,
            'stale_after_days' => self::STALE_AFTER_DAYS,
        ];
    }

    /**
     * One row per organization — also what the report's Excel/CSV/PDF
     * export streams, so it stays an Eloquent builder. The map places each
     * organization's bubble at the average position of its issues, since
     * organizations themselves carry no coordinates.
     *
     * @param  Filters  $filters
     */
    public function byOrganizationQuery(User $user, array $filters): Builder
    {
        return $this->baseQuery($user, $filters)
            ->join('organizations', 'organizations.id', '=', 'issues.organization_id')
            ->select('issues.organization_id')
            ->selectRaw('organizations.name as organization_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as open_count', [IssueStatus::Open->value])
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as resolved_count', [IssueStatus::Resolved->value])
            ->selectRaw(
                'SUM(CASE WHEN issues.status = ? AND issues.created_at < ? THEN 1 ELSE 0 END) as stale_open_count',
                [IssueStatus::Open->value, $this->staleBefore()]
            )
            ->selectRaw('ROUND(100 * SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) / COUNT(*)) as resolution_rate', [IssueStatus::Resolved->value])
            ->selectRaw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, issues.created_at, issues.resolved_at)) / 60, 1) as avg_resolution_hours')
            ->selectRaw('AVG(issues.latitude) as latitude')
            ->selectRaw('AVG(issues.longitude) as longitude')
            ->groupBy('issues.organization_id', 'organizations.name')
            ->orderByDesc('total')
            ->orderBy('organizations.name');
    }

    /**
     * @param  Filters  $filters
     * @return array<string, int|float|null>
     */
    private function totals(User $user, array $filters): array
    {
        $row = $this->baseQuery($user, $filters)->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as open_count', [IssueStatus::Open->value])
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as resolved_count', [IssueStatus::Resolved->value])
            ->selectRaw(
                'SUM(CASE WHEN issues.status = ? AND issues.created_at < ? THEN 1 ELSE 0 END) as stale_open_count',
                [IssueStatus::Open->value, $this->staleBefore()]
            )
            ->selectRaw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, issues.created_at, issues.resolved_at)) / 60, 1) as avg_resolution_hours')
            ->first();

        $total = (int) $row->total;
        $resolved = (int) $row->resolved_count;

        return [
            'total' => $total,
            'open_count' => (int) $row->open_count,
            'resolved_count' => $resolved,
            'stale_open_count' => (int) $row->stale_open_count,
            'resolution_rate' => $total > 0 ? (int) round(100 * $resolved / $total) : 0,
            'avg_resolution_hours' => $row->avg_resolution_hours !== null ? (float) $row->avg_resolution_hours : null,
        ];
    }

    /**
     * Issues filed before categories existed have none; they're grouped
     * under a null category rather than dropped.
     *
     * @param  Filters  $filters
     */
    private function byCategory(User $user, array $filters): Collection
    {
        return $this->baseQuery($user, $filters)->toBase()
            ->leftJoin('issue_categories', 'issue_categories.id', '=', 'issues.issue_category_id')
            ->select('issues.issue_category_id')
            ->selectRaw('issue_categories.name as category_name')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as open_count', [IssueStatus::Open->value])
            ->selectRaw('SUM(CASE WHEN issues.status = ? THEN 1 ELSE 0 END) as resolved_count', [IssueStatus::Resolved->value])
            ->groupBy('issues.issue_category_id', 'issue_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row) => [
                'issue_category_id' => $row->issue_category_id !== null ? (int) $row->issue_category_id : null,
                'category_name' => $row->category_name,
                'total' => (int) $row->total,
                'open_count' => (int) $row->open_count,
                'resolved_count' => (int) $row->resolved_count,
            ]);
    }

    /**
     * Issues reported per month next to issues resolved per month — the
     * second series filtered by when it was resolved, not when it was
     * reported, so a month's "resolved" bar is work actually done in it.
     * Months with neither are filled in with zeros.
     *
     * @param  Filters  $filters
     * @return array<int, array{month: string, created: int, resolved: int}>
     */
    private function monthly(User $user, array $filters): array
    {
        $created = $this->baseQuery($user, $filters)->toBase()
            ->selectRaw("DATE_FORMAT(issues.created_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $resolved = $this->baseQuery($user, $filters, 'resolved_at')->toBase()
            ->whereNotNull('issues.resolved_at')
            ->selectRaw("DATE_FORMAT(issues.resolved_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = $created->keys()->merge($resolved->keys())->unique()->sort()->values();

        if ($months->isEmpty()) {
            return [];
        }

        $first = CarbonImmutable::createFromFormat('Y-m', $months->first())->startOfMonth();
        $last = CarbonImmutable::createFromFormat('Y-m', $months->last())->startOfMonth();

        return collect(CarbonPeriod::create($first, '1 month', $last))
            ->map(fn ($month) => $month->format('Y-m'))
            ->map(fn (string $month) => [
                'month' => $month,
                'created' => (int) ($created[$month] ?? 0),
                'resolved' => (int) ($resolved[$month] ?? 0),
            ])
            ->all();
    }

    /**
     * Newest first, one past the limit so the caller can tell whether the
     * set was cut.
     *
     * @param  Filters  $filters
     */
    private function points(User $user, array $filters): Collection
    {
        $now = CarbonImmutable::now();

        return $this->baseQuery($user, $filters)
            ->with(['category:id,name', 'organization:id,name'])
            ->select([
                'issues.id', 'issues.title', 'issues.object_name', 'issues.status',
                'issues.latitude', 'issues.longitude',
                'issues.issue_category_id', 'issues.organization_id',
                'issues.created_at', 'issues.resolved_at',
            ])
            ->latest('issues.created_at')
            ->limit(self::POINT_LIMIT + 1)
            ->get()
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'title' => $issue->title,
                'object_name' => $issue->object_name,
                'status' => $issue->status,
                'latitude' => (float) $issue->latitude,
                'longitude' => (float) $issue->longitude,
                'category' => $issue->category?->name,
                'organization' => $issue->organization?->name,
                'created_at' => $issue->created_at,
                'resolved_at' => $issue->resolved_at,
                'open_days' => $issue->status === IssueStatus::Open ? (int) $issue->created_at->diffInDays($now) : null,
            ]);
    }

    /**
     * The visible issues narrowed by the report filters. The date range
     * applies to `created_at` by default; the monthly "resolved" series
     * applies it to `resolved_at` instead.
     *
     * @param  Filters  $filters
     */
    private function baseQuery(User $user, array $filters, string $dateColumn = 'created_at'): Builder
    {
        return Issue::query()
            ->visibleTo($user)
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $from) => $query->where(
                "issues.{$dateColumn}", '>=', CarbonImmutable::parse($from)->startOfDay()
            ))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $to) => $query->where(
                "issues.{$dateColumn}", '<=', CarbonImmutable::parse($to)->endOfDay()
            ))
            ->when($filters['organization_id'] ?? null, fn (Builder $query, $organizationId) => $query->where('issues.organization_id', $organizationId))
            ->when($filters['issue_category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('issues.issue_category_id', $categoryId));
    }

    private function staleBefore(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::STALE_AFTER_DAYS);
    }
}
