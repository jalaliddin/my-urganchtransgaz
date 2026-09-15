<?php

namespace Database\Seeders;

use App\Enums\ActiveStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Seeder;

/**
 * Seeds the demo organizational structure: one central office plus a
 * configurable number of subordinate branches. The count below (14) is
 * seed data only; nothing in the schema limits the number of organizations.
 */
class OrganizationSeeder extends Seeder
{
    /**
     * @var string[]
     */
    private array $subordinateNames = [
        'Urganch shahar gaz ta\'minoti boshqarmasi',
        'Urganch tuman gaz tarmoqlari boshqarmasi',
        'Xiva shahar gaz ta\'minoti boshqarmasi',
        'Xiva tuman gaz tarmoqlari boshqarmasi',
        'Xonqa tuman gaz tarmoqlari boshqarmasi',
        'Bog\'ot tuman gaz tarmoqlari boshqarmasi',
        'Gurlan tuman gaz tarmoqlari boshqarmasi',
        'Qo\'shko\'pir tuman gaz tarmoqlari boshqarmasi',
        'Shovot tuman gaz tarmoqlari boshqarmasi',
        'Xazorasp tuman gaz tarmoqlari boshqarmasi',
        'Yangiariq tuman gaz tarmoqlari boshqarmasi',
        'Yangibozor tuman gaz tarmoqlari boshqarmasi',
        'Tuproqqal\'a tuman gaz tarmoqlari boshqarmasi',
        'Mexanika-avtotransport va ta\'mirlash xizmati',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $central = Organization::factory()->central()->create([
            'name' => '"Urganchtransgaz" MCHJ — Bosh ofis',
            'short_name' => 'Bosh ofis',
            'code' => 'UTG-000',
            'director_name' => 'Bosh direktor',
            'email' => 'info@urtg.uz',
        ]);

        foreach ($this->subordinateNames as $index => $name) {
            Organization::factory()->create([
                'parent_id' => $central->id,
                'name' => $name,
                'short_name' => 'Filial '.($index + 1),
                'code' => sprintf('UTG-%03d', $index + 1),
                'type' => OrganizationType::Subordinate,
                'status' => ActiveStatus::Active,
            ]);
        }
    }
}
