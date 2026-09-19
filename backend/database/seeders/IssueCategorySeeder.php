<?php

namespace Database\Seeders;

use App\Models\IssueCategory;
use Illuminate\Database\Seeder;

/**
 * Real reference data (like DocumentTypeSeeder), not demo data — safe and
 * needed on a production database: the issue form requires a category.
 */
class IssueCategorySeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $categories = [
        'gas_leak' => 'Gaz sizib chiqishi',
        'pipeline_damage' => 'Gaz quvuri shikastlanishi',
        'low_pressure' => 'Gaz bosimi pasayishi',
        'supply_outage' => 'Gaz uzilishi',
        'meter_fault' => 'Hisoblagich nosozligi',
        'station_fault' => 'Nasos yoki taqsimlash stansiyasi nosozligi',
        'other' => 'Boshqa',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $order = 0;

        foreach ($this->categories as $code => $name) {
            IssueCategory::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => ++$order]
            );
        }
    }
}
