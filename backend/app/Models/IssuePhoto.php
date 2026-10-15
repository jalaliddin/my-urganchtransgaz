<?php

namespace App\Models;

use Database\Factories\IssuePhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'uploaded_by', 'path'])]
class IssuePhoto extends Model
{
    /** @use HasFactory<IssuePhotoFactory> */
    use HasFactory;

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
