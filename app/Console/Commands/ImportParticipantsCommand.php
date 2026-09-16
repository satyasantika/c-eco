<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ParticipantImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportParticipantsCommand extends Command
{
    protected $signature = 'cat:import-participants
        {file : CSV berkolom school,class_name,student_code,display_name}
        {--city= : Kota untuk sekolah yang baru dibuat}';

    protected $description = 'Mengimpor daftar peserta dari CSV';

    public function handle(ParticipantImporter $importer): int
    {
        try {
            $counts = $importer->importFile(
                (string) $this->argument('file'),
                $this->option('city') !== null ? (string) $this->option('city') : null,
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$counts['created']} peserta baru, {$counts['skipped']} sudah ada.");

        return self::SUCCESS;
    }
}
