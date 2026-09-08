<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TestSession;
use Database\Seeders\SimulationSeeder;
use Illuminate\Console\Command;

class SeedSimulationCommand extends Command
{
    protected $signature = 'cat:seed-simulation {--students=20 : Jumlah siswa}';

    protected $description = 'Membuat akun panel (semua peran) dan siswa simulasi beserta token';

    public function handle(): int
    {
        $seeder = new SimulationSeeder;
        $seeder->setCommand($this);
        $seeder->panelUsers();
        $seeder->students((int) $this->option('students'));

        $base = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->info('Masuk panel: '.$base.'/admin');
        $this->table(
            ['Peran', 'Email', 'Kata sandi'],
            collect(SimulationSeeder::PANEL_USERS)->map(fn (array $u): array => [
                explode('@', $u['email'])[0],
                $u['email'],
                SimulationSeeder::PASSWORD,
            ])->all(),
        );

        $rows = TestSession::query()
            ->whereHas('participant.school', fn ($q) => $q->where('name', 'SMA Simulasi C-ECO'))
            ->with('participant')
            ->orderBy('id')
            ->get()
            ->map(fn (TestSession $s): array => [
                $s->participant->student_code,
                $s->participant->display_name,
                $s->access_token,
                $base.'/t/'.$s->access_token,
            ]);

        $this->table(['Kode', 'Nama', 'Token', 'Tautan'], $rows->all());

        return self::SUCCESS;
    }
}
