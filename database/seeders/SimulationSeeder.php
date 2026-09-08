<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ExamGroup;
use App\Models\ItemBank;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\User;
use App\Services\ExamGroupSeater;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use App\Services\TokenIssuer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Akun panel dan tiga jadwal simulasi QR (bisa hidup berdampingan).
 *
 * 20 siswa / 1 kelas, 100 / 4 kelas, 300 / 10 kelas. Tiap jadwal jam sendiri,
 * ruang sendiri, paket campuran seimbang X/XI/XII.
 */
class SimulationSeeder extends Seeder
{
    public const PASSWORD = 'ekonomi1234';

    public const SCHOOL_NAME = 'SMA Simulasi C-ECO';

    public const MIXED_PACKAGE_NAME = 'Campuran simulasi seimbang';

    public const GRADE_SHARE_X = 34;

    public const GRADE_SHARE_XI = 33;

    public const GRADE_SHARE_XII = 33;

    public const POOL_SIZE = 30;

    public const PENGAWAS_COUNT = 10;

    /** Jam jadwal 100 siswa (4 kelas). Dipakai uji yang merujuk gelombang itu. */
    public const STARTS_AT = '2026-09-21 07:30:00';

    /**
     * @var array<int, array{students: int, rooms: int, per_room: int, starts_at: string, room_prefix: string, label: string}>
     */
    public const PRESETS = [
        20 => [
            'students' => 20,
            'rooms' => 1,
            'per_room' => 20,
            'starts_at' => '2026-09-21 07:00:00',
            'room_prefix' => 'R. 20-',
            'label' => '20 siswa · 1 kelas',
        ],
        100 => [
            'students' => 100,
            'rooms' => 4,
            'per_room' => 25,
            'starts_at' => '2026-09-21 07:30:00',
            'room_prefix' => 'R. XI-',
            'label' => '100 siswa · 4 kelas',
        ],
        300 => [
            'students' => 300,
            'rooms' => 10,
            'per_room' => 30,
            'starts_at' => '2026-09-21 10:00:00',
            'room_prefix' => 'R. 300-',
            'label' => '300 siswa · 10 kelas',
        ],
    ];

    /**
     * @return list<array{email: string, name: string, role: UserRole}>
     */
    public static function panelAccounts(): array
    {
        $users = [
            ['email' => 'admin@c-eco.test', 'name' => 'Admin C-ECO', 'role' => UserRole::Admin],
            ['email' => 'operator1@c-eco.test', 'name' => 'Operator 1', 'role' => UserRole::Operator],
            ['email' => 'operator2@c-eco.test', 'name' => 'Operator 2', 'role' => UserRole::Operator],
            ['email' => 'peneliti@c-eco.test', 'name' => 'Peneliti C-ECO', 'role' => UserRole::Peneliti],
        ];

        for ($i = 1; $i <= self::PENGAWAS_COUNT; $i++) {
            $users[] = [
                'email' => sprintf('pengawas%02d@c-eco.test', $i),
                'name' => sprintf('Pengawas %02d', $i),
                'role' => UserRole::Pengawas,
            ];
        }

        return $users;
    }

    /** @return list<int> */
    public static function sizes(): array
    {
        return array_keys(self::PRESETS);
    }

    /**
     * @return array{students: int, rooms: int, per_room: int, starts_at: string, room_prefix: string, label: string}
     */
    public static function preset(int $size): array
    {
        return self::PRESETS[$size] ?? throw new RuntimeException(
            'Ukuran simulasi harus 20, 100, atau 300.'
        );
    }

    /**
     * @return list<array{class: string, room: string}>
     */
    public static function classroomsFor(int $size): array
    {
        $preset = self::preset($size);
        $pad = $preset['rooms'] >= 10 ? 2 : 1;
        $rows = [];

        for ($i = 1; $i <= $preset['rooms']; $i++) {
            $n = str_pad((string) $i, $pad, '0', STR_PAD_LEFT);
            $rows[] = [
                'class' => 'XI-'.$n,
                'room' => $preset['room_prefix'].$n,
            ];
        }

        return $rows;
    }

    public function run(): void
    {
        $this->panelUsers();
        $this->scheduleAll();
    }

    public function panelUsers(): void
    {
        foreach (self::panelAccounts() as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => self::PASSWORD,
                    'role' => $user['role'],
                    'is_active' => true,
                ],
            );
        }

        User::query()
            ->whereIn('email', ['operator@c-eco.test', 'pengawas@c-eco.test'])
            ->update(['is_active' => false]);

        $this->command?->info('Panel: 1 admin, 2 operator, '.self::PENGAWAS_COUNT.' pengawas, 1 peneliti. Kata sandi '.self::PASSWORD);
    }

    public function students(int $n = 20): int
    {
        $school = $this->school();
        $bank = $this->bank('XI');
        $config = TestConfig::query()->where('item_bank_id', $bank->id)->where('name', 'like', 'Adaptif%')->first()
            ?? throw new RuntimeException("Tidak ada test_config untuk jenjang {$bank->grade}.");

        $ids = [];

        for ($i = 1; $i <= $n; $i++) {
            $ids[] = Participant::query()->firstOrCreate(
                ['school_id' => $school->id, 'student_code' => sprintf('SIM%02d', $i)],
                ['class_name' => 'XI-SIM', 'display_name' => sprintf('Siswa Simulasi %02d', $i)],
            )->id;
        }

        $participants = Participant::query()->whereIn('id', $ids)->orderBy('student_code')->get();

        app(TokenIssuer::class)->issue($participants, $config);

        $this->command?->info("Slip kertas: {$n} peserta XI-SIM (jalur mundur).");

        return $n;
    }

    /**
     * @return Collection<int, ExamGroup>
     */
    public function scheduleAll(): Collection
    {
        return collect(self::sizes())
            ->map(fn (int $size): Collection => $this->schedule($size))
            ->flatten();
    }

    /**
     * Satu gelombang: jam sama, ruang berbeda, paket campuran X/XI/XII.
     *
     * @return Collection<int, ExamGroup>
     */
    public function schedule(int $size): Collection
    {
        $preset = self::preset($size);

        $pengawas = User::query()
            ->where('role', UserRole::Pengawas)
            ->where('is_active', true)
            ->orderBy('email')
            ->get();

        if ($pengawas->count() < $preset['rooms']) {
            throw new RuntimeException("Butuh {$preset['rooms']} pengawas aktif, hanya ada {$pengawas->count()}.");
        }

        $school = $this->school();
        $config = $this->balancedPackage();
        $startsAt = Carbon::parse($preset['starts_at']);
        $groups = collect();

        foreach (self::classroomsFor($size) as $i => $row) {
            $group = ExamGroup::query()->firstOrCreate(
                [
                    'school_id' => $school->id,
                    'room' => $row['room'],
                    'starts_at' => $startsAt,
                ],
                [
                    'name' => $preset['students'].' · '.$row['class'],
                    'supervisor_id' => $pengawas[$i]->id,
                    'test_config_id' => $config->id,
                    'capacity' => $preset['per_room'],
                    'notes' => $preset['label'].' · paket campuran seimbang X/XI/XII.',
                ],
            );

            $group->forceFill([
                'name' => $preset['students'].' · '.$row['class'],
                'supervisor_id' => $pengawas[$i]->id,
                'test_config_id' => $config->id,
                'capacity' => $preset['per_room'],
                'notes' => $preset['label'].' · paket campuran seimbang X/XI/XII.',
            ])->save();

            app(ExamGroupSeater::class)->fill($group);
            $groups->push($group->fresh(['supervisor', 'testConfig', 'school']));
        }

        $seats = $groups->sum(fn (ExamGroup $g): int => $g->seatCount());
        $this->command?->info("{$preset['label']}: {$groups->count()} ruang pada {$startsAt->format('d M Y H:i')}, {$seats} kursi QR.");

        return $groups;
    }

    public function balancedPackage(): TestConfig
    {
        $bank = $this->bank('XI');

        $config = TestConfig::query()->firstOrCreate(
            ['name' => self::MIXED_PACKAGE_NAME],
            array_merge(TestConfigProvisioner::adaptiveAttributes(), [
                'item_bank_id' => $bank->id,
                'grade_share_x' => self::GRADE_SHARE_X,
                'grade_share_xi' => self::GRADE_SHARE_XI,
                'grade_share_xii' => self::GRADE_SHARE_XII,
                'pool_size' => self::POOL_SIZE,
            ]),
        );

        if (! $config->usesGradeShares()) {
            $config->forceFill([
                'grade_share_x' => self::GRADE_SHARE_X,
                'grade_share_xi' => self::GRADE_SHARE_XI,
                'grade_share_xii' => self::GRADE_SHARE_XII,
                'pool_size' => self::POOL_SIZE,
                'is_active' => true,
            ])->save();
        }

        if (! $config->packageItems()->exists()) {
            app(MixedPackageComposer::class)->apply($config->fresh() ?? $config);
        }

        return $config->fresh(['packageItems.itemBank']) ?? $config;
    }

    private function school(): School
    {
        return School::query()->firstOrCreate(
            ['name' => self::SCHOOL_NAME],
            ['city' => 'Tasikmalaya'],
        );
    }

    private function bank(string $grade): ItemBank
    {
        $bank = ItemBank::query()->where('grade', $grade)->where('is_active', true)->first()
            ?? ItemBank::query()->where('grade', $grade)->first();

        if ($bank === null) {
            throw new RuntimeException('Bank soal belum ada. Jalankan seeder butir dulu.');
        }

        return $bank;
    }
}
