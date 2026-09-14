<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\ExamSimulations\Pages\CreateExamSimulation;
use App\Models\ExamGroup;
use App\Models\ExamSimulation;
use App\Models\Item;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\ExamSimulationBuilder;
use App\Services\ExamSimulationPurger;
use App\Services\MixedPackageComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class ExamSimulationTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_simulation_creates_isolated_rooms_accounts_and_package(): void
    {
        $beforeItems = Item::query()->count();
        $beforeConfigs = TestConfig::query()->whereNull('exam_simulation_id')->count();
        $beforeUsers = User::query()->whereNull('exam_simulation_id')->count();

        $simulation = $this->makeSimulation([
            'students' => 23,
            'rooms' => 2,
            'operators_count' => 2,
            'pengawas_count' => 2,
            'grade_share_x' => 34,
            'grade_share_xi' => 33,
            'grade_share_xii' => 33,
            'pool_size' => 30,
        ]);

        $this->assertSame(23, $simulation->examGroups->sum(fn (ExamGroup $g): int => $g->seatCount()));
        $this->assertSame([12, 11], $simulation->examGroups->sortBy('room')->values()->map->capacity->all());
        $this->assertSame(2, $simulation->operators()->count());
        $this->assertSame(2, $simulation->pengawas()->count());
        $this->assertTrue(User::query()->where('email', 'sim'.$simulation->id.'.op01@c-eco.test')->exists());
        $this->assertTrue(User::query()->where('email', 'sim'.$simulation->id.'.pw01@c-eco.test')->exists());

        $config = $simulation->testConfig;
        $this->assertNotNull($config);
        $this->assertTrue($config->usesGradeShares());
        $expected = MixedPackageComposer::allocate(30, 34, 33, 33);
        $items = $config->packageItems()->with('itemBank')->get();
        $this->assertSame(30, $items->count());
        $this->assertSame($expected['X'], $items->where('itemBank.grade', 'X')->count());
        $this->assertSame($expected['XI'], $items->where('itemBank.grade', 'XI')->count());
        $this->assertSame($expected['XII'], $items->where('itemBank.grade', 'XII')->count());

        $this->assertSame($beforeItems, Item::query()->count());
        $this->assertSame($beforeConfigs, TestConfig::query()->whereNull('exam_simulation_id')->count());
        $this->assertSame($beforeUsers, User::query()->whereNull('exam_simulation_id')->count());
    }

    public function test_deleting_a_simulation_leaves_real_tests_and_the_item_bank_intact(): void
    {
        $admin = User::factory()->create();
        $realSchool = School::query()->firstOrCreate(['name' => 'SMA Asli'], ['city' => 'Tasikmalaya']);
        $realConfig = TestConfig::query()->whereNull('exam_simulation_id')->firstOrFail();
        $realGroup = ExamGroup::query()->create([
            'school_id' => $realSchool->id,
            'name' => 'Tes asli',
            'room' => 'AULA',
            'starts_at' => Carbon::now()->addDay(),
            'supervisor_id' => User::factory()->pengawas()->create()->id,
            'test_config_id' => $realConfig->id,
            'capacity' => 2,
        ]);

        $snapshot = ExamSimulationPurger::inventorySnapshot();
        $simulation = $this->makeSimulation(['students' => 5, 'rooms' => 1, 'pengawas_count' => 1]);
        $simId = $simulation->id;
        $simUser = $simulation->pengawas()->firstOrFail();

        $this->actingAs($simUser)->get('/admin/monitor')->assertOk();

        $simulation->delete();

        $this->assertNull(ExamSimulation::query()->find($simId));
        $this->assertSame(0, ExamGroup::query()->where('exam_simulation_id', $simId)->count());
        $this->assertFalse(User::query()->where('email', 'sim'.$simId.'.pw01@c-eco.test')->exists());
        $this->assertFalse(TestConfig::query()->where('name', 'like', 'Simulasi #'.$simId.'%')->exists());
        $this->assertFalse(School::query()->where('name', 'SMA Simulasi #'.$simId)->exists());

        $this->assertTrue($realGroup->fresh()->exists);
        $this->assertTrue($realConfig->fresh()->exists);
        $this->assertTrue($admin->fresh()->exists);
        $this->assertSame($snapshot, ExamSimulationPurger::inventorySnapshot());
        $this->assertSame(0, TestSession::query()->where('test_config_id', $realConfig->id)->whereNotNull('exam_group_id')->count());
    }

    public function test_only_admin_can_open_the_simulation_pages(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/exam-simulations')
            ->assertOk()
            ->assertSee('Tulis gelombang baru')
            ->assertSee('Gelombang uji terpisah')
            ->assertSee('Belum ada denah');
        $named = $this->makeSimulation(['name' => 'Gelombang tampak']);
        $this->actingAs($admin)
            ->get('/admin/exam-simulations')
            ->assertOk()
            ->assertSee('Gelombang tampak')
            ->assertSee($named->students.' siswa menempati');

        $this->actingAs($admin)->get('/admin/exam-simulations/create')->assertOk()->assertSee('Persen jenjang X');
        $this->actingAs(User::factory()->operator()->create())->get('/admin/exam-simulations')->assertForbidden();
        $this->actingAs(User::factory()->pengawas()->create())->get('/admin/exam-simulations')->assertForbidden();
        $this->actingAs(User::factory()->peneliti()->create())->get('/admin/exam-simulations')->assertForbidden();
    }

    public function test_admin_can_create_a_simulation_from_the_panel(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CreateExamSimulation::class)
            ->fillForm([
                'name' => 'Uji 8 siswa',
                'students' => 8,
                'rooms' => 2,
                'starts_at' => Carbon::now()->addHour(),
                'operators_count' => 1,
                'pengawas_count' => 2,
                'plain_password' => 'ekonomi1234',
                'grade_share_x' => 40,
                'grade_share_xi' => 30,
                'grade_share_xii' => 30,
                'pool_size' => 30,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $simulation = ExamSimulation::query()->where('name', 'Uji 8 siswa')->firstOrFail();
        $this->assertSame(8, $simulation->examGroups()->withCount('testSessions')->get()->sum('test_sessions_count'));
        $this->actingAs(User::factory()->create())
            ->get('/admin/exam-simulations/'.$simulation->id)
            ->assertOk()
            ->assertSee('sim'.$simulation->id.'.pw01@c-eco.test')
            ->assertSee('40% X');
    }

    public function test_simulation_accounts_cannot_edit_real_schedules_or_packages(): void
    {
        $simulation = $this->makeSimulation(['students' => 4, 'rooms' => 1, 'pengawas_count' => 1, 'operators_count' => 1]);
        $operator = $simulation->operators()->firstOrFail();

        $this->assertFalse($operator->canManageExamGroups());
        $this->assertFalse($operator->canManagePackages());
        $this->assertFalse($operator->canManageRoster());
        $this->actingAs($operator)->get('/admin/exam-groups/create')->assertForbidden();
        $this->actingAs($operator)->get('/admin/test-configs')->assertForbidden();
        $this->actingAs($operator)->get('/admin/exam-groups')->assertOk();
    }

    public function test_real_schedule_list_hides_simulation_rooms(): void
    {
        $simulation = $this->makeSimulation(['students' => 4, 'rooms' => 1, 'pengawas_count' => 1]);
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)
            ->get('/admin/exam-groups')
            ->assertOk()
            ->assertDontSee($simulation->examGroups->first()->room);
    }

    public function test_a_simulation_pengawas_can_open_their_qr_after_start(): void
    {
        $simulation = $this->makeSimulation([
            'students' => 3,
            'rooms' => 1,
            'pengawas_count' => 1,
            'starts_at' => Carbon::now()->subMinute(),
        ]);
        $group = $simulation->examGroups->firstOrFail();
        $token = $group->testSessions()->orderBy('id')->value('access_token');

        $this->actingAs($simulation->pengawas()->firstOrFail())
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee($token);
    }

    public function test_rescheduling_a_simulation_moves_its_rooms_not_real_groups(): void
    {
        $realSchool = School::query()->firstOrCreate(['name' => 'SMA Asli'], ['city' => 'Tasikmalaya']);
        $realConfig = TestConfig::query()->whereNull('exam_simulation_id')->firstOrFail();
        $realWhen = Carbon::parse('2026-09-21 10:00:00');
        $realGroup = ExamGroup::query()->create([
            'school_id' => $realSchool->id,
            'name' => 'Tes asli',
            'room' => 'AULA',
            'starts_at' => $realWhen,
            'supervisor_id' => User::factory()->pengawas()->create()->id,
            'test_config_id' => $realConfig->id,
            'capacity' => 2,
        ]);

        $simulation = $this->makeSimulation([
            'students' => 4,
            'rooms' => 2,
            'pengawas_count' => 2,
            'starts_at' => Carbon::parse('2026-09-21 07:00:00'),
        ]);

        $newWhen = Carbon::parse('2026-09-14 08:30:00', (string) config('app.timezone'));
        app(ExamSimulationBuilder::class)->reschedule($simulation, $newWhen);

        $this->assertSame($newWhen->timestamp, $simulation->fresh()->starts_at->timestamp);
        foreach ($simulation->examGroups as $group) {
            $this->assertSame($newWhen->timestamp, $group->fresh()->starts_at->timestamp);
        }
        $this->assertSame($realWhen->timestamp, $realGroup->fresh()->starts_at->timestamp);
    }

    public function test_admin_can_change_simulation_time_from_the_panel(): void
    {
        $simulation = $this->makeSimulation([
            'students' => 3,
            'rooms' => 1,
            'pengawas_count' => 1,
            'starts_at' => Carbon::parse('2026-09-21 07:00:00'),
        ]);
        $when = Carbon::parse('2026-09-14 09:15:00', (string) config('app.timezone'));

        Livewire::actingAs(User::factory()->create())
            ->test(\App\Filament\Resources\ExamSimulations\Pages\ViewExamSimulation::class, [
                'record' => $simulation->getKey(),
            ])
            ->callAction('reschedule', ['starts_at' => $when])
            ->assertHasNoActionErrors();

        $this->assertSame(
            $when->timestamp,
            $simulation->fresh()->starts_at->timestamp,
        );
        $this->assertSame(
            $when->timestamp,
            $simulation->examGroups()->firstOrFail()->starts_at->timestamp,
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSimulation(array $overrides = []): ExamSimulation
    {
        return app(ExamSimulationBuilder::class)->create(array_merge([
            'name' => 'Gelombang uji',
            'students' => 20,
            'rooms' => 1,
            'starts_at' => Carbon::now()->addHour(),
            'grade_share_x' => 34,
            'grade_share_xi' => 33,
            'grade_share_xii' => 33,
            'pool_size' => 30,
            'operators_count' => 1,
            'pengawas_count' => 1,
            'plain_password' => 'ekonomi1234',
        ], $overrides));
    }
}
