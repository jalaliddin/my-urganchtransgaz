<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TaskAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately omits file_path — see EmployeeDocumentResource for why.
 *
 * @mixin TaskAttachment
 */
class TaskAttachmentResource extends JsonResource
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
            'task_id' => $this->task_id,
            'uploaded_by' => $this->uploaded_by,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'download_url' => route('tasks.attachments.download', ['task' => $this->task_id, 'attachment' => $this->id]),
            'created_at' => $this->created_at,
        ];
    }
}
