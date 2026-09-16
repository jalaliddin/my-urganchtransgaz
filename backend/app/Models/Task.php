<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'description', 'creator_id', 'organization_id', 'department_id',
    'priority', 'status', 'start_date', 'due_date', 'completed_at',
    'progress', 'result',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

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
