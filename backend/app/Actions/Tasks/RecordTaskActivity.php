<?php

namespace App\Actions\Tasks;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;

class RecordTaskActivity
{
    public function handle(Task $task, ?User $causer, string $action, string $description): TaskActivity
    {
        return $task->activities()->create([
            'causer_id' => $causer?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
