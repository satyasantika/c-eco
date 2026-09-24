<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ExamSimulations\Pages\ListExamSimulations;
use App\Models\ExamSimulation;
use App\Models\Item;
use App\Models\ItemParameter;
use App\Models\Participant;
use App\Models\School;
use App\Models\SessionItem;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\DemoSimulation;
use App\Services\ExamSimulationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class DemoSimulationTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_generate_creates_every_panel_role_and_played_sessions(): void
    {
        ['simulation' => $demo, 'created' => $created] = app(DemoSimulation::class)->generate();

        $this->assertTrue($created);
        $this->assertTrue($demo->is_demo);

        $roles = $demo->users()->pluck('role')->map(fn (UserRole $r): string => $r->value)->unique()->sort()->values()->all();
        $this->assertSame(['admin', 'operator', 'peneliti', 'pengawas'], $roles);
        $this->assertSame(0, $demo->users()->whereNull('exam_simulation_id')->count());

        $sessions = TestSession::query()->whereIn('exam_group_id', $demo->examGroups()->select('id'));
        $this->assertSame(DemoSimulation::STUDENTS + DemoSimulation::SCHEDULED_SEATS, (clone $sessions)->count());

        // Satu ruang sengaja belum dimulai: kursinya kosong dan siswa masih tertahan.
        $scheduled = $demo->examGroups()->get()->reject->hasStarted()->values();
        $this->assertCount(1, $scheduled);
        $this->assertSame(DemoSimulation::SCHEDULED_SEATS, $scheduled[0]->testSessions()->whereNull('opened_at')->whereNull('claimed_at')->count());
        $this->get('/t/'.$scheduled[0]->testSessions()->value('access_token'))->assertSee('Tes belum dimulai');
        $this->assertGreaterThan(0, (clone $sessions)->where('status', 'completed')->count());
        $this->assertGreaterThan(0, (clone $sessions)->where('status', 'in_progress')->count());
        $this->assertGreaterThan(0, (clone $sessions)->where('status', 'pending')->count());

        $answered = SessionItem::query()->whereIn('test_session_id', (clone $sessions)->select('id'));
        $this->assertGreaterThan(0, (clone $answered)->whereNotNull('response_label')->count());
        // R4: jawaban demo pun harus membawa versi parameternya.
        $this->assertSame(0, (clone $answered)->whereNull('item_parameter_id')->count());

        $status = app(DemoSimulation::class)->status();
        $this->assertTrue($status['active']);
        $this->assertSame(5, $status['accounts']);
    }

    public function test_generate_is_idempotent(): void
    {
        $demo = app(DemoSimulation::class);
        $first = $demo->generate()['simulation'];
        $users = User::query()->count();
        $sessions = TestSession::query()->count();

        ['simulation' => $again, 'created' => $created] = $demo->generate();

        $this->assertFalse($created);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, ExamSimulation::query()->demo()->count());
        $this->assertSame($users, User::query()->count());
        $this->assertSame($sessions, TestSession::query()->count());
    }

    public function test_reset_removes_only_demo_rows(): void
    {
        $realAdmin = User::factory()->create(['email' => 'asli@c-eco.test']);
        $manualWave = app(ExamSimulationBuilder::class)->create([
            'name' => 'Gelombang tulisan admin',
            'students' => 4,
            'rooms' => 1,
            'starts_at' => Carbon::now()->addHour(),
            'grade_share_x' => 34,
            'grade_share_xi' => 33,
            'grade_share_xii' => 33,
            'pool_size' => 30,
            'operators_count' => 1,
            'pengawas_count' => 1,
            'plain_password' => 'ekonomi1234',
        ]);
        $realSession = $this->makeSession('XI', token: 'ASLI2345');

        $before = [
            'items' => Item::query()->count(),
            'parameters' => ItemParameter::query()->count(),
            'users' => User::query()->count(),
            'sessions' => TestSession::query()->count(),
            'participants' => Participant::query()->count(),
            'schools' => School::query()->count(),
            'configs' => TestConfig::query()->count(),
        ];

        $demo = app(DemoSimulation::class);
        $demo->generate();
        $this->assertSame(1, $demo->reset());
        $this->assertSame(0, $demo->reset());

        $this->assertNull($demo->active());
        $this->assertFalse($demo->status()['active']);
        $this->assertSame($before, [
            'items' => Item::query()->count(),
            'parameters' => ItemParameter::query()->count(),
            'users' => User::query()->count(),
            'sessions' => TestSession::query()->count(),
            'participants' => Participant::query()->count(),
            'schools' => School::query()->count(),
            'configs' => TestConfig::query()->count(),
        ]);
        $this->assertTrue($realAdmin->fresh()?->exists);
        $this->assertNotNull($manualWave->fresh());
        $this->assertNotNull($realSession->fresh());
    }

    public function test_commands_are_repeatable(): void
    {
        $this->artisan('simulation:status')->expectsOutputToContain('tidak aktif')->assertSuccessful();
        $this->artisan('simulation:generate')->expectsOutputToContain('Simulasi demo dibuat')->assertSuccessful();
        $this->artisan('simulation:generate')->expectsOutputToContain('sudah aktif')->assertSuccessful();
        $this->artisan('simulation:status')->expectsOutputToContain('aktif sejak')->assertSuccessful();
        $this->artisan('simulation:reset')->expectsOutputToContain('dihapus')->assertSuccessful();
        $this->artisan('simulation:reset')->expectsOutputToContain('Tidak ada simulasi demo aktif')->assertSuccessful();

        $this->assertSame(0, ExamSimulation::query()->count());
    }

    public function test_admin_creates_and_removes_the_demo_from_the_panel(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/exam-simulations')
            ->assertOk()
            ->assertSee('data-demo-status="inactive"', false)
            // Tampilan kustom tanpa tabel: modal konfirmasi harus dirender sendiri.
            ->assertSee('filamentActionModals', false);

        Livewire::actingAs($admin)
            ->test(ListExamSimulations::class)
            ->assertActionVisible('generateDemo')
            ->assertActionHidden('resetDemo')
            ->callAction('generateDemo')
            ->assertNotified();

        $this->assertNotNull(app(DemoSimulation::class)->active());

        $this->actingAs($admin)->get('/admin/exam-simulations')
            ->assertOk()
            ->assertSee('data-demo-status="active"', false);

        Livewire::actingAs($admin)
            ->test(ListExamSimulations::class)
            ->assertActionHidden('generateDemo')
            ->assertActionVisible('resetDemo')
            ->callAction('resetDemo')
            ->assertNotified();

        $this->assertNull(app(DemoSimulation::class)->active());
        $this->assertTrue($admin->fresh()?->exists);
    }

    public function test_only_admin_may_manage_the_demo(): void
    {
        foreach ([User::factory()->operator(), User::factory()->pengawas(), User::factory()->peneliti()] as $factory) {
            $user = $factory->create();
            $this->assertFalse($user->can('manage-simulation'));
            $this->actingAs($user)->get('/admin/exam-simulations')->assertForbidden();
        }

        $this->assertTrue(User::factory()->create()->can('manage-simulation'));
    }
}
