<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Organization;
use App\Notifications\DocumentApproved;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

it('returns 401 when listing documents without authentication', function () {
    $this->getJson('/api/v1/documents')->assertStatus(401);
});

it('lets an employee upload their own document', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $documentType = DocumentType::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/documents', [
        'document_type_id' => $documentType->id,
        'title' => 'My Passport',
        'file' => UploadedFile::fake()->create('passport.pdf', 500, 'application/pdf'),
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
    $this->assertDatabaseHas('employee_documents', [
        'employee_id' => $user->employee->id,
        'title' => 'My Passport',
    ]);
});

it('rejects an upload with a disallowed file type', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $documentType = DocumentType::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/documents', [
        'document_type_id' => $documentType->id,
        'title' => 'Bad file',
        'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('only shows an employee their own documents in the index', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    EmployeeDocument::factory()->create(['employee_id' => $user->employee->id]);
    EmployeeDocument::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/documents');

    $response->assertOk()->assertJsonPath('meta.total', 1);
});

it('lets hr see documents across every organization', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    EmployeeDocument::factory()->count(3)->create(['employee_id' => Employee::factory()->create(['organization_id' => Organization::factory()->create()->id])->id]);

    $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/documents');

    $response->assertOk()->assertJsonPath('meta.total', 3);
});

it('forbids a manager from viewing a document outside their organization', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create(['employee_id' => Employee::factory()->create(['organization_id' => Organization::factory()->create()->id])->id]);

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/documents/{$document->id}")
        ->assertStatus(403);
});

it('lets the owning employee download their own document', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    Storage::disk('local')->put('employee-documents/1/file.pdf', 'content');
    $document = EmployeeDocument::factory()->create([
        'employee_id' => $user->employee->id,
        'file_path' => 'employee-documents/1/file.pdf',
    ]);

    $this->actingAs($user, 'sanctum')
        ->get("/api/v1/documents/{$document->id}/download")
        ->assertOk();
});

it('lets hr approve a pending document and notifies the employee', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create(['employee_id' => $employeeUser->employee->id]);

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/documents/{$document->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($document->fresh())
        ->status->toBe(DocumentStatus::Approved)
        ->approved_by->toBe($hr->id);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employeeUser->id,
        'type' => DocumentApproved::class,
    ]);
});

it('rejects a document with a required reason and notifies the employee', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create();

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/documents/{$document->id}/reject", ['reason' => 'Rasm sifatsiz.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($document->fresh()->rejection_reason)->toBe('Rasm sifatsiz.');
});

it('will not reject a document without a reason', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create();

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/documents/{$document->id}/reject", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('will not re-approve a document that was already reviewed', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $document = EmployeeDocument::factory()->approved()->create();

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/documents/{$document->id}/approve")
        ->assertStatus(409);
});

it('forbids a manager from approving a document', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => $manager->employee->organization_id])->id,
    ]);

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/documents/{$document->id}/approve")
        ->assertStatus(403);
});

it('lets an employee delete their own pending document', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->create(['employee_id' => $user->employee->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertOk();

    $this->assertSoftDeleted('employee_documents', ['id' => $document->id]);
});

it('forbids an employee from deleting their own already-approved document', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    $document = EmployeeDocument::factory()->approved()->create(['employee_id' => $user->employee->id]);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/documents/{$document->id}")
        ->assertStatus(403);
});
