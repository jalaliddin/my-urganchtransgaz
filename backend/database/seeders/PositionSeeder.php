<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * @var string[]
     */
    private array $titles = [
        'Direktor' => 'DIR',
        'Direktor o\'rinbosari' => 'DIR-O',
        'Bo\'lim boshlig\'i' => 'BB',
        'Bosh mutaxassis' => 'BM',
        'Muhandis' => 'MUH',
        'Iqtisodchi' => 'IQT',
        'Elektromontyor' => 'ELM',
        'Operator' => 'OPR',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::query()->each(function (Organization $organization) {
            foreach ($this->titles as $title => $code) {
                Position::factory()->create([
                    'organization_id' => $organization->id,
                    'title' => $title,
                    'code' => "{$code}-{$organization->id}",
                ]);
            }
        });
    }
}
