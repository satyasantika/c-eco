<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ItemParameterImporter;
use Illuminate\Console\Command;
use RuntimeException;

class ImportParametersCommand extends Command
{
    protected $signature = 'cat:import-parameters
        {file : CSV berkolom '.'item_code,a,b,c,se_a,se_b,model,infit,outfit}
        {--run-label= : Catatan yang disimpan pada calibration_run}
        {--provisional : Tandai kalibrasi ini sebagai sementara}
        {--software=R/mirt : Perangkat lunak yang menghasilkan parameter}
        {--sample-n= : Jumlah responden pada kalibrasi ini}';

    protected $description = 'Impor parameter butir hasil kalibrasi sebagai versi baru (R5)';

    public function handle(ItemParameterImporter $importer): int
    {
        try {
            $rows = $importer->parseCsv((string) $this->argument('file'));

            $counts = $importer->store(
                rows: $rows,
                software: (string) $this->option('software'),
                label: (string) ($this->option('run-label') ?: 'impor '.now()->toDateTimeString()),
                isProvisional: (bool) $this->option('provisional'),
                sampleN: $this->option('sample-n') === null ? null : (int) $this->option('sample-n'),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d calibration_run dibuat, %d parameter baru aktif, %d versi lama dinonaktifkan.',
            $counts['runs'],
            $counts['parameters'],
            $counts['deactivated'],
        ));

        return self::SUCCESS;
    }
}
