<?php

namespace App\Models;

use App\Enums\ChangeRequestStatus;
use Database\Factories\EmployeeChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id', 'requested_by', 'changes', 'status',
    'reviewed_by', 'reviewed_at', 'review_comment',
])]
class EmployeeChangeRequest extends Model
{
    /** @use HasFactory<EmployeeChangeRequestFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'status' => ChangeRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
