<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function importCsv(array $rows): UploadedFile
{
    $header = 'employee_number,first_name,last_name,middle_name,organization_code,department_code,position_code,gender,birth_date,hire_date,phone,email,employment_type,status';
    $lines = array_map(fn (array $row) => implode(',', $row), $rows);
    $content = implode("\n", [$header, ...$lines]);

    return UploadedFile::fake()->createWithContent('employees.csv', $content);
}

it('returns 401 when downloading the template without authentication', function () {
    $this->getJson('/api/v1/employees/import/template')->assertStatus(401);
});

it('forbids a plain employee from importing', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')
        ->postJson('/api/v1/employees/import', ['file' => importCsv([])])
        ->assertStatus(403);
});

it('lets hr download the import template as a csv with the expected headers', function () {
    $hr = userWithRole('hr');

    $response = $this->actingAs($hr, 'sanctum')->get('/api/v1/employees/import/template');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('employee_number,first_name,last_name');
});

it('previews a mixed valid/invalid file in dry-run mode without creating anything', function () {
    $organization = Organization::factory()->create(['code' => 'ORG-DRY']);
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPDRY01', 'Vali', 'Yusupov', '', 'ORG-DRY', '', '', 'male', '', '', '', '', '', ''],
        ['', 'NoNumber', 'Missing', '', 'ORG-DRY', '', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import?dry_run=1', ['file' => $file]);

    $response->assertOk()->assertJsonPath('data.dry_run', true);
    expect($response->json('data.imported_count'))->toBe(1);
    expect($response->json('data.invalid'))->toHaveCount(1);
    $this->assertDatabaseMissing('employees', ['employee_number' => 'EMPDRY01']);
});

it('imports only the valid rows and reports the invalid one, never importing it silently', function () {
    $organization = Organization::factory()->create(['code' => 'ORG-REAL']);
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPREAL01', 'Shokir', 'Nazarov', '', 'ORG-REAL', '', '', 'male', '', '', '', '', '', ''],
        ['', 'NoNumber', 'Missing', '', 'ORG-REAL', '', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import', ['file' => $file]);

    $response->assertOk();
    expect($response->json('data.imported_count'))->toBe(1);
    expect($response->json('data.invalid'))->toHaveCount(1);
    $this->assertDatabaseHas('employees', ['employee_number' => 'EMPREAL01', 'organization_id' => $organization->id]);
    $this->assertDatabaseMissing('employees', ['first_name' => 'NoNumber']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'created', 'module' => 'employees']);
});

it('rejects a duplicate employee_number already in the database', function () {
    $organization = Organization::factory()->create(['code' => 'ORG-DUP']);
    Employee::factory()->create(['employee_number' => 'EMPDUP01']);
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPDUP01', 'Karim', 'Aliyev', '', 'ORG-DUP', '', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import', ['file' => $file]);

    expect($response->json('data.imported_count'))->toBe(0);
    expect($response->json('data.invalid'))->toHaveCount(1);
});

it('rejects two rows in the same file with the same employee_number', function () {
    $organization = Organization::factory()->create(['code' => 'ORG-SAME']);
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPSAME01', 'Aziz', 'Karimov', '', 'ORG-SAME', '', '', '', '', '', '', '', '', ''],
        ['EMPSAME01', 'Vali', 'Toshev', '', 'ORG-SAME', '', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import', ['file' => $file]);

    expect($response->json('data.imported_count'))->toBe(0);
    expect($response->json('data.invalid'))->toHaveCount(2);
});

it('skips a row whose department_code belongs to a different organization', function () {
    $organizationA = Organization::factory()->create(['code' => 'ORG-A-DEP']);
    $organizationB = Organization::factory()->create(['code' => 'ORG-B-DEP']);
    $foreignDepartment = Department::factory()->create(['organization_id' => $organizationB->id, 'code' => 'DEP-FOREIGN']);
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPXORG01', 'Nodir', 'Yoqubov', '', 'ORG-A-DEP', 'DEP-FOREIGN', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import', ['file' => $file]);

    expect($response->json('data.imported_count'))->toBe(0);
    expect($response->json('data.skipped'))->toHaveCount(1);
    $this->assertDatabaseMissing('employees', ['employee_number' => 'EMPXORG01']);
});

it('rejects a row referencing an organization_code that does not exist', function () {
    $hr = userWithRole('hr');

    $file = importCsv([
        ['EMPNOORG01', 'Sardor', 'Rahimov', '', 'ORG-DOES-NOT-EXIST', '', '', '', '', '', '', '', '', ''],
    ]);

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/employees/import', ['file' => $file]);

    expect($response->json('data.imported_count'))->toBe(0);
    expect($response->json('data.invalid'))->toHaveCount(1);
});
