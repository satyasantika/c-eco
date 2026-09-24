<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DemoSimulation;
use Illuminate\Console\Command;

class SimulationStatusCommand extends Command
{
    protected $signature = 'simulation:status';

    protected $description = 'Tampilkan apakah simulasi demo sedang aktif, beserta akun dan sesinya';

    public function handle(DemoSimulation $demo): int
    {
        $simulation = $demo->active();

        if ($simulation === null) {
            $this->info('Simulasi demo: tidak aktif.');

            return self::SUCCESS;
        }

        $this->info('Simulasi demo: aktif sejak '.$simulation->created_at?->format('d M Y H:i').' — '.$simulation->label().'.');
        SimulationGenerateCommand::printStatus($this, $demo, $simulation);

        return self::SUCCESS;
    }
}
