<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Database\Factories\IssueCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'sort_order', 'status'])]
class IssueCategory extends Model
{
    /** @use HasFactory<IssueCategoryFactory> */
    use HasFactory;

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => ActiveStatus::class,
        ];
    }
}
