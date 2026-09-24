<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExamGroup;
use App\Models\ExamSimulation;
use App\Models\TestSession;
use App\Services\DemoSimulation;
use App\Support\UserManual;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Memotret ulang semua tangkapan layar manual dari aplikasi yang berjalan.
 *
 * PHP menyiapkan job (akun demo, URL yang sudah diisi); Playwright di Node
 * yang memotret. Di kontainer PHP tanpa Node, job disiapkan lalu perintah
 * Node untuk host dicetak.
 */
class CaptureManualScreenshotsCommand extends Command
{
    protected $signature = 'manual:capture-screenshots
                            {--base-url= : URL aplikasi yang bisa dibuka browser (bawaan: APP_URL)}
                            {--node=node : Biner Node yang menjalankan Playwright}
                            {--prepare-only : Hanya tulis job; jalankan Node sendiri di host}
                            {--reset-after : Hapus simulasi demo setelah selesai memotret}';

    protected $description = 'Ambil ulang tangkapan layar manual pengguna (Playwright) memakai akun simulasi demo';

    public const JOB = 'storage/app/manual-capture.json';

    public function handle(DemoSimulation $demo): int
    {
        try {
            ['simulation' => $simulation, 'created' => $created] = $demo->generate();
        } catch (RuntimeException $e) {
            $this->error('Simulasi demo diperlukan untuk memotret: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($created) {
            $this->info('Simulasi demo dibuat untuk pemotretan.');
        }

        try {
            $job = $this->job($demo, $simulation);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        File::put(base_path(self::JOB), json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod(base_path(self::JOB), 0600);

        $command = ['node', 'scripts/capture-manual.mjs', self::JOB];
        $nodeFound = Process::run([(string) $this->option('node'), '--version'])->successful();

        if ($this->option('prepare-only') || ! $nodeFound) {
            if (! $nodeFound) {
                $this->warn('Node tidak ditemukan di lingkungan ini (misalnya kontainer PHP).');
            }
            $this->line('Job tersimpan. Jalankan di mesin yang punya Node + Playwright, dari akar proyek:');
            $this->line('  '.implode(' ', $command));

            return self::SUCCESS;
        }

        $command[0] = (string) $this->option('node');
        $result = Process::path(base_path())
            ->timeout(600)
            ->run($command, fn (string $type, string $output) => $this->output->write($output));

        if ($this->option('reset-after')) {
            $demo->reset();
            $this->info('Simulasi demo dihapus.');
        }

        return $result->successful() ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, mixed> */
    private function job(DemoSimulation $demo, ExamSimulation $simulation): array
    {
        $groups = $simulation->examGroups()->orderBy('room')->get();
        $groupIds = $groups->pluck('id');
        $accounts = $demo->accountsByRole($simulation);

        // Kursi yang dipakai alur siswa diambil dari ujung antrean, supaya
        // Kartu QR pengawas tetap menampilkan kursi pertama yang belum terbuka.
        $seat = TestSession::query()
            ->whereIn('exam_group_id', $groupIds)
            ->whereNull('claimed_at')
            ->whereNull('opened_at')
            ->orderByDesc('id')
            ->value('access_token')
            ?? throw new RuntimeException('Tidak ada kursi kosong di simulasi demo. Jalankan simulation:reset lalu ulangi.');

        $done = TestSession::query()
            ->whereIn('exam_group_id', $groupIds)
            ->where('status', 'completed')
            ->orderBy('id')
            ->value('access_token')
            ?? throw new RuntimeException('Simulasi demo belum punya sesi selesai.');

        $pengawas = $accounts['pengawas'] ?? null;
        /** @var ExamGroup $qrGroup */
        $qrGroup = $groups->firstWhere('supervisor_id', $pengawas?->id) ?? $groups->first();

        $replace = [
            '{demo}' => '/admin/exam-simulations/'.$simulation->id,
            '{qr}' => '/awas/'.$qrGroup->id,
            '{token_seat}' => $seat,
            '{token_done}' => $done,
            // Nomor induk harus baru setiap kali: kursi demo menolak nomor yang sudah dipakai.
            '{student_code}' => 'MANUAL-'.now()->format('His'),
        ];

        $fill = fn (mixed $value): mixed => is_string($value) ? strtr($value, $replace) : $value;

        $shots = array_map(function (array $shot) use ($fill): array {
            $shot['path'] = isset($shot['path']) ? $fill($shot['path']) : null;
            $shot['steps'] = array_map(fn (array $step): array => array_map($fill, $step), $shot['steps'] ?? []);

            return $shot;
        }, UserManual::shots());

        return [
            'baseUrl' => rtrim((string) ($this->option('base-url') ?: config('app.url')), '/'),
            'outDir' => 'public/'.UserManual::DIRECTORY,
            'accounts' => collect($accounts)
                ->map(fn ($user): array => ['email' => $user->email, 'password' => $simulation->plain_password])
                ->all(),
            'shots' => $shots,
        ];
    }
}
