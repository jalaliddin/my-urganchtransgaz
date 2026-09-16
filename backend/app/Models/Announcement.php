<?php

namespace App\Models;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\OrganizationType;
use App\Enums\TaskPriority;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'content', 'image_path', 'attachment_path', 'attachment_name',
    'author_id', 'priority', 'status', 'publish_at', 'expire_at',
])]
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory;

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AnnouncementTarget::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * An announcement OR-matches any one of its target rows — unlike
     * Exam's single nullable organization/department pair, this table
     * lets one announcement carry several simultaneous audiences (e.g.
     * "Organization A" and "role: safety-manager" at once).
     */
    public function appliesTo(Employee $employee): bool
    {
        $roleIds = null;

        foreach ($this->targets as $target) {
            $matches = match ($target->target_type) {
                AnnouncementTargetType::Everyone => true,
                AnnouncementTargetType::Central => $employee->organization?->type === OrganizationType::Central,
                AnnouncementTargetType::Organization => $target->target_id === $employee->organization_id,
                AnnouncementTargetType::Department => $target->target_id === $employee->department_id,
                AnnouncementTargetType::Employee => $target->target_id === $employee->id,
                AnnouncementTargetType::Role => in_array(
                    $target->target_id,
                    $roleIds ??= $employee->user?->roles->pluck('id')->all() ?? [],
                    true
                ),
            };

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * The employees this announcement's targets resolve to, in SQL — used
     * both by the controller's audience-feed scope and by
     * PublishAnnouncement to resolve who to notify. Kept in lockstep with
     * appliesTo()'s per-employee logic above, just expressed as a query.
     */
    public function eligibleEmployeesQuery(): Builder
    {
        if ($this->targets->contains(fn (AnnouncementTarget $target) => $target->target_type === AnnouncementTargetType::Everyone)) {
            return Employee::query();
        }

        return Employee::query()->where(function (Builder $query) {
            foreach ($this->targets as $target) {
                match ($target->target_type) {
                    AnnouncementTargetType::Central => $query->orWhereHas(
                        'organization',
                        fn (Builder $q) => $q->where('type', OrganizationType::Central->value)
                    ),
                    AnnouncementTargetType::Organization => $query->orWhere('organization_id', $target->target_id),
                    AnnouncementTargetType::Department => $query->orWhere('department_id', $target->target_id),
                    AnnouncementTargetType::Employee => $query->orWhere('id', $target->target_id),
                    AnnouncementTargetType::Role => $query->orWhereHas(
                        'user.roles',
                        fn (Builder $q) => $q->where('roles.id', $target->target_id)
                    ),
                    AnnouncementTargetType::Everyone => null,
                };
            }
        });
    }

    /**
     * Whether this announcement is currently live for its audience:
     * published, its scheduled publish time (if any) has arrived, and it
     * hasn't expired yet.
     */
    public function isCurrentlyLive(): bool
    {
        if ($this->status !== AnnouncementStatus::Published) {
            return false;
        }

        if ($this->publish_at !== null && $this->publish_at->isFuture()) {
            return false;
        }

        return $this->expire_at === null || $this->expire_at->isFuture();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => AnnouncementStatus::class,
            'publish_at' => 'datetime',
            'expire_at' => 'datetime',
        ];
    }
}
