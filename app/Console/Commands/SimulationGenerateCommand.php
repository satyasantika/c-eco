<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExamSimulation;
use App\Services\DemoSimulation;
use Illuminate\Console\Command;
use RuntimeException;

class SimulationGenerateCommand extends Command
{
    protected $signature = 'simulation:generate';

    protected $description = 'Buat gelombang simulasi demo (akun tiap peran + sesi tes); tidak berbuat apa-apa bila sudah aktif';

    public function handle(DemoSimulation $demo): int
    {
        try {
            ['simulation' => $simulation, 'created' => $created] = $demo->generate();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $created
            ? $this->info('Simulasi demo dibuat: '.$simulation->label().'.')
            : $this->warn('Simulasi demo sudah aktif sejak '.$simulation->created_at?->format('d M Y H:i').'. Tidak ada yang dibuat ulang.');

        self::printStatus($this, $demo, $simulation);

        return self::SUCCESS;
    }

    public static function printStatus(Command $command, DemoSimulation $demo, ExamSimulation $simulation): void
    {
        $status = $demo->status();
        $sessions = $status['sessions'];

        $command->line(sprintf(
            'Sesi: %d selesai, %d berjalan, %d menunggu. Akun: %d.',
            $sessions['completed'] ?? 0,
            $sessions['in_progress'] ?? 0,
            $sessions['pending'] ?? 0,
            $status['accounts'],
        ));

        $command->table(
            ['Peran', 'Email', 'Kata sandi'],
            collect($demo->accountsByRole($simulation))
                ->map(fn ($user): array => [$user->role->label(), $user->email, $simulation->plain_password])
                ->values()
                ->all(),
        );
    }
}
