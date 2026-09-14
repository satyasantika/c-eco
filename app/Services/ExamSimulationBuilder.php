<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\ExamGroup;
use App\Models\ExamSimulation;
use App\Models\ItemBank;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyusun satu gelombang simulasi yang terpisah dari tes asli.
 *
 * Bank soal dan parameter tidak disentuh. Semua akun, sekolah, paket, dan
 * jadwal yang dibuat memakai exam_simulation_id supaya bisa dihapus utuh.
 */
class ExamSimulationBuilder
{
    public const MAX_STUDENTS = 300;

    public const MAX_ROOMS = 20;

    public const DEFAULT_PASSWORD = 'ekonomi1234';

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ExamSimulation
    {
        $payload = $this->validated($data);

        return DB::transaction(function () use ($payload): ExamSimulation {
            $simulation = ExamSimulation::query()->create($payload);
            $this->provision($simulation);

            return $simulation->fresh([
                'school',
                'testConfig.packageItems.itemBank',
                'examGroups.supervisor',
                'users',
            ]) ?? $simulation;
        });
    }

    public function reschedule(ExamSimulation $simulation, \DateTimeInterface|string $startsAt): ExamSimulation
    {
        if (! $simulation->exists) {
            throw new RuntimeException('Gelombang belum tersimpan.');
        }

        $when = Carbon::parse($startsAt);

        return DB::transaction(function () use ($simulation, $when): ExamSimulation {
            $simulation->forceFill(['starts_at' => $when])->save();

            ExamGroup::query()
                ->where('exam_simulation_id', $simulation->id)
                ->update(['starts_at' => $when]);

            return $simulation->refresh();
        });
    }

    public function provision(ExamSimulation $simulation): void
    {
        $school = School::query()->create([
            'name' => 'SMA Simulasi #'.$simulation->id,
            'city' => 'Tasikmalaya',
            'exam_simulation_id' => $simulation->id,
        ]);

        $config = $this->package($simulation);
        $pengawas = $this->accounts($simulation, UserRole::Pengawas, $simulation->pengawas_count, 'pw', 'Pengawas');
        $this->accounts($simulation, UserRole::Operator, $simulation->operators_count, 'op', 'Operator');

        $seats = self::seatPlan($simulation->students, $simulation->rooms);

        foreach ($seats as $i => $capacity) {
            $n = $i + 1;
            $group = ExamGroup::query()->create([
                'exam_simulation_id' => $simulation->id,
                'school_id' => $school->id,
                'name' => $simulation->name.' · kelas '.$n,
                'room' => 'S'.$simulation->id.'-R'.str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'starts_at' => $simulation->starts_at,
                'supervisor_id' => $pengawas[$i]->id,
                'test_config_id' => $config->id,
                'capacity' => $capacity,
                'notes' => 'Simulasi #'.$simulation->id.' — dihapus bersama gelombangnya, bukan tes asli.',
            ]);

            app(ExamGroupSeater::class)->fill($group);
        }
    }

    /**
     * @return list<int>
     */
    public static function seatPlan(int $students, int $rooms): array
    {
        if ($rooms < 1) {
            throw new RuntimeException('Jumlah kelas minimal 1.');
        }

        if ($students < $rooms) {
            throw new RuntimeException('Jumlah siswa tidak boleh lebih kecil dari jumlah kelas.');
        }

        $base = intdiv($students, $rooms);
        $rem = $students % $rooms;
        $plan = [];

        for ($i = 0; $i < $rooms; $i++) {
            $plan[] = $base + ($i < $rem ? 1 : 0);
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $students = (int) ($data['students'] ?? 0);
        $rooms = (int) ($data['rooms'] ?? 0);
        $operators = (int) ($data['operators_count'] ?? 0);
        $pengawas = (int) ($data['pengawas_count'] ?? 0);
        $shareX = (int) ($data['grade_share_x'] ?? 0);
        $shareXi = (int) ($data['grade_share_xi'] ?? 0);
        $shareXii = (int) ($data['grade_share_xii'] ?? 0);
        $pool = (int) ($data['pool_size'] ?? 0);
        $password = (string) ($data['plain_password'] ?? self::DEFAULT_PASSWORD);

        if ($name === '') {
            throw new RuntimeException('Nama simulasi wajib diisi.');
        }

        if ($students < 1 || $students > self::MAX_STUDENTS) {
            throw new RuntimeException('Jumlah siswa harus 1–'.self::MAX_STUDENTS.'.');
        }

        if ($rooms < 1 || $rooms > self::MAX_ROOMS) {
            throw new RuntimeException('Jumlah kelas harus 1–'.self::MAX_ROOMS.'.');
        }

        if ($students < $rooms) {
            throw new RuntimeException('Jumlah siswa tidak boleh lebih kecil dari jumlah kelas.');
        }

        if ($pengawas < $rooms) {
            throw new RuntimeException('Pengawas minimal sama dengan jumlah kelas (satu orang per ruang).');
        }

        if ($operators < 1 || $operators > 5) {
            throw new RuntimeException('Operator simulasi harus 1–5.');
        }

        if ($shareX + $shareXi + $shareXii !== 100) {
            throw new RuntimeException('Jumlah persen X + XI + XII harus 100.');
        }

        if ($pool < 1) {
            throw new RuntimeException('Jumlah butir paket minimal 1.');
        }

        $maxItems = (int) TestConfigProvisioner::adaptiveAttributes()['max_items'];
        if ($pool < $maxItems) {
            throw new RuntimeException("Jumlah butir paket tidak boleh lebih kecil dari maksimal butir tes ({$maxItems}).");
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('Kata sandi akun simulasi minimal 8 karakter.');
        }

        if ($data['starts_at'] === null || $data['starts_at'] === '') {
            throw new RuntimeException('Waktu mulai wajib diisi.');
        }

        return [
            'name' => $name,
            'students' => $students,
            'rooms' => $rooms,
            'starts_at' => $data['starts_at'],
            'grade_share_x' => $shareX,
            'grade_share_xi' => $shareXi,
            'grade_share_xii' => $shareXii,
            'pool_size' => $pool,
            'operators_count' => $operators,
            'pengawas_count' => $pengawas,
            'plain_password' => $password,
        ];
    }

    private function package(ExamSimulation $simulation): TestConfig
    {
        $bank = ItemBank::query()->where('grade', 'XI')->where('is_active', true)->first()
            ?? ItemBank::query()->where('grade', 'XI')->first()
            ?? throw new RuntimeException('Bank soal jenjang XI belum ada. Unggah atau seed butir dulu.');

        $config = TestConfig::query()->create(array_merge(TestConfigProvisioner::adaptiveAttributes(), [
            'exam_simulation_id' => $simulation->id,
            'item_bank_id' => $bank->id,
            'name' => 'Simulasi #'.$simulation->id.' · '.$simulation->name,
            'grade_share_x' => $simulation->grade_share_x,
            'grade_share_xi' => $simulation->grade_share_xi,
            'grade_share_xii' => $simulation->grade_share_xii,
            'pool_size' => $simulation->pool_size,
        ]));

        app(MixedPackageComposer::class)->apply($config);

        return $config;
    }

    /**
     * @return list<User>
     */
    private function accounts(ExamSimulation $simulation, UserRole $role, int $count, string $tag, string $label): array
    {
        $created = [];

        for ($i = 1; $i <= $count; $i++) {
            $created[] = User::query()->create([
                'exam_simulation_id' => $simulation->id,
                'name' => $label.' simulasi '.$simulation->id.'-'.$i,
                'email' => sprintf('sim%d.%s%02d@c-eco.test', $simulation->id, $tag, $i),
                'password' => $simulation->plain_password,
                'role' => $role,
                'is_active' => true,
            ]);
        }

        return $created;
    }
}
