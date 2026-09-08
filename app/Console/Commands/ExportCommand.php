<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DataExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportCommand extends Command
{
    protected $signature = 'cat:export
        {--config= : Batasi ke satu test_config}
        {--completed-only : Hanya sesi yang sudah selesai}
        {--dir=exports : Folder tujuan di disk local}';

    protected $description = 'Mengekspor sesi, butir tersaji, peserta, dan peristiwa ke CSV untuk analisis di R';

    public function handle(DataExporter $exporter): int
    {
        try {
            $result = $exporter->export(
                configId: $this->option('config') === null ? null : (int) $this->option('config'),
                completedOnly: (bool) $this->option('completed-only'),
                baseDirectory: (string) $this->option('dir'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Ekspor selesai: '.Storage::disk('local')->path($result['directory']));

        foreach ($result['files'] as $name => $rows) {
            $this->line(sprintf('  %-20s %s baris', $name, $rows));
        }

        return self::SUCCESS;
    }
}
