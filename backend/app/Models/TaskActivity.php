<?php

namespace App\Models;

use Database\Factories\TaskActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'causer_id', 'action', 'description'])]
class TaskActivity extends Model
{
    /** @use HasFactory<TaskActivityFactory> */
    use HasFactory;

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }
}
