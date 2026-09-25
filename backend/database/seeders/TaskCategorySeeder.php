<?php

namespace Database\Seeders;

use App\Models\TaskCategory;
use Illuminate\Database\Seeder;

/**
 * Real reference data (like IssueCategorySeeder), not demo data — safe to
 * run on a production database. Admins can rename, recolor, deactivate or
 * add categories afterwards from the Task Categories page.
 */
class TaskCategorySeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private array $categories = [
        'maintenance' => ['Texnik xizmat ko\'rsatish', '#1E88E5'],
        'repair' => ['Ta\'mirlash ishlari', '#E53935'],
        'inspection' => ['Nazorat va tekshiruv', '#8E24AA'],
        'subscribers' => ['Abonentlar bilan ishlash', '#00897B'],
        'safety' => ['Mehnat muhofazasi va xavfsizlik', '#F4511E'],
        'documents' => ['Hujjatlar tayyorlash', '#6D4C41'],
        'reporting' => ['Hisobot topshirish', '#3949AB'],
        'meeting' => ['Yig\'ilish va kengash', '#7CB342'],
        'other' => ['Boshqa', '#757575'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $order = 0;

        foreach ($this->categories as $code => [$name, $color]) {
            TaskCategory::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'color' => $color, 'sort_order' => ++$order]
            );
        }
    }
}
