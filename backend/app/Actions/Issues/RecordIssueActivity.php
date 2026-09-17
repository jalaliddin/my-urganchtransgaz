<?php

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\User;

class RecordIssueActivity
{
    public function handle(Issue $issue, ?User $causer, string $action, string $description): IssueActivity
    {
        return $issue->activities()->create([
            'causer_id' => $causer?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
