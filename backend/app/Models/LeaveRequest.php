<?php

namespace App\Models;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id', 'type', 'start_date', 'end_date', 'reason', 'status',
    'department_reviewed_by', 'department_reviewed_at',
    'hr_reviewed_by', 'hr_reviewed_at', 'rejection_reason',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function departmentReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_reviewed_by');
    }

    public function hrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    /**
     * Whether today falls within this request's date range — used both
     * by the immediate-on-approve status sync and the daily command.
     */
    public function coversToday(): bool
    {
        $today = now()->toDateString();

        return $this->start_date->toDateString() <= $today && $today <= $this->end_date->toDateString();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaveRequestType::class,
            'status' => LeaveRequestStatus::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'department_reviewed_at' => 'datetime',
            'hr_reviewed_at' => 'datetime',
        ];
    }
}
