<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\ResponseModel;
use App\Enums\UserRole;
use App\Models\ExamGroup;
use App\Models\ExamSimulation;
use App\Models\SessionItem;
use App\Models\TestSession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu gelombang demo untuk latihan dan manual pengguna.
 *
 * Dibangun di atas ExamSimulationBuilder, jadi setiap baris yang dibuat
 * (akun, sekolah, paket, rombongan, kursi, jawaban) terikat ke satu
 * exam_simulation_id dan dihapus ExamSimulationPurger. Bank soal dan
 * parameter hanya dibaca. Tidak pernah ada dua gelombang demo sekaligus.
 */
class DemoSimulation
{
    public const NAME = 'Simulasi demo';

    public const STUDENTS = 24;

    public const ROOMS = 2;

    /**
     * Kursi ruang tambahan yang dijadwalkan besok pagi: contoh slip QR yang
     * dicetak sebelum jadwal dan layar "Tes belum dimulai" di HP siswa.
     */
    public const SCHEDULED_SEATS = 4;

    /** Porsi kursi per ruang: selesai, sedang mengerjakan, sisanya belum dipindai. */
    public const COMPLETED_SHARE = 0.5;

    public const IN_PROGRESS_SHARE = 0.25;

    private const LOCK = 'demo-simulation';

    private const NAMES = [
        'Aditya Pratama', 'Nurul Aini', 'Rizky Maulana', 'Siti Rahmawati', 'Fajar Nugraha',
        'Dewi Lestari', 'Ilham Ramadhan', 'Putri Ayu', 'Galih Saputra', 'Intan Permata',
        'Yusuf Hidayat', 'Rina Oktaviani', 'Bayu Firmansyah', 'Anisa Fitri', 'Dimas Kurniawan',
        'Salsa Nabila', 'Reza Mahendra', 'Wulan Sari', 'Agus Setiawan', 'Laras Kinanti',
    ];

    public function __construct(
        private readonly ExamSimulationBuilder $builder,
        private readonly SessionClaimer $claimer,
        private readonly CatSession $cat,
    ) {}

    public function active(): ?ExamSimulation
    {
        return ExamSimulation::query()->demo()->latest('id')->first();
    }

    /**
     * Idempoten: bila demo sudah aktif, gelombang itu dikembalikan apa adanya.
     *
     * @return array{simulation: ExamSimulation, created: bool}
     */
    public function generate(): array
    {
        return $this->locked(function (): array {
            $existing = $this->active();

            if ($existing !== null) {
                return ['simulation' => $existing, 'created' => false];
            }

            $simulation = DB::transaction(function (): ExamSimulation {
                $maxItems = (int) TestConfigProvisioner::adaptiveAttributes()['max_items'];

                $simulation = $this->builder->create([
                    'name' => self::NAME,
                    'students' => self::STUDENTS,
                    'rooms' => self::ROOMS,
                    // Sudah dimulai, supaya kartu QR, monitor, dan ekspor punya isi.
                    'starts_at' => Carbon::now()->subMinutes(30),
                    'grade_share_x' => 34,
                    'grade_share_xi' => 33,
                    'grade_share_xii' => 33,
                    'pool_size' => $maxItems * 2,
                    'operators_count' => 1,
                    'pengawas_count' => self::ROOMS,
                    // Sandi acak per gelombang: akun demo termasuk admin, jangan
                    // pernah memakai sandi yang bisa ditebak dari kode sumber.
                    'plain_password' => Str::password(14, symbols: false),
                ]);

                $simulation->forceFill(['is_demo' => true])->save();

                $this->builder->accounts($simulation, UserRole::Admin, 1, 'ad', 'Admin');
                $this->builder->accounts($simulation, UserRole::Peneliti, 1, 'pn', 'Peneliti');

                $this->playSessions($simulation);
                $this->scheduleRoom($simulation);

                return $simulation;
            });

            return ['simulation' => $simulation->fresh() ?? $simulation, 'created' => true];
        });
    }

    /**
     * Menghapus semua gelombang demo. Gelombang tulisan admin tidak tersentuh.
     *
     * @return int jumlah gelombang demo yang dihapus
     */
    public function reset(): int
    {
        return $this->locked(function (): int {
            $waves = ExamSimulation::query()->demo()->get();

            foreach ($waves as $wave) {
                $wave->delete();
            }

            return $waves->count();
        });
    }

    /**
     * @return array{active: bool, simulation: ?ExamSimulation, accounts: int, sessions: array<string, int>}
     */
    public function status(): array
    {
        $simulation = $this->active();

        if ($simulation === null) {
            return ['active' => false, 'simulation' => null, 'accounts' => 0, 'sessions' => []];
        }

        $sessions = TestSession::query()
            ->whereIn('exam_group_id', $simulation->examGroups()->select('id'))
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->map(fn ($n): int => (int) $n)
            ->all();

        return [
            'active' => true,
            'simulation' => $simulation,
            'accounts' => $simulation->users()->count(),
            'sessions' => $sessions,
        ];
    }

    /**
     * Satu akun per peran, untuk masuk panel (manual, latihan).
     *
     * @return array<string, User>
     */
    public function accountsByRole(ExamSimulation $simulation): array
    {
        return $simulation->users()
            ->orderBy('email')
            ->get()
            ->unique(fn (User $user): string => $user->role->value)
            ->mapWithKeys(fn (User $user): array => [$user->role->value => $user])
            ->all();
    }

    /**
     * Ruang yang belum dimulai (besok 07.30): slipnya boleh dicetak sekarang,
     * tetapi siswa yang memindai hanya melihat "Tes belum dimulai".
     */
    private function scheduleRoom(ExamSimulation $simulation): void
    {
        $template = $simulation->examGroups()->orderBy('room')->firstOrFail();
        $n = $simulation->examGroups()->count() + 1;
        $tz = (string) config('app.timezone');

        $group = ExamGroup::query()->create([
            'exam_simulation_id' => $simulation->id,
            'school_id' => $template->school_id,
            'name' => $simulation->name.' · kelas '.$n.' (terjadwal)',
            'room' => 'S'.$simulation->id.'-R'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'starts_at' => Carbon::tomorrow($tz)->setTime(7, 30),
            'supervisor_id' => $template->supervisor_id,
            'test_config_id' => $template->test_config_id,
            'capacity' => self::SCHEDULED_SEATS,
            'notes' => 'Simulasi #'.$simulation->id.' — ruang terjadwal untuk contoh slip QR, bukan tes asli.',
        ]);

        app(ExamGroupSeater::class)->fill($group);
    }

    /**
     * Mengisi kursi seperti hari-H: sebagian selesai, sebagian di tengah
     * jalan, sisanya menunggu dipindai. Jawaban melewati CatSession asli
     * sehingga session_items, theta, dan eksposur terbentuk seperti tes nyata.
     */
    private function playSessions(ExamSimulation $simulation): void
    {
        $counter = 0;

        foreach ($simulation->examGroups()->orderBy('room')->get() as $group) {
            /** @var Collection<int, TestSession> $seats */
            $seats = $group->testSessions()->orderBy('id')->get();
            $completed = (int) round($seats->count() * self::COMPLETED_SHARE);
            $inProgress = (int) round($seats->count() * self::IN_PROGRESS_SHARE);

            foreach ($seats->take($completed + $inProgress)->values() as $i => $seat) {
                $counter++;
                $grade = ['X', 'XI', 'XII'][$counter % 3];

                $seat = $this->claimer->claim($seat, [
                    'student_code' => sprintf('DEMO%d-%03d', $simulation->id, $counter),
                    'display_name' => self::NAMES[($counter - 1) % count(self::NAMES)],
                    'grade' => $grade,
                    'class_name' => $grade.' IPS '.(($counter % 2) + 1),
                ]);
                $seat->participant->forceFill(['consent_at' => Carbon::now()])->save();
                // Siswa yang sedang mengerjakan terkunci ke satu HP, seperti di lapangan,
                // supaya pengawas bisa berlatih "Izinkan pindah HP". Kursi selesai
                // dibiarkan terbuka agar halaman hasilnya bisa dipotret untuk manual.
                if ($i >= $completed) {
                    $seat->forceFill(['resume_token' => bin2hex(random_bytes(16))])->save();
                }

                $this->answer($seat, $i < $completed ? null : random_int(3, 7));
            }
        }
    }

    /** @param  int|null  $limit  null = sampai aturan berhenti menutup sesi */
    private function answer(TestSession $session, ?int $limit): void
    {
        $theta = $this->normal();
        $meta = [
            'device_uuid' => (string) Str::uuid(),
            'user_agent' => 'Mozilla/5.0 (Linux; Android 13) C-ECO demo',
            'effective_connection' => ['4g', '4g', '4g', '3g'][random_int(0, 3)],
        ];

        $state = $this->cat->start($session, $meta);
        $answered = 0;

        while (! $state->isFinished() && $state->item !== null && ($limit === null || $answered < $limit)) {
            /** @var SessionItem $sessionItem */
            $sessionItem = $state->session->sessionItems()
                ->where('sequence', $state->item->sequence)
                ->with(['item.options', 'itemParameter'])
                ->firstOrFail();

            $label = $this->respond($sessionItem, $theta);
            $state = $this->cat->answer($session, $state->item->sequence, $label, $meta + [
                'latency_ms' => random_int(12_000, 75_000),
            ]);

            if (++$answered > 200) {
                throw new RuntimeException('Sesi demo tidak berhenti; periksa aturan berhenti paket.');
            }
        }
    }

    /** Label tampilan yang dipilih siswa berkemampuan $theta, mengikuti model IRT butir itu. */
    private function respond(SessionItem $sessionItem, float $theta): string
    {
        $permutation = $sessionItem->option_permutation_json;
        $key = $sessionItem->item->options->firstWhere('is_key', true)?->label;
        $keyDisplay = $key === null ? false : array_search($key, $permutation, true);

        $p = (new ResponseModel($sessionItem->itemParameter->toCat()))->probability($theta);

        if ($keyDisplay !== false && mt_rand() / mt_getrandmax() < $p) {
            return (string) $keyDisplay;
        }

        $wrong = array_values(array_filter(
            array_keys($permutation),
            fn ($label): bool => (string) $label !== (string) $keyDisplay,
        ));

        return (string) $wrong[array_rand($wrong)];
    }

    /** Box–Muller: kemampuan siswa demo ~ N(0, 1). */
    private function normal(): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return sqrt(-2.0 * log($u)) * cos(2.0 * M_PI * $v);
    }

    /**
     * Dua klik tombol yang berdekatan, atau CLI dan panel bersamaan, tidak
     * boleh menghasilkan dua gelombang demo.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function locked(callable $callback): mixed
    {
        $lock = Cache::lock(self::LOCK, 300);

        if (! $lock->get()) {
            throw new RuntimeException('Simulasi demo sedang dibuat atau dihapus. Coba lagi sebentar lagi.');
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }
}
