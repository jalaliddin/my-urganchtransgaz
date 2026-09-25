<?php

use App\Models\Department;
use App\Models\Issue;
use App\Models\IssueCategory;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 without authentication', function () {
    $this->getJson('/api/v1/issues/report')->assertUnauthorized();
});

it('forbids a role without issues.report with 403', function () {
    $employeeUser = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/issues/report')->assertForbidden();
});

it('gives leadership company-wide totals and a per-organization breakdown', function () {
    $this->travelTo('2026-09-20 12:00:00');
    $urganch = Organization::factory()->create(['name' => 'Urganch tumani']);
    $xiva = Organization::factory()->create(['name' => 'Xiva tumani']);
    Issue::factory()->create(['organization_id' => $urganch->id, 'created_at' => '2026-09-19 10:00:00']);
    Issue::factory()->create(['organization_id' => $urganch->id, 'created_at' => '2026-09-05 12:00:00']);
    Issue::factory()->resolved()->create([
        'organization_id' => $urganch->id,
        'created_at' => '2026-09-10 08:00:00',
        'resolved_at' => '2026-09-10 13:00:00',
    ]);
    Issue::factory()->resolved()->create([
        'organization_id' => $xiva->id,
        'created_at' => '2026-09-15 09:00:00',
        'resolved_at' => '2026-09-15 12:00:00',
    ]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues/report');

    $response->assertOk()
        ->assertJsonPath('data.totals', [
            'total' => 4,
            'open_count' => 2,
            'resolved_count' => 2,
            'stale_open_count' => 1,
            'resolution_rate' => 50,
            'avg_resolution_hours' => 4,
        ])
        ->assertJsonCount(2, 'data.by_organization')
        ->assertJsonPath('data.by_organization.0.organization_name', 'Urganch tumani')
        ->assertJsonPath('data.by_organization.0.total', 3)
        ->assertJsonPath('data.by_organization.0.open_count', 2)
        ->assertJsonPath('data.by_organization.0.stale_open_count', 1)
        ->assertJsonPath('data.by_organization.0.resolution_rate', 33)
        ->assertJsonPath('data.by_organization.0.avg_resolution_hours', 5)
        ->assertJsonPath('data.by_organization.1.organization_name', 'Xiva tumani')
        ->assertJsonPath('data.by_organization.1.resolution_rate', 100);
});

it('limits an organization-admin to their own organization\'s issues', function () {
    $ownOrganization = Organization::factory()->create();
    Issue::factory()->count(2)->create(['organization_id' => $ownOrganization->id]);
    Issue::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $organizationAdmin = userWithRole('organization-admin', $ownOrganization);

    $response = $this->actingAs($organizationAdmin, 'sanctum')->getJson('/api/v1/issues/report');

    $response->assertOk()
        ->assertJsonPath('data.totals.total', 2)
        ->assertJsonCount(1, 'data.by_organization')
        ->assertJsonPath('data.by_organization.0.organization_id', $ownOrganization->id)
        ->assertJsonCount(2, 'data.points');
});

it('limits a department-manager to what their department raised', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $ownIssue = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $ownDepartment->id]);
    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id]);
    $departmentManager = userWithRole('department-manager', $organization, $ownDepartment);

    $response = $this->actingAs($departmentManager, 'sanctum')->getJson('/api/v1/issues/report');

    $response->assertOk()
        ->assertJsonPath('data.totals.total', 1)
        ->assertJsonPath('data.points.0.id', $ownIssue->id);
});

it('counts only issues reported within the date range and fills empty months with zeros', function () {
    $this->travelTo('2026-09-20 12:00:00');
    Issue::factory()->resolved()->create(['created_at' => '2026-06-10 09:00:00', 'resolved_at' => '2026-08-02 09:00:00']);
    Issue::factory()->create(['created_at' => '2026-08-15 09:00:00']);
    Issue::factory()->create(['created_at' => '2026-04-01 09:00:00']);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')
        ->getJson('/api/v1/issues/report?date_from=2026-06-01&date_to=2026-09-30');

    $response->assertOk()
        ->assertJsonPath('data.totals.total', 2)
        ->assertJsonPath('data.monthly', [
            ['month' => '2026-06', 'created' => 1, 'resolved' => 0],
            ['month' => '2026-07', 'created' => 0, 'resolved' => 0],
            ['month' => '2026-08', 'created' => 1, 'resolved' => 1],
        ]);
});

it('groups issues by category, keeping issues without a category', function () {
    $gasLeak = IssueCategory::factory()->create(['name' => 'Gaz sizib chiqishi']);
    Issue::factory()->count(2)->create(['issue_category_id' => $gasLeak->id]);
    Issue::factory()->create(['issue_category_id' => null]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues/report');

    $response->assertOk()
        ->assertJsonCount(2, 'data.by_category')
        ->assertJsonPath('data.by_category.0.category_name', 'Gaz sizib chiqishi')
        ->assertJsonPath('data.by_category.0.total', 2)
        ->assertJsonPath('data.by_category.1.issue_category_id', null)
        ->assertJsonPath('data.by_category.1.total', 1);
});

it('returns map points with how many days each open issue has been open', function () {
    $this->travelTo('2026-09-20 12:00:00');
    $issue = Issue::factory()->create(['created_at' => '2026-09-05 12:00:00', 'latitude' => 41.55, 'longitude' => 60.63]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues/report');

    $response->assertOk()
        ->assertJsonPath('data.points.0.id', $issue->id)
        ->assertJsonPath('data.points.0.latitude', 41.55)
        ->assertJsonPath('data.points.0.open_days', 15)
        ->assertJsonPath('data.points_truncated', false);
});

it('rejects an end date before the start date with 422', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $this->actingAs($technicalPolicyUser, 'sanctum')
        ->getJson('/api/v1/issues/report?date_from=2026-09-10&date_to=2026-09-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_to');
});

it('downloads the per-organization table as CSV', function () {
    $organization = Organization::factory()->create(['name' => 'Xonqa tumani']);
    Issue::factory()->count(2)->create(['organization_id' => $organization->id]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->get('/api/v1/issues/report?export=csv');

    $response->assertDownload('muammolar-hisoboti.csv');
    expect($response->streamedContent())
        ->toContain('Tashkilot,"Jami muammolar"')
        ->toContain('"Xonqa tumani",2,2,0');
});

it('downloads the per-organization table as an Excel workbook', function () {
    Issue::factory()->count(2)->create();
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->get('/api/v1/issues/report?export=xlsx');

    $response->assertDownload('muammolar-hisoboti.xlsx');
});
