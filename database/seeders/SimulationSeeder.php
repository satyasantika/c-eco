<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemBank;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\User;
use App\Services\TokenIssuer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Akun panel + 20 siswa untuk uji di mesin lokal.
 *
 * Panel tidak membedakan izin per peran: keempat akun masuk ke /admin yang
 * sama. Yang berbeda hanya emailnya, supaya manual peran bisa dicoba tanpa
 * saling menimpa sesi browser.
 */
class SimulationSeeder extends Seeder
{
    public const PASSWORD = 'ekonomi1234';

    /** @var list<array{email: string, name: string}> */
    public const PANEL_USERS = [
        ['email' => 'admin@c-eco.test', 'name' => 'Admin C-ECO'],
        ['email' => 'pengawas@c-eco.test', 'name' => 'Pengawas Ruangan'],
        ['email' => 'operator@c-eco.test', 'name' => 'Operator C-ECO'],
        ['email' => 'peneliti@c-eco.test', 'name' => 'Peneliti C-ECO'],
    ];

    public function run(): void
    {
        $this->panelUsers();
        $this->students();
    }

    public function panelUsers(): void
    {
        $password = Hash::make(self::PASSWORD);

        foreach (self::PANEL_USERS as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'password' => $password],
            );
        }

        $this->command?->info('Panel: 4 akun, kata sandi '.self::PASSWORD);
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
