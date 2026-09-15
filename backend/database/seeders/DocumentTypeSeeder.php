<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * @var array<string, array{0: string, 1: bool}>
     */
    private array $types = [
        'Pasport' => ['PASSPORT', true],
        'ID karta' => ['ID_CARD', true],
        'Diplom' => ['DIPLOMA', false],
        'Sertifikat' => ['CERTIFICATE', true],
        'Ishga qabul hujjati' => ['EMPLOYMENT_DOC', false],
        'Malaka sertifikati' => ['QUALIFICATION_CERT', true],
        'Tibbiy ma\'lumotnoma' => ['MEDICAL_DOC', true],
        'Xavfsizlik texnikasi sertifikati' => ['SAFETY_CERT', true],
        'O\'quv sertifikati' => ['TRAINING_CERT', true],
        'Boshqa' => ['OTHER', false],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->types as $name => [$code, $requiresExpiry]) {
            DocumentType::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'requires_expiry' => $requiresExpiry]
            );
        }
    }
}
