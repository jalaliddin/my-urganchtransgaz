<?php

namespace App\Models;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use Database\Factories\AttendanceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw, append-only log of every individual check-in/check-out scan —
 * unlike AttendanceRecord (one summary row per employee per day), every
 * call to RecordCheckInAction/RecordCheckOutAction writes one of these,
 * regardless of that action's own idempotent daily-summary behavior. This
 * is what answers "necha bora kirib chiqqan" (how many times did they
 * enter/exit) for a given day.
 */
#[Fillable(['employee_id', 'type', 'occurred_at', 'source'])]
class AttendanceEvent extends Model
{
    /** @use HasFactory<AttendanceEventFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttendanceEventType::class,
            'occurred_at' => 'datetime',
            'source' => AttendanceSource::class,
        ];
    }
}
