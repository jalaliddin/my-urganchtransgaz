<?php

namespace App\Models;

use App\Enums\IssueStatus;
use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reporter_employee_id', 'organization_id', 'department_id',
    'issue_category_id',
    'title', 'description', 'object_name', 'latitude', 'longitude',
    'status', 'resolution_note', 'resolved_by', 'resolved_at',
])]
class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    /**
     * Central leadership sees every issue — deliberately not the broader
     * `hasCentralAccess()` set (which also includes hr and safety-manager).
     */
    public static function seesAllIssues(User $user): bool
    {
        return $user->hasAnyRole(['technical-policy', 'central-admin', 'super-admin']);
    }

    /**
     * Issues this user may see in the list, on the map and in the report:
     * what they reported or are executing, plus their whole organization for
     * an organization-admin and what their department raised or is executing
     * for a department-manager. `IssuePolicy::view()` mirrors this rule.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (self::seesAllIssues($user)) {
            return;
        }

        $employee = $user->employee;

        // Qualified column names: the report joins organizations/categories.
        $query->where(function ($scope) use ($user, $employee) {
            $scope->where('issues.reporter_employee_id', $employee?->id ?? 0)
                ->orWhereHas('executors', fn ($executors) => $executors->whereKey($employee?->id ?? 0));

            if ($user->hasRole('organization-admin') && $employee) {
                $scope->orWhere('issues.organization_id', $employee->organization_id);
            }

            if ($user->hasRole('department-manager') && $employee?->department_id) {
                $scope->orWhere('issues.department_id', $employee->department_id)
                    ->orWhereHas('executors', fn ($executors) => $executors->where('employees.department_id', $employee->department_id));
            }
        });
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporter_employee_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IssueCategory::class, 'issue_category_id');
    }

    /**
     * The people doing the work — one or several, like a task's assignees.
     */
    public function executors(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'issue_executors')->withTimestamps();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(IssueComment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(IssueActivity::class)->latest();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'status' => IssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }
}
