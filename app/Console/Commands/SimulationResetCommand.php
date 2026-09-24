<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DemoSimulation;
use Illuminate\Console\Command;
use RuntimeException;

class SimulationResetCommand extends Command
{
    protected $signature = 'simulation:reset';

    protected $description = 'Hapus gelombang simulasi demo beserta akun, kursi, dan jawabannya; data asli tidak tersentuh';

    public function handle(DemoSimulation $demo): int
    {
        try {
            $removed = $demo->reset();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $removed === 0
            ? $this->info('Tidak ada simulasi demo aktif. Tidak ada yang dihapus.')
            : $this->info("Simulasi demo dihapus ({$removed} gelombang). Bank soal dan tes asli tidak berubah.");

        return self::SUCCESS;
    }
}
