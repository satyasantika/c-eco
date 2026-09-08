<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ItemBankImporter;
use Illuminate\Database\Seeder;

class ItemBankSeeder extends Seeder
{
    public function run(): void
    {
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
