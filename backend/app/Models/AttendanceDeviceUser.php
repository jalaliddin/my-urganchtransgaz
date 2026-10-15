<?php

namespace App\Models;

use Database\Factories\AttendanceDeviceUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person enrolled on a terminal (its `UserID`, and the name it shows).
 * Matched to an employee through `employees.dahua_person_id`.
 */
#[Fillable(['attendance_device_id', 'user_id', 'name', 'card_no'])]
class AttendanceDeviceUser extends Model
{
    /** @use HasFactory<AttendanceDeviceUserFactory> */
    use HasFactory;

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }
}
