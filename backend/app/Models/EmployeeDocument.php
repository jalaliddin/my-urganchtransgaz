<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Database\Factories\EmployeeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'employee_id', 'document_type_id', 'title', 'document_number',
    'issue_date', 'expiry_date', 'file_path', 'mime_type', 'file_size',
    'status', 'uploaded_by', 'approved_by', 'approved_at', 'rejection_reason',
])]
class EmployeeDocument extends Model
{
    /** @use HasFactory<EmployeeDocumentFactory> */
    use HasFactory, SoftDeletes;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "date:Y-m-d", not plain "date" — see Employee::casts() for why
            // (a bare "date" cast shifts across the day boundary when
            // serialized to JSON under a non-UTC APP_TIMEZONE).
            'issue_date' => 'date:Y-m-d',
            'expiry_date' => 'date:Y-m-d',
            'approved_at' => 'datetime',
            'file_size' => 'integer',
            'status' => DocumentStatus::class,
        ];
    }
}
