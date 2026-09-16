<?php

namespace App\Actions\Announcements;

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Notifications\AnnouncementPublished;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Notification;

class PublishAnnouncement
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Flip the announcement live and notify its resolved audience.
     *
     * Uses Notification::send() (a batch insert) rather than a per-employee
     * ->notify() loop like Tasks' handful of assignees — an "everyone"
     * announcement's audience can be the whole company.
     */
    public function handle(Announcement $announcement): Announcement
    {
        $announcement->update([
            'status' => AnnouncementStatus::Published,
            'publish_at' => $announcement->publish_at ?? now(),
        ]);

        $recipients = $announcement->eligibleEmployeesQuery()->with('user')->get()
            ->map(fn ($employee) => $employee->user)
            ->filter();

        Notification::send($recipients, new AnnouncementPublished($announcement));

        $this->auditLog->log('published', 'announcements', $announcement);

        return $announcement;
    }
}
