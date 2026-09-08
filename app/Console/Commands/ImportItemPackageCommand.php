<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ItemPackageImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportItemPackageCommand extends Command
{
    protected $signature = 'cat:import-item-package
        {file : JSON paket soal (bentuk data/items-all.json atau array butir)}
        {--grade= : Jenjang X, XI, atau XII}
        {--package-version= : Versi paket, unik bersama jenjang}
        {--no-provisional : Jangan membuat parameter sementara}';

    protected $description = 'Mengimpor paket soal baru dari JSON tanpa menimpa butir yang sudah ada';

    public function handle(ItemPackageImporter $importer): int
    {
        $grade = strtoupper((string) $this->option('grade'));
        $version = trim((string) $this->option('package-version'));

        if ($grade === '') {
            $this->error('Opsi --grade wajib (X, XI, atau XII).');

            return self::FAILURE;
        }

        if ($version === '') {
            $this->error('Opsi --package-version wajib, misalnya 2026.2');

            return self::FAILURE;
        }

        try {
            $counts = $importer->importFromFile(
                path: (string) $this->argument('file'),
                grade: $grade,
                version: $version,
                provisional: ! (bool) $this->option('no-provisional'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s paket %s · %s: %d butir, %d opsi, %d parameter sementara, %d konfigurasi tes.',
            $counts['created'] ? 'Paket baru' : 'Ditambah ke',
            $counts['bank']->grade,
            $counts['bank']->version,
            $counts['items'],
            $counts['options'],
            $counts['parameters'],
            $counts['configs'],
        ));

        return self::SUCCESS;
    }
}
