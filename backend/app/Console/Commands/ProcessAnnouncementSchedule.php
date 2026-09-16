<?php

namespace App\Console\Commands;

use App\Actions\Announcements\PublishAnnouncement;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('announcements:process-schedule')]
#[Description('Auto-publish drafts whose publish_at has arrived, and auto-archive published announcements past their expire_at.')]
class ProcessAnnouncementSchedule extends Command
{
    public function handle(PublishAnnouncement $publish): int
    {
        Announcement::query()
            ->where('status', AnnouncementStatus::Draft)
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->with('targets')
            ->chunkById(100, function ($announcements) use ($publish) {
                foreach ($announcements as $announcement) {
                    $publish->handle($announcement);
                }
            });

        Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->whereNotNull('expire_at')
            ->where('expire_at', '<=', now())
            ->update(['status' => AnnouncementStatus::Archived]);

        return self::SUCCESS;
    }
}
