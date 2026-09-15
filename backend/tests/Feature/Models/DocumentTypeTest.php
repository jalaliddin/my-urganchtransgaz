<?php

use App\Enums\ActiveStatus;
use App\Models\DocumentType;
use App\Models\EmployeeDocument;

it('casts requires_expiry to boolean and status to its backed enum', function () {
    $documentType = DocumentType::factory()->create(['requires_expiry' => 1, 'status' => 'active']);

    expect($documentType->requires_expiry)->toBeTrue()
        ->and($documentType->status)->toBe(ActiveStatus::Active);
});

it('lists the documents that use it', function () {
    $documentType = DocumentType::factory()->create();
    $document = EmployeeDocument::factory()->create(['document_type_id' => $documentType->id]);

    expect($documentType->documents->pluck('id'))->toContain($document->id);
});
