<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExamGroup;
use Database\Seeders\SimulationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SeedSimulationCommand extends Command
{
    protected $signature = 'cat:seed-simulation
                            {size=all : 20, 100, 300, atau all}
                            {--slips=0 : Jumlah siswa slip kertas (jalur mundur)}';

    protected $description = 'Akun panel + paket campuran seimbang + jadwal simulasi 20/1, 100/4, atau 300/10';

    public function handle(): int
    {
        $size = strtolower((string) $this->argument('size'));
        $allowed = array_merge(['all'], array_map('strval', SimulationSeeder::sizes()));

        if (! in_array($size, $allowed, true)) {
            $this->error('Ukuran harus 20, 100, 300, atau all.');

            return self::INVALID;
        }

        $seeder = new SimulationSeeder;
        $seeder->setCommand($this);
        $seeder->panelUsers();

        $slips = (int) $this->option('slips');
        if ($slips > 0) {
            $seeder->students($slips);
        }

        /** @var Collection<int, ExamGroup> $groups */
        $groups = $size === 'all'
            ? $seeder->scheduleAll()
            : $seeder->schedule((int) $size);

        $base = rtrim((string) config('app.url'), '/');

        $this->newLine();
        $this->info('Masuk panel: '.$base.'/admin');
        $this->table(
            ['Peran', 'Email', 'Kata sandi'],
            collect(SimulationSeeder::panelAccounts())->map(fn (array $u): array => [
                $u['role']->label(),
                $u['email'],
                SimulationSeeder::PASSWORD,
            ])->all(),
        );

        if ($groups->isEmpty()) {
            return self::SUCCESS;
        }

        $byClock = $groups->groupBy(fn (ExamGroup $g): string => $g->starts_at?->format('Y-m-d H:i') ?? '');

        foreach ($byClock as $clock => $wave) {
            $this->newLine();
            $this->info('Kartu QR · '.$clock.' · '.$wave->count().' ruang · '.$wave->sum(fn (ExamGroup $g): int => $g->seatCount()).' kursi');
            $this->table(
                ['Kelas', 'Ruang', 'Pengawas', 'Kursi', 'Kartu QR'],
                $wave->map(function (ExamGroup $group) use ($base): array {
                    return [
                        $group->name,
                        $group->room,
                        $group->supervisor?->email,
                        $group->seatCount().' / '.$group->capacity,
                        $base.route('proctor.qr', $group, false),
                    ];
                })->all(),
            );
        }

        return self::SUCCESS;
    }
}
