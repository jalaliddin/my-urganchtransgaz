<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AnnouncementTargetType;
use App\Models\AnnouncementTarget;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin AnnouncementTarget
 */
class AnnouncementTargetResource extends JsonResource
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
            'target_type' => $this->target_type,
            'target_id' => $this->target_id,
            'label' => $this->resolveLabel(),
        ];
    }

    private function resolveLabel(): string
    {
        return match ($this->target_type) {
            AnnouncementTargetType::Everyone => 'Barcha xodimlar',
            AnnouncementTargetType::Central => 'Markaziy ofis',
            AnnouncementTargetType::Organization => Organization::find($this->target_id)?->name ?? '—',
            AnnouncementTargetType::Department => Department::find($this->target_id)?->name ?? '—',
            AnnouncementTargetType::Employee => Employee::find($this->target_id)?->fullName() ?? '—',
            AnnouncementTargetType::Role => Role::find($this->target_id)?->name ?? '—',
        };
    }
}
