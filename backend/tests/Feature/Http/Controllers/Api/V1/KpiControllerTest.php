<?php

use App\Actions\Kpi\CalculateKpiScore;
use App\Enums\KpiCalculationType;
use App\Models\Department;
use App\Models\EmployeeKpi;
use App\Models\KpiIndicator;
use App\Models\KpiPeriod;
use App\Models\KpiTemplate;
use App\Models\Organization;
use App\Notifications\KpiPublished;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing kpi results without authentication', function () {
    $this->getJson('/api/v1/kpi')->assertStatus(401);
});

it('lets organization-admin create a template, indicator, and period', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $template = $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi-templates', [
        'name' => 'Sotuv jamoasi 2026',
    ])->assertCreated()->json('data');

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/kpi-templates/{$template['id']}/indicators", [
        'name' => 'Sotuv rejasi',
        'weight' => 100,
        'target' => 100,
        'calculation_type' => 'percentage',
        'period' => 'monthly',
    ])->assertCreated();

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi/periods', [
        'name' => '2026-09',
        'period_type' => 'monthly',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ])->assertCreated();
});

it('forbids a plain employee from creating a template', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/kpi-templates', ['name' => 'Not allowed'])
        ->assertStatus(403);
});

it('forbids a manager from creating a template', function () {
    $manager = userWithRole('manager', Organization::factory()->create());

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/kpi-templates', ['name' => 'Not allowed'])
        ->assertStatus(403);
});

it('forbids an organization-admin from scoring an employee outside their organization', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $outsider = userWithRole('employee', Organization::factory()->create());
    $indicator = KpiIndicator::factory()->create();
    $period = KpiPeriod::factory()->create();

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi', [
        'employee_id' => $outsider->employee->id,
        'kpi_period_id' => $period->id,
        'kpi_indicator_id' => $indicator->id,
        'actual_value' => 90,
    ])->assertStatus(403);
});

it('lets organization-admin enter and approve a result, publishing it to the employee', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $indicator = KpiIndicator::factory()->create(['target' => 100, 'weight' => 80, 'calculation_type' => 'percentage']);
    $period = KpiPeriod::factory()->create();

    $store = $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi', [
        'employee_id' => $employeeUser->employee->id,
        'kpi_period_id' => $period->id,
        'kpi_indicator_id' => $indicator->id,
        'actual_value' => 90,
    ]);
    $store->assertCreated();
    $employeeKpiId = $store->json('data.id');

    // Draft: invisible to the employee themselves.
    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/kpi/my')
        ->assertOk()->assertJsonPath('meta.total', 0);

    $approve = $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/kpi/{$employeeKpiId}/approve");
    $approve->assertOk()
        ->assertJsonPath('data.score', 90)
        ->assertJsonPath('data.weighted_score', 72);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employeeUser->id,
        'type' => KpiPublished::class,
    ]);

    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/kpi/my')
        ->assertOk()->assertJsonPath('meta.total', 1);
});

it('will not approve a result that has no actual value yet', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $employeeKpi = EmployeeKpi::factory()->create(['employee_id' => $employeeUser->employee->id]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/kpi/{$employeeKpi->id}/approve")
        ->assertStatus(409);
});

it('will not approve a result twice', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $employeeKpi = EmployeeKpi::factory()->approved()->create(['employee_id' => $employeeUser->employee->id]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/kpi/{$employeeKpi->id}/approve")
        ->assertStatus(409);
});

it('does not let a plain employee see a coworkers published kpi', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $coworkerUser = userWithRole('employee', $organization);
    $employeeKpi = EmployeeKpi::factory()->approved()->create(['employee_id' => $coworkerUser->employee->id]);

    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/kpi?filter[employee_id]='.$coworkerUser->employee->id)
        ->assertOk()->assertJsonPath('meta.total', 0);

    // The index is silently self-scoped for a bare employee — confirm
    // they still can't fetch the coworker's total via the raw list.
    $ids = collect($this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/kpi')->json('data'))->pluck('id');
    expect($ids)->not->toContain($employeeKpi->id);
});

it('lets a department-manager see their permitted employees published kpi', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $deptManager = userWithRole('department-manager', $organization, $department);
    $teamMember = userWithRole('employee', $organization, $department);
    EmployeeKpi::factory()->approved()->create(['employee_id' => $teamMember->employee->id]);

    $response = $this->actingAs($deptManager, 'sanctum')->getJson('/api/v1/kpi');

    $response->assertOk()->assertJsonPath('meta.total', 1);
});

it('generates draft kpi rows for every eligible employee once per indicator', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $template = KpiTemplate::factory()->create(['organization_id' => $organization->id]);
    $indicator = KpiIndicator::factory()->create(['kpi_template_id' => $template->id, 'period' => 'monthly']);
    $period = KpiPeriod::factory()->create(['period_type' => 'monthly']);

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi/generate', [
        'kpi_period_id' => $period->id,
        'kpi_template_id' => $template->id,
    ])->assertCreated()->assertJsonPath('data.created', 2); // orgAdmin's own employee + employeeUser's

    $this->assertDatabaseHas('employee_kpis', [
        'employee_id' => $employeeUser->employee->id,
        'kpi_period_id' => $period->id,
        'kpi_indicator_id' => $indicator->id,
    ]);

    // Running it again creates nothing new.
    $second = $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/kpi/generate', [
        'kpi_period_id' => $period->id,
        'kpi_template_id' => $template->id,
    ]);
    $second->assertCreated()->assertJsonPath('data.created', 0);
});

it('aggregates a department report from published results only', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $period = KpiPeriod::factory()->create();

    EmployeeKpi::factory()->create([
        'employee_id' => $employeeUser->employee->id,
        'kpi_period_id' => $period->id,
        'weight' => 100,
        'actual_value' => 90,
        'score' => 90,
        'weighted_score' => 90,
        'approved_at' => now(),
    ]);
    EmployeeKpi::factory()->create(['employee_id' => $employeeUser->employee->id, 'kpi_period_id' => $period->id]); // draft, excluded

    $response = $this->actingAs($orgAdmin, 'sanctum')->getJson("/api/v1/kpi/report?period_id={$period->id}");

    $response->assertOk();
    $row = collect($response->json('data'))->first();
    expect($row['employees_count'])->toBe(1)
        ->and((float) $row['average_score'])->toBe(90.0);
});

describe('CalculateKpiScore', function () {
    it('scores manual and formula types as the entered actual value directly', function () {
        $calculator = app(CalculateKpiScore::class);

        foreach ([KpiCalculationType::Manual, KpiCalculationType::Formula] as $type) {
            $indicator = KpiIndicator::factory()->create(['calculation_type' => $type->value]);
            $employeeKpi = EmployeeKpi::factory()->create([
                'kpi_indicator_id' => $indicator->id,
                'target_value' => 100,
                'actual_value' => 85,
                'weight' => 50,
            ]);
            $employeeKpi->setRelation('indicator', $indicator);

            $result = $calculator->handle($employeeKpi);

            expect($result['score'])->toBe(85.0)->and($result['weighted_score'])->toBe(42.5);
        }
    });

    it('scores percentage/quantity/rating as the achievement ratio, clamped to 100', function () {
        $calculator = app(CalculateKpiScore::class);

        foreach ([KpiCalculationType::Percentage, KpiCalculationType::Quantity, KpiCalculationType::Rating] as $type) {
            $indicator = KpiIndicator::factory()->create(['calculation_type' => $type->value]);
            $employeeKpi = EmployeeKpi::factory()->create([
                'kpi_indicator_id' => $indicator->id,
                'target_value' => 50,
                'actual_value' => 75, // 150% achievement, clamped to 100
                'weight' => 100,
            ]);
            $employeeKpi->setRelation('indicator', $indicator);

            $result = $calculator->handle($employeeKpi);

            expect($result['score'])->toBe(100.0)->and($result['weighted_score'])->toBe(100.0);
        }
    });
});
