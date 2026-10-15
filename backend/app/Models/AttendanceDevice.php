<?php

namespace App\Models;

use Database\Factories\AttendanceDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['device_id', 'name', 'organization_id', 'token', 'is_active', 'last_used_at', 'roster_synced_at', 'roster_complete'])]
#[Hidden(['token'])]
class AttendanceDevice extends Model
{
    /** @use HasFactory<AttendanceDeviceFactory> */
    use HasFactory;

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(AttendanceDeviceUser::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
            'roster_synced_at' => 'datetime',
            'roster_complete' => 'boolean',
        ];
    }
}
