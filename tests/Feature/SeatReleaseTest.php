<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\LiveSessions;
use App\Models\ExamGroup;
use App\Models\ItemBank;
use App\Models\School;
use App\Models\SeatRelease;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\DemoSimulation;
use App\Services\ExamGroupSeater;
use App\Services\SeatGuard;
use App\Services\SeatReleaser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class SeatReleaseTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_proctor_lets_a_student_continue_on_a_new_phone(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        [$session, $oldPhone] = $this->claimedSeat($pengawas, 'Siti Pindah');
        $token = $session->access_token;

        // HP baru ditolak sebelum ada izin.
        $this->phone(null);
        $this->get("/t/{$token}")->assertForbidden()->assertSee('pindah HP', false);

        Livewire::actingAs($pengawas)
            ->test(LiveSessions::class)
            ->assertTableActionVisible('releaseSeat', $session)
            ->callTableAction('releaseSeat', $session, data: ['reason' => 'baterai habis'])
            ->assertHasNoTableActionErrors();

        $release = SeatRelease::query()->sole();
        $this->assertSame($pengawas->id, $release->released_by);
        $this->assertSame('baterai habis', $release->reason);
        $this->assertNull($session->fresh()->resume_token);

        // HP lama tidak boleh merebut kembali selama izin berlaku.
        $this->phone($oldPhone);
        $this->get("/t/{$token}")->assertForbidden();

        // HP baru membuka token yang sama dan melanjutkan sebagai siswa yang sama.
        $this->phone(null);
        $this->get("/t/{$token}")->assertOk()->assertSee('Siti Pindah');

        $fresh = $session->fresh();
        $this->assertNotNull($fresh->resume_token);
        $this->assertNotSame($oldPhone, $fresh->resume_token);
        $this->assertNotNull($release->fresh()->used_at);
        $this->assertSame('Siti Pindah', $fresh->participant->display_name);

        $newPhone = $fresh->resume_token;
        $this->phone($newPhone);
        $this->get("/t/{$token}")->assertOk()->assertSee('Siti Pindah');

        $this->phone($oldPhone);
        $this->get("/t/{$token}")->assertForbidden();
    }

    public function test_an_unused_permission_expires_and_the_old_phone_keeps_the_seat(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        [$session, $oldPhone] = $this->claimedSeat($pengawas, 'Budi Tetap');

        app(SeatReleaser::class)->release($pengawas, $session);

        $this->travel(SeatReleaser::RELEASE_MINUTES + 1)->minutes();

        $this->phone($oldPhone);
        $this->get("/t/{$session->access_token}")->assertOk()->assertSee('Budi Tetap');
        $this->assertSame($oldPhone, $session->fresh()->resume_token);
        $this->assertNotNull(SeatRelease::query()->sole()->restored_at);

        $this->phone(null);
        $this->get("/t/{$session->access_token}")->assertForbidden();
    }

    public function test_only_the_rooms_proctor_or_staff_may_release_a_seat(): void
    {
        $owner = User::factory()->pengawas()->create();
        [$session] = $this->claimedSeat($owner, 'Rina');
        $releaser = app(SeatReleaser::class);

        $this->assertFalse($releaser->canRelease(User::factory()->pengawas()->create(), $session));
        $this->assertFalse($releaser->canRelease(User::factory()->peneliti()->create(), $session));
        $this->assertTrue($releaser->canRelease(User::factory()->operator()->create(), $session));
        $this->assertTrue($releaser->canRelease($owner, $session));

        Livewire::actingAs(User::factory()->pengawas()->create())
            ->test(LiveSessions::class)
            ->assertCanNotSeeTableRecords([$session]);

        $this->expectException(RuntimeException::class);
        $releaser->release(User::factory()->pengawas()->create(), $session);
    }

    public function test_a_seat_that_is_not_locked_or_already_finished_cannot_be_released(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group($pengawas, capacity: 2);
        $free = $group->testSessions()->orderBy('id')->first();

        $this->assertNotNull(app(SeatReleaser::class)->blockReason($free));

        [$done] = $this->claimedSeat($pengawas, 'Selesai', $group);
        $done->forceFill(['status' => 'completed'])->save();
        $this->assertSame('Tes siswa ini sudah selesai.', app(SeatReleaser::class)->blockReason($done->fresh()));

        Livewire::actingAs($pengawas)
            ->test(LiveSessions::class)
            ->assertTableActionHidden('releaseSeat', $free)
            ->assertTableActionHidden('releaseSeat', $done);
    }

    public function test_the_monitor_finds_students_by_name_code_or_spaced_token(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group($pengawas, capacity: 3);
        [$siti] = $this->claimedSeat($pengawas, 'Siti Nurhaliza', $group, '1234567');
        [$budi] = $this->claimedSeat($pengawas, 'Budi Santoso', $group, '7654321');
        $spaced = strtolower(implode(' ', str_split($budi->access_token, 4)));

        Livewire::actingAs($pengawas)
            ->test(LiveSessions::class)
            ->assertCanSeeTableRecords([$siti, $budi])
            ->searchTable('nurhal')
            ->assertCanSeeTableRecords([$siti])
            ->assertCanNotSeeTableRecords([$budi])
            ->searchTable('7654321')
            ->assertCanSeeTableRecords([$budi])
            ->assertCanNotSeeTableRecords([$siti])
            ->searchTable($spaced)
            ->assertCanSeeTableRecords([$budi])
            ->assertCanNotSeeTableRecords([$siti])
            ->searchTable('')
            ->filterTable('exam_group_id', $group->id)
            ->assertCanSeeTableRecords([$siti, $budi]);
    }

    public function test_the_demo_simulation_lets_its_proctor_practise_moving_a_phone(): void
    {
        ['simulation' => $demo] = app(DemoSimulation::class)->generate();
        $seat = TestSession::query()
            ->whereIn('exam_group_id', $demo->examGroups()->select('id'))
            ->where('status', 'in_progress')
            ->firstOrFail();
        $pengawas = $seat->examGroup->supervisor;
        $this->assertSame($demo->id, $pengawas->exam_simulation_id);

        $this->assertNotNull($seat->resume_token, 'Kursi demo yang sedang dikerjakan harus terkunci ke satu HP.');

        Livewire::actingAs($pengawas)
            ->test(LiveSessions::class)
            ->assertCanSeeTableRecords([$seat])
            ->callTableAction('releaseSeat', $seat)
            ->assertHasNoTableActionErrors();

        $this->assertNull($seat->fresh()->resume_token);

        $this->phone(null);
        $this->get("/t/{$seat->access_token}")->assertOk();
        $this->assertNotNull($seat->fresh()->resume_token);

        // Akun demo tidak bisa melepas kursi tes asli.
        [$real] = $this->claimedSeat(User::factory()->pengawas()->create(), 'Siswa Asli');
        $admin = $demo->users()->where('role', 'admin')->firstOrFail();
        $this->assertFalse(app(SeatReleaser::class)->canRelease($admin, $real));

        // Menghapus demo ikut menghapus jejak izinnya.
        app(DemoSimulation::class)->reset();
        $this->assertSame(0, SeatRelease::query()->where('test_session_id', $seat->id)->count());
    }

    /**
     * Kursi yang sudah diisi identitas dari satu "HP".
     *
     * @return array{0: TestSession, 1: string} sesi dan kunci HP-nya
     */
    private function claimedSeat(User $pengawas, string $name, ?ExamGroup $group = null, ?string $code = null): array
    {
        $group ??= $this->group($pengawas, capacity: 1);
        $session = $group->testSessions()->whereNull('claimed_at')->orderBy('id')->firstOrFail();
        $token = $session->access_token;

        $this->phone(null);
        $this->get("/t/{$token}")->assertOk();
        $this->post("/t/{$token}/identitas", [
            'student_code' => $code ?? (string) random_int(100000, 999999),
            'display_name' => $name,
            'grade' => 'XI',
            'class_name' => 'IPS 1',
        ])->assertRedirect();

        $session = $session->fresh();
        $this->assertNotNull($session->resume_token);

        return [$session, $session->resume_token];
    }

    /** Ganti "HP": sesi dan cookie bersih, opsional membawa kunci kursi tertentu. */
    private function phone(?string $secret): void
    {
        $this->flushSession();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        if ($secret !== null) {
            foreach (TestSession::query()->pluck('access_token') as $token) {
                $this->withCookie(app(SeatGuard::class)->cookieName($token), $secret);
            }
        }
    }

    private function group(User $supervisor, int $capacity): ExamGroup
    {
        $school = School::query()->firstOrCreate(['name' => 'SMA Uji'], ['city' => 'Tasikmalaya']);
        $bank = ItemBank::query()->where('grade', 'XI')->firstOrFail();
        $config = TestConfig::query()->where('item_bank_id', $bank->id)->firstOrFail();

        $group = ExamGroup::query()->create([
            'school_id' => $school->id,
            'name' => 'Ruang pindah HP',
            'room' => 'P-'.random_int(1, 9999),
            'starts_at' => Carbon::now()->subMinute(),
            'supervisor_id' => $supervisor->id,
            'test_config_id' => $config->id,
            'capacity' => $capacity,
        ]);

        app(ExamGroupSeater::class)->fill($group);

        return $group->fresh();
    }
}
