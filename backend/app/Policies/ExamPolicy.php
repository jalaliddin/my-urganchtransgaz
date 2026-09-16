<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    /**
     * Every authenticated user with `exams.view` may list exams; the
     * controller scopes the query to "applies to me" for a plain
     * employee and to everything for Safety Department/central roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('exams.view');
    }

    public function view(User $user, Exam $exam): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('exams.view')) {
            return false;
        }

        $employee = $user->employee;

        if ($employee && $exam->appliesTo($employee)) {
            return true;
        }

        return $employee && $exam->attempts()->where('employee_id', $employee->id)->exists();
    }

    /**
     * Creating/updating the exam and authoring its questions — Safety
     * Department (or central) only.
     */
    public function manage(User $user, ?Exam $exam = null): bool
    {
        return $user->can('exams.manage') && $this->isAdmin($user);
    }

    /**
     * Viewing the results roster/statistics — Safety Department (or
     * central) only.
     */
    public function evaluate(User $user, Exam $exam): bool
    {
        return $user->can('exams.evaluate') && $this->isAdmin($user);
    }

    /**
     * Starting/answering/submitting an attempt — the exam must apply to
     * the user's own employee record. No extra permission beyond
     * `exams.view` is required, the same "being eligible is enough"
     * precedent as attendance's check-in/check-out.
     */
    public function attempt(User $user, Exam $exam): bool
    {
        if (! $user->can('exams.view')) {
            return false;
        }

        $employee = $user->employee;

        return $employee !== null && $exam->appliesTo($employee);
    }

    /**
     * Safety Department's oversight is scoped narrowly to this module —
     * deliberately not folded into User::hasCentralAccess(), which would
     * incorrectly grant safety-manager reach into employees/attendance/
     * tasks it has no business rule for.
     */
    private function isAdmin(User $user): bool
    {
        return $user->hasRole('safety-manager') || $user->hasCentralAccess();
    }
}
