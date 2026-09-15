<?php

namespace App\Models;

use App\Enums\ContactType;
use Database\Factories\EmployeeContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id', 'type', 'full_name', 'relationship', 'phone',
    'address', 'bank_name', 'bank_account_number',
])]
class EmployeeContact extends Model
{
    /** @use HasFactory<EmployeeContactFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
        ];
    }
}
