<?php

namespace App\Http\Requests;

class UpdateAnnouncementRequest extends StoreAnnouncementRequest
{
    /**
     * Same target-scope restriction as creating, plus: only the author or
     * a central role may edit an existing announcement at all.
     */
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $announcement = $this->route('announcement');
        $user = $this->user();

        return $announcement->author_id === $user->id || $user->hasCentralAccess();
    }
}
