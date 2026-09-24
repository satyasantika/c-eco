<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ExamGroup;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\MixedPackageComposer;
use Database\Seeders\SimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class SimulationSeederTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
        $this->seed(SimulationSeeder::class);
    }

    public function test_every_panel_role_can_sign_in(): void
    {
        foreach (SimulationSeeder::panelAccounts() as $user) {
            $this->assertTrue(
                Auth::attempt([
                    'email' => $user['email'],
                    'password' => SimulationSeeder::PASSWORD,
                ]),
                "Gagal masuk sebagai {$user['email']}",
            );
            Auth::logout();
        }

        $this->assertSame(14, User::query()->where('is_active', true)->count());
        $this->assertSame(2, User::query()->where('is_active', true)->where('role', UserRole::Operator)->count());
        $this->assertSame(10, User::query()->where('is_active', true)->where('role', UserRole::Pengawas)->count());
    }

    public function test_a_pengawas_can_open_the_monitor(): void
    {
        $this->actingAs(User::query()->where('email', 'pengawas01@c-eco.test')->firstOrFail())
            ->get('/admin/monitor')
            ->assertOk();
    }

    public function test_the_three_classroom_presets_can_coexist(): void
    {
        foreach (SimulationSeeder::PRESETS as $size => $preset) {
            $groups = ExamGroup::query()
                ->where('starts_at', $preset['starts_at'])
                ->orderBy('room')
                ->get();

            $this->assertCount($preset['rooms'], $groups, "ukuran {$size}");
            $this->assertSame(1, $groups->pluck('starts_at')->unique()->count());
            $this->assertSame($preset['rooms'], $groups->pluck('room')->unique()->count());
            $this->assertSame($preset['rooms'], $groups->pluck('supervisor_id')->unique()->count());
            $this->assertSame($preset['students'], $groups->sum(fn (ExamGroup $g): int => $g->seatCount()));
            $this->assertTrue($groups->every(fn (ExamGroup $g): bool => $g->capacity === $preset['per_room']));
        }

        $this->assertSame(15, ExamGroup::query()->count());
        $this->assertSame(420, TestSession::query()->whereNotNull('exam_group_id')->count());
        $this->assertSame(3, ExamGroup::query()->pluck('starts_at')->unique()->count());
    }

    public function test_twenty_students_in_one_room_open_the_identity_form(): void
    {
        $this->travelTo(SimulationSeeder::preset(20)['starts_at']);

        $group = ExamGroup::query()
            ->where('starts_at', SimulationSeeder::preset(20)['starts_at'])
            ->firstOrFail();

        $this->assertSame(20, $group->seatCount());

        $token = $group->testSessions()->orderBy('id')->value('access_token');

        $this->get('/t/'.$token)
            ->assertOk()
            ->assertSee('Nomor induk siswa')
            ->assertSee('Isi identitas');
    }

    public function test_the_shared_package_covers_all_grades_evenly(): void
    {
        $config = TestConfig::query()->where('name', SimulationSeeder::MIXED_PACKAGE_NAME)->firstOrFail();
        $this->assertTrue($config->usesGradeShares());

        $expected = MixedPackageComposer::allocate(
            SimulationSeeder::POOL_SIZE,
            SimulationSeeder::GRADE_SHARE_X,
            SimulationSeeder::GRADE_SHARE_XI,
            SimulationSeeder::GRADE_SHARE_XII,
        );
        $items = $config->packageItems()->with('itemBank')->get();
        $this->assertSame(SimulationSeeder::POOL_SIZE, $items->count());
        $this->assertSame($expected['X'], $items->where('itemBank.grade', 'X')->count());
        $this->assertSame($expected['XI'], $items->where('itemBank.grade', 'XI')->count());
        $this->assertSame($expected['XII'], $items->where('itemBank.grade', 'XII')->count());
        $this->assertLessThanOrEqual(1, max($expected) - min($expected));

        $this->assertSame(1, ExamGroup::query()->pluck('test_config_id')->unique()->count());
    }

    public function test_pengawas_ten_can_open_the_three_hundred_student_qr(): void
    {
        $group = ExamGroup::query()
            ->where('starts_at', SimulationSeeder::preset(300)['starts_at'])
            ->orderBy('room')
            ->get()
            ->last();

        $pengawas = User::query()->where('email', 'pengawas10@c-eco.test')->firstOrFail();
        $token = $group->testSessions()->orderBy('id')->value('access_token');

        // Jadwal preset memakai tanggal tetap; tanpa ini tes gagal setelah hari itu lewat.
        $this->travelTo($group->starts_at->copy()->subHour());

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('Kartu QR belum dibuka')
            ->assertDontSee($token);

        $this->travelTo($group->starts_at);

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('sudah memuat')
            ->assertSee($token);
    }

    public function test_the_classroom_schedule_is_idempotent(): void
    {
        $this->seed(SimulationSeeder::class);

        $this->assertSame(15, ExamGroup::query()->count());
        $this->assertSame(420, TestSession::query()->whereNotNull('exam_group_id')->count());
        $this->assertSame(1, TestConfig::query()->where('name', SimulationSeeder::MIXED_PACKAGE_NAME)->count());
    }

    public function test_paper_slips_remain_available_as_a_fallback(): void
    {
        $seeder = new SimulationSeeder;
        $seeder->students(20);

        $sessions = TestSession::query()
            ->whereHas('participant', fn ($q) => $q->where('class_name', 'XI-SIM'))
            ->get();

        $this->assertCount(20, $sessions);
        $this->get('/t/'.$sessions->first()->access_token)
            ->assertOk()
            ->assertSee('Siswa Simulasi 01');
    }

    public function test_the_artisan_command_rejects_an_unknown_size(): void
    {
        $this->artisan('cat:seed-simulation', ['size' => '50'])
            ->assertFailed();
    }
}
