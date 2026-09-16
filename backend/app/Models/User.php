<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AuthSource;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    /**
     * Central-level roles are not confined to a single organization.
     * Super-admin already bypasses every Gate check, but query-level
     * scoping (e.g. an index listing's WHERE clause) happens outside the
     * Gate and must check this explicitly.
     *
     * `hr` and `technical-policy` are included deliberately: per the
     * spec's own organization chart (§68), both are Central Office
     * services, not per-branch roles — a single HR department manages
     * employees/documents/users across all subordinate organizations, and
     * Technical Policy Service must likewise reach across organizations
     * to assign tasks (§9). Confining either to only the central office's
     * own ~9 employees would make it unable to do its job for the other
     * 14 organizations.
     */
    public function hasCentralAccess(): bool
    {
        return $this->hasAnyRole(['super-admin', 'central-admin', 'hr', 'technical-policy']);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'auth_source' => AuthSource::class,
            'last_login_at' => 'datetime',
        ];
    }
}
