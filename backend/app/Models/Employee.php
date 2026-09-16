<?php

namespace App\Models;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'organization_id', 'department_id', 'position_id',
    'employee_number', 'first_name', 'last_name', 'middle_name',
    'birth_date', 'birth_place', 'gender',
    'phone', 'email', 'corporate_email',
    'address', 'passport_number', 'pinfl',
    'employment_type', 'hire_date', 'termination_date',
    'photo', 'status',
])]
#[Hidden(['passport_number', 'pinfl'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function managedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'manager_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EmployeeContact::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(EmployeeChangeRequest::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function todayAttendance(): HasOne
    {
        return $this->hasOne(AttendanceRecord::class)->whereDate('date', now()->toDateString());
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')->withTimestamps();
    }

    public function fullName(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "date:Y-m-d" (not plain "date") keeps JSON serialization as a
            // bare calendar date. Plain "date" round-trips through Carbon's
            // toJSON(), which converts to UTC — since APP_TIMEZONE is
            // Asia/Tashkent (+5), a stored date's midnight shifts to the
            // previous day once serialized, so anything that reads the
            // date back from the API (e.g. the profile form) would see the
            // wrong day and could even "change" a field the user never
            // touched.
            'birth_date' => 'date:Y-m-d',
            'hire_date' => 'date:Y-m-d',
            'termination_date' => 'date:Y-m-d',
            'gender' => Gender::class,
            'employment_type' => EmploymentType::class,
            'status' => EmployeeStatus::class,
            'passport_number' => 'encrypted',
            'pinfl' => 'encrypted',
        ];
    }
}
