<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'description', 'creator_id', 'organization_id', 'department_id',
    'task_category_id',
    'priority', 'status', 'start_date', 'due_date', 'completed_at',
    'progress', 'result',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * Tasks this user may see in lists and counts: everything for a central
     * role; otherwise what they created or are assigned to, plus — with
     * `tasks.view` — their organization's tasks (only their department's
     * for a department-manager). `TaskPolicy::view()` mirrors this rule.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasCentralAccess()) {
            return;
        }

        $employee = $user->employee;

        $query->where(function ($involvedOrScoped) use ($user, $employee) {
            $involvedOrScoped->where('creator_id', $user->id)
                ->orWhereHas('assignees', fn ($assigneeQuery) => $assigneeQuery->where('employees.id', $employee?->id));

            if ($user->can('tasks.view')) {
                $involvedOrScoped->orWhere(function ($orgQuery) use ($user, $employee) {
                    $orgQuery->where('organization_id', $employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $orgQuery->where('department_id', $employee?->department_id);
                    }
                });
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'task_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'task_assignees')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->latest();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "date:Y-m-d", not plain "date" — see Employee::casts() for
            // why a bare "date" cast shifts across the day boundary once
            // JSON-serialized under a non-UTC APP_TIMEZONE.
            'start_date' => 'date:Y-m-d',
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'progress' => 'integer',
        ];
    }
}
