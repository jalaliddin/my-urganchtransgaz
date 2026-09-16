<?php

namespace App\Models;

use App\Enums\ExamStatus;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'description', 'organization_id', 'department_id', 'created_by',
    'duration_minutes', 'passing_score', 'attempts_allowed',
    'start_date', 'end_date', 'status',
])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ExamQuestion::class)->orderBy('order');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /**
     * An exam's organization_id/department_id (both nullable) are its
     * audience, not a separate assignee table: null organization means
     * company-wide, an organization with a null department means that
     * whole organization, and both set means one department.
     */
    public function appliesTo(Employee $employee): bool
    {
        if ($this->organization_id === null) {
            return true;
        }

        if ($this->organization_id !== $employee->organization_id) {
            return false;
        }

        return $this->department_id === null || $this->department_id === $employee->department_id;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => ExamStatus::class,
            'duration_minutes' => 'integer',
            'passing_score' => 'integer',
            'attempts_allowed' => 'integer',
        ];
    }
}
