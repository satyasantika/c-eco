<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ItemBankImporter;
use Illuminate\Console\Command;
use RuntimeException;

class SeedItemsCommand extends Command
{
    protected $signature = 'cat:seed-items
        {--force : Timpa butir dan opsi yang sudah ada dengan isi data/items-all.json}';

    protected $description = 'Memuat bank soal dari data/items-all.json (tidak menimpa butir yang sudah ada)';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if ($force) {
            $this->warn('--force aktif: perbaikan manual lewat Filament akan ditimpa isi JSON.');
        }

        try {
            $counts = ItemBankImporter::fromDataDirectory()->import($force);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d dimensi, %d bank, %d butir, %d opsi dimuat; %d butir sudah ada dan tidak disentuh.',
            $counts['dimensions'],
            $counts['banks'],
            $counts['items'],
            $counts['options'],
            $counts['skipped'],
        ));

        return self::SUCCESS;
    }
}
