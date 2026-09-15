<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\EmployeeDocument;
use App\Notifications\DocumentExpiring;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('documents:check-expiration')]
#[Description('Notify employees about documents expiring in 30/7/1 days, or already expired.')]
class CheckDocumentExpiration extends Command
{
    /**
     * @var int[]
     */
    private const THRESHOLDS = [30, 7, 1];

    public function handle(): int
    {
        foreach (self::THRESHOLDS as $days) {
            $this->notifyForDate(Carbon::today()->addDays($days), $days);
        }

        $this->notifyExpired();

        return self::SUCCESS;
    }

    private function notifyForDate(Carbon $date, int $threshold): void
    {
        EmployeeDocument::query()
            ->where('status', DocumentStatus::Approved)
            ->whereDate('expiry_date', $date)
            ->with('employee.user')
            ->chunkById(100, function ($documents) use ($threshold) {
                foreach ($documents as $document) {
                    $this->notifyOnce($document, $threshold);
                }
            });
    }

    private function notifyExpired(): void
    {
        EmployeeDocument::query()
            ->where('status', DocumentStatus::Approved)
            ->whereDate('expiry_date', '<', Carbon::today())
            ->with('employee.user')
            ->chunkById(100, function ($documents) {
                foreach ($documents as $document) {
                    $this->notifyOnce($document, 'expired');
                }
            });
    }

    private function notifyOnce(EmployeeDocument $document, int|string $threshold): void
    {
        $user = $document->employee->user;

        if (! $user) {
            return;
        }

        $alreadyNotified = $user->notifications()
            ->where('type', DocumentExpiring::class)
            ->whereJsonContains('data->document_id', $document->id)
            ->whereJsonContains('data->threshold', $threshold)
            ->exists();

        if ($alreadyNotified) {
            return;
        }

        $user->notify(new DocumentExpiring($document, $threshold));
    }
}
