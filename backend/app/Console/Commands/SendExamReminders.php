<?php

namespace App\Console\Commands;

use App\Enums\ExamStatus;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ExamAvailable;
use App\Notifications\ExamDeadlineApproaching;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

#[Signature('exams:send-reminders')]
#[Description('Notify eligible employees about upcoming exams and approaching deadlines.')]
class SendExamReminders extends Command
{
    /**
     * Default "upcoming exam" reminder days-before, Settings-backed
     * (group "exams", key "exams.reminder_thresholds").
     *
     * @var int[]
     */
    private const UPCOMING_THRESHOLDS = [3, 1];

    /**
     * @var int[]
     */
    private const DEADLINE_THRESHOLDS = [3, 1, 0];

    public function handle(): int
    {
        $upcomingThresholds = Setting::get('exams.reminder_thresholds', self::UPCOMING_THRESHOLDS);

        foreach ($upcomingThresholds as $days) {
            $this->notifyForExams(
                Exam::query()->where('status', ExamStatus::Active)->whereDate('start_date', Carbon::today()->addDays($days))->get(),
                $days,
                ExamAvailable::class,
            );
        }

        foreach (self::DEADLINE_THRESHOLDS as $days) {
            $this->notifyForExams(
                Exam::query()->where('status', ExamStatus::Active)->whereDate('end_date', Carbon::today()->addDays($days))->get(),
                $days,
                ExamDeadlineApproaching::class,
            );
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Exam>  $exams
     * @param  class-string<ExamAvailable|ExamDeadlineApproaching>  $notificationClass
     */
    private function notifyForExams(Collection $exams, int $threshold, string $notificationClass): void
    {
        foreach ($exams as $exam) {
            Employee::query()
                ->when($exam->organization_id, fn ($query) => $query->where('organization_id', $exam->organization_id))
                ->when($exam->department_id, fn ($query) => $query->where('department_id', $exam->department_id))
                ->whereDoesntHave('examAttempts', fn ($query) => $query->where('exam_id', $exam->id)->where('passed', true))
                ->with('user')
                ->chunkById(200, function ($employees) use ($exam, $threshold, $notificationClass) {
                    foreach ($employees as $employee) {
                        $this->notifyOnce($employee->user, $exam, $threshold, $notificationClass);
                    }
                });
        }
    }

    /**
     * @param  class-string<ExamAvailable|ExamDeadlineApproaching>  $notificationClass
     */
    private function notifyOnce(?User $user, Exam $exam, int $threshold, string $notificationClass): void
    {
        if (! $user) {
            return;
        }

        $alreadyNotified = $user->notifications()
            ->where('type', $notificationClass)
            ->whereJsonContains('data->exam_id', $exam->id)
            ->whereJsonContains('data->threshold', $threshold)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $user->notify(new $notificationClass($exam, $threshold));
    }
}
