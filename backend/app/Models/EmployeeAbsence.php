<?php

namespace App\Models;

use App\Enums\AbsenceType;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeAbsenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An HR-recorded period an employee is away: any kind of leave, a sick
 * leave certificate, a business trip, or another excused absence. These
 * records drive the employee's status and how their days appear on the
 * attendance tabel.
 */
#[Fillable([
    'employee_id', 'type', 'start_date', 'end_date',
    'document_number', 'document_date', 'destination', 'notes',
    'file_path', 'file_name', 'created_by',
    'cancelled_at', 'cancelled_by', 'cancellation_reason',
])]
class EmployeeAbsence extends Model
{
    /** @use HasFactory<EmployeeAbsenceFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @param  Builder<EmployeeAbsence>  $query
     */
    public function scopeNotCancelled(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /**
     * @param  Builder<EmployeeAbsence>  $query
     */
    public function scopeCovering(Builder $query, CarbonInterface $date): void
    {
        $query->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString());
    }

    /**
     * Absences that share at least one day with the given range.
     *
     * @param  Builder<EmployeeAbsence>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString());
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Calendar days, both ends inclusive — the unit leave is counted in.
     */
    public function dayCount(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * upcoming | current | completed | cancelled, relative to today.
     */
    public function state(): string
    {
        $today = today();

        return match (true) {
            $this->isCancelled() => 'cancelled',
            $this->start_date->gt($today) => 'upcoming',
            $this->end_date->lt($today) => 'completed',
            default => 'current',
        };
    }

    /**
     * Readable type for exports.
     *
     * @return Attribute<string, never>
     */
    protected function typeLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->type->label());
    }

    /**
     * @return Attribute<int, never>
     */
    protected function days(): Attribute
    {
        return Attribute::get(fn (): int => $this->dayCount());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            // "date:Y-m-d", not plain "date" — see Employee::casts() for why.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'document_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }
}
