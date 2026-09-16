<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDeadlineApproaching;
use App\Notifications\TaskOverdue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('tasks:check-deadlines')]
#[Description('Notify assignees about tasks due in 3/1 days or today, and mark overdue tasks automatically.')]
class CheckTaskDeadlines extends Command
{
    /**
     * @var int[]
     */
    private const THRESHOLDS = [3, 1, 0];

    /**
     * Only tasks an assignee hasn't submitted yet are eligible for these
     * reminders/the overdue flip — once a task is "waiting" (submitted,
     * awaiting manager approval) or already terminal, a passing deadline
     * shouldn't silently overwrite that state.
     */
    private const OPEN_STATUSES = [TaskStatus::New, TaskStatus::InProgress];

    public function handle(): int
    {
        foreach (self::THRESHOLDS as $days) {
            $this->notifyForDate(Carbon::today()->addDays($days), $days);
        }

        $this->markOverdue();

        return self::SUCCESS;
    }

    private function notifyForDate(Carbon $date, int $threshold): void
    {
        Task::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('due_date', $date)
            ->with('assignees.user')
            ->chunkById(100, function ($tasks) use ($threshold) {
                foreach ($tasks as $task) {
                    foreach ($task->assignees as $employee) {
                        $this->notifyOnce($employee->user, $task, $threshold);
                    }
                }
            });
    }

    private function markOverdue(): void
    {
        Task::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereDate('due_date', '<', Carbon::today())
            ->with('assignees.user')
            ->chunkById(100, function ($tasks) {
                foreach ($tasks as $task) {
                    $task->update(['status' => TaskStatus::Overdue]);

                    foreach ($task->assignees as $employee) {
                        $employee->user?->notify(new TaskOverdue($task));
                    }
                }
            });
    }

    private function notifyOnce(?User $user, Task $task, int $threshold): void
    {
        if (! $user) {
            return;
        }

        $alreadyNotified = $user->notifications()
            ->where('type', TaskDeadlineApproaching::class)
            ->whereJsonContains('data->task_id', $task->id)
            ->whereJsonContains('data->threshold', $threshold)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $user->notify(new TaskDeadlineApproaching($task, $threshold));
    }
}
