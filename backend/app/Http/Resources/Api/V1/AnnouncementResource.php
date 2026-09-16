<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'has_image' => $this->image_path !== null,
            'has_attachment' => $this->attachment_path !== null,
            'attachment_name' => $this->attachment_name,
            'author_id' => $this->author_id,
            'author_name' => $this->whenLoaded('author', fn () => $this->author->name),
            'priority' => $this->priority,
            'status' => $this->status,
            'publish_at' => $this->publish_at,
            'expire_at' => $this->expire_at,
            'is_live' => $this->isCurrentlyLive(),

            'targets' => AnnouncementTargetResource::collection($this->whenLoaded('targets')),

            // Present only when the controller eager-loaded `reads`
            // constrained to the viewing employee — see
            // AnnouncementController's audience scope.
            'is_read' => $this->whenLoaded('reads', fn () => $this->reads->isNotEmpty()),

            'reads_count' => $this->whenCounted('reads'),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
