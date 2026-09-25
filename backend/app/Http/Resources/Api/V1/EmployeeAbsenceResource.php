<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeAbsence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Omits file_path, like EmployeeDocumentResource: the attached order or
 * certificate is only ever fetched through the authorized download route.
 *
 * @mixin EmployeeAbsence
 */
class EmployeeAbsenceResource extends JsonResource
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
            'type' => $this->type,
            'type_label' => $this->type->label(),
            // Explicit format, not the bare cast value — see
            // EmployeeResource for why a "date" cast still needs this.
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'days' => $this->dayCount(),
            'state' => $this->state(),
            'document_number' => $this->document_number,
            'document_date' => $this->document_date?->format('Y-m-d'),
            'destination' => $this->destination,
            'notes' => $this->notes,
            'file_name' => $this->file_name,
            'download_url' => $this->file_path ? route('absences.download', $this->id) : null,
            'created_by' => $this->created_by,
            'creator_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'cancelled_at' => $this->cancelled_at,
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
