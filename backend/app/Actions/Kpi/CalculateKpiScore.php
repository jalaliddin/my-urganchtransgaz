<?php

namespace App\Actions\Kpi;

use App\Enums\KpiCalculationType;
use App\Models\EmployeeKpi;

class CalculateKpiScore
{
    /**
     * The one place calculation-type dispatch lives, per the spec's "do
     * not hard-code KPI formulas" — extending this later (e.g. a real
     * formula engine for `formula`) only touches this method.
     *
     * `manual` and `formula` both take the entered actual_value as the
     * score directly (a safe, arbitrary-formula evaluator is out of scope
     * for this phase — see the backend README). `percentage`/`quantity`/
     * `rating` all score as the achievement ratio against target,
     * clamped to 0-100 (for `rating`, target is the scale's max, e.g. 5).
     *
     * @return array{score: float, weighted_score: float}
     */
    public function handle(EmployeeKpi $employeeKpi): array
    {
        $type = $employeeKpi->indicator->calculation_type;
        $target = (float) $employeeKpi->target_value;
        $actual = (float) $employeeKpi->actual_value;

        $score = match ($type) {
            KpiCalculationType::Manual, KpiCalculationType::Formula => $actual,
            KpiCalculationType::Percentage, KpiCalculationType::Quantity, KpiCalculationType::Rating => $target > 0
                ? ($actual / $target) * 100
                : 0.0,
        };

        $score = round(max(0.0, min(100.0, $score)), 2);
        $weightedScore = round($score * $employeeKpi->weight / 100, 2);

        return ['score' => $score, 'weighted_score' => $weightedScore];
    }
}
