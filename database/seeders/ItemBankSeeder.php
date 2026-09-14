<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ItemBankImporter;
use Illuminate\Database\Seeder;

class ItemBankSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('data/items-all.json');

        if (! is_file($path)) {
            $this->command?->warn(
                'Lewati bank soal: data/items-all.json tidak ada. Unggah lewat panel atau jalankan cat:seed-items setelah berkas disalin.',
            );

            return;
        }

        $counts = ItemBankImporter::fromDataDirectory()->import(force: false);

        $this->command?->info(sprintf(
            '%d dimensi, %d bank, %d butir, %d opsi dimuat; %d butir sudah ada dan tidak disentuh.',
            $counts['dimensions'],
            $counts['banks'],
            $counts['items'],
            $counts['options'],
            $counts['skipped'],
        ));
    }
}
