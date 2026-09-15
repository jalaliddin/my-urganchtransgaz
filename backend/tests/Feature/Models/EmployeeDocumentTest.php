<?php

use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;

it('casts status to its backed enum', function () {
    $document = EmployeeDocument::factory()->create(['status' => 'approved']);

    expect($document->status)->toBe(DocumentStatus::Approved);
});

it('resolves its employee, document type, uploader, and approver relationships', function () {
    $employee = Employee::factory()->create();
    $documentType = DocumentType::factory()->create();
    $uploader = User::factory()->create();
    $approver = User::factory()->create();

    $document = EmployeeDocument::factory()->create([
        'employee_id' => $employee->id,
        'document_type_id' => $documentType->id,
        'uploaded_by' => $uploader->id,
        'approved_by' => $approver->id,
    ]);

    expect($document->employee->is($employee))->toBeTrue()
        ->and($document->documentType->is($documentType))->toBeTrue()
        ->and($document->uploader->is($uploader))->toBeTrue()
        ->and($document->approver->is($approver))->toBeTrue();
});

it('is soft deleted rather than removed from the database', function () {
    $document = EmployeeDocument::factory()->create();

    $document->delete();

    expect(EmployeeDocument::find($document->id))->toBeNull();
    $this->assertSoftDeleted('employee_documents', ['id' => $document->id]);
});
