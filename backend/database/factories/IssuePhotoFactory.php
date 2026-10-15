<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssuePhoto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IssuePhoto>
 */
class IssuePhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'uploaded_by' => null,
            'path' => fn (array $attributes) => "issue-photos/{$attributes['issue_id']}/".Str::uuid().'.jpg',
        ];
    }
}
