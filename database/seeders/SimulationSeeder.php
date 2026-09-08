<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ItemBank;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\User;
use App\Services\TokenIssuer;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Akun panel + 20 siswa untuk uji di mesin lokal.
 *
 * Satu admin pusat, dua operator, sembilan pengawas (satu per rombongan
 * simulasi), satu peneliti. Admin saja yang boleh menambah/menonaktifkan
 * akun staf dari panel.
 */
class SimulationSeeder extends Seeder
{
    public const PASSWORD = 'ekonomi1234';

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

        for ($i = 1; $i <= 9; $i++) {
            $users[] = [
                'email' => sprintf('pengawas%02d@c-eco.test', $i),
                'name' => sprintf('Pengawas %02d', $i),
                'role' => UserRole::Pengawas,
            ];
        }

        return $users;
    }

    public function run(): void
    {
        $this->panelUsers();
        $this->students();
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

        $this->command?->info('Panel: 1 admin, 2 operator, 9 pengawas, 1 peneliti. Kata sandi '.self::PASSWORD);
    }

    public function students(int $n = 20): int
    {
        $school = School::query()->firstOrCreate(
            ['name' => 'SMA Simulasi C-ECO'],
            ['city' => 'Tasikmalaya'],
        );

        $bank = ItemBank::query()->where('grade', 'XI')->first()
            ?? ItemBank::query()->first();

        if ($bank === null) {
            throw new RuntimeException('Bank soal belum ada. Jalankan seeder butir dulu.');
        }

        $config = TestConfig::query()->where('item_bank_id', $bank->id)->first()
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

        $this->command?->info("Siswa: {$n} peserta XI-SIM, token terbit.");

        return $n;
    }
}
