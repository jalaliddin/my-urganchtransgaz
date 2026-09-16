<?php

namespace App\Models;

use App\Enums\AnnouncementTargetType;
use Database\Factories\AnnouncementTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['announcement_id', 'target_type', 'target_id'])]
class AnnouncementTarget extends Model
{
    /** @use HasFactory<AnnouncementTargetFactory> */
    use HasFactory;

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_type' => AnnouncementTargetType::class,
        ];
    }
}
