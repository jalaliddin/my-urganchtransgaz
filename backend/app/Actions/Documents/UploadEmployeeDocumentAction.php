<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class UploadEmployeeDocumentAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, User $uploader, UploadedFile $file, array $data): EmployeeDocument
    {
        $path = $file->store("employee-documents/{$employee->id}", 'local');

        return EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type_id' => $data['document_type_id'],
            'title' => $data['title'],
            'document_number' => $data['document_number'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => DocumentStatus::Pending,
            'uploaded_by' => $uploader->id,
        ]);
    }
}
