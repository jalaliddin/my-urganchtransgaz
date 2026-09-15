<?php

namespace Database\Seeders;

use App\Enums\OrganizationType;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var string[]
     */
    private array $centralDepartments = [
        'Kadrlar bo\'limi' => 'HR',
        'Ishlab chiqarish-texnika bo\'limi' => 'ITB',
        'Mehnat muhofazasi va sanoat xavfsizligi bo\'limi' => 'MMSX',
        'Texnik siyosat xizmati' => 'TPS',
        'Buxgalteriya' => 'BUX',
        'Axborot texnologiyalari bo\'limi' => 'IT',
    ];

    /**
     * @var string[]
     */
    private array $branchDepartments = [
        'Ma\'muriy bo\'lim' => 'ADM',
        'Texnik xizmat guruhi' => 'TEX',
        'Hisob-kitob guruhi' => 'HK',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::query()->each(function (Organization $organization) {
            $departments = $organization->type === OrganizationType::Central
                ? $this->centralDepartments
                : $this->branchDepartments;

            foreach ($departments as $name => $code) {
                Department::factory()->create([
                    'organization_id' => $organization->id,
                    'name' => $name,
                    'code' => $code,
                ]);
            }
        });
    }
}
