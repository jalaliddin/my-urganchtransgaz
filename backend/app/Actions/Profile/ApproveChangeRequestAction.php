<?php

namespace App\Actions\Profile;

use App\Enums\ChangeRequestStatus;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Notifications\ProfileChangeApproved;
use Illuminate\Support\Facades\DB;

class ApproveChangeRequestAction
{
    public function handle(EmployeeChangeRequest $changeRequest, User $reviewer, ?string $comment = null): EmployeeChangeRequest
    {
        return DB::transaction(function () use ($changeRequest, $reviewer, $comment) {
            $employee = $changeRequest->employee;
            $employee->update($changeRequest->changes);

            $changeRequest->update([
                'status' => ChangeRequestStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ]);

            $employee->user?->notify(new ProfileChangeApproved($changeRequest));

            return $changeRequest->fresh();
        });
    }
}
