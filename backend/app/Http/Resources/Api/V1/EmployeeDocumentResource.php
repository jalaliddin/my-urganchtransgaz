<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately omits file_path: the raw private-disk path is an internal
 * detail. Files are always fetched through the authenticated download
 * endpoint, never a direct path/URL.
 *
 * @mixin EmployeeDocument
 */
class EmployeeDocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'document_type' => new DocumentTypeResource($this->whenLoaded('documentType')),
            'title' => $this->title,
            'document_number' => $this->document_number,
            // Explicit format, not the bare cast value — see
            // EmployeeResource for why a "date" cast still needs this.
            'issue_date' => $this->issue_date?->format('Y-m-d'),
            'expiry_date' => $this->expiry_date?->format('Y-m-d'),
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'uploaded_by' => $this->uploaded_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'rejection_reason' => $this->rejection_reason,
            'download_url' => route('documents.download', $this->id),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
