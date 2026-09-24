<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\Monitor;
use App\Filament\Resources\ExamGroups\Pages\ListExamGroups;
use App\Filament\Widgets\LiveSessions;
use App\Filament\Widgets\SessionOverview;
use App\Models\ExamGroup;
use App\Models\Participant;
use App\Models\School;
use App\Models\SessionEvent;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\DemoSimulation;
use App\Services\ExamGroupSeater;
use App\Support\MonitorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class MonitorScopeTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_the_monitor_counts_only_todays_real_schedules_by_default(): void
    {
        $today = $this->room(Carbon::today()->setTime(8, 0), seats: 3);
        $old = $this->room(Carbon::today()->subDays(5)->setTime(8, 0), seats: 4);
        $old->testSessions()->orderBy('id')->first()->forceFill(['status' => 'completed'])->save();
        SessionEvent::query()->create([
            'test_session_id' => $old->testSessions()->orderBy('id')->first()->id,
            'type' => 'duplicate_submit',
            'payload_json' => [],
            'occurred_at' => Carbon::now(),
        ]);
        app(DemoSimulation::class)->generate();

        $stats = $this->stats(['date' => Carbon::today()->toDateString()]);

        $this->assertSame('3', $stats['Belum mulai']);
        $this->assertSame('0', $stats['Selesai']);
        $this->assertSame('0', $stats['Kiriman ulang']);
        $this->assertSame('0', $stats['Sedang mengerjakan'], 'Kursi simulasi tidak ikut KPI tes asli.');

        // Tanpa filter halaman (null) juga berarti hari ini.
        $this->assertSame('3', $this->stats(null)['Belum mulai']);

        // Tanggal lama menampilkan data lamanya saja.
        $past = $this->stats(['date' => Carbon::today()->subDays(5)->toDateString()]);
        $this->assertSame('3', $past['Belum mulai']);
        $this->assertSame('1', $past['Selesai']);
        $this->assertSame('1', $past['Kiriman ulang']);

        // Semua tanggal: tes asli saja, tetap tanpa simulasi.
        $this->assertSame('6', $this->stats(['date' => null])['Belum mulai']);

        // Satu jadwal.
        $this->assertSame('3', $this->stats(['date' => null, 'exam_group_id' => $today->id])['Belum mulai']);
    }

    public function test_the_session_table_follows_the_page_filter(): void
    {
        $operator = User::factory()->operator()->create();
        $today = $this->room(Carbon::today()->setTime(8, 0), seats: 1);
        $old = $this->room(Carbon::today()->subDay()->setTime(8, 0), seats: 1);

        Livewire::actingAs($operator)
            ->test(LiveSessions::class, ['pageFilters' => ['date' => Carbon::today()->toDateString()]])
            ->assertCanSeeTableRecords($today->testSessions)
            ->assertCanNotSeeTableRecords($old->testSessions);
    }

    public function test_the_monitor_page_shows_the_filter_and_defaults_to_today(): void
    {
        $operator = User::factory()->operator()->create();

        Livewire::actingAs($operator)
            ->test(Monitor::class)
            ->assertSet('filters.date', fn ($d): bool => Carbon::parse($d)->isToday())
            ->assertSee('Tanggal jadwal');
    }

    public function test_a_pengawas_sees_only_their_rooms_in_the_counts(): void
    {
        $mine = User::factory()->pengawas()->create();
        $this->room(Carbon::today()->setTime(8, 0), seats: 2, supervisor: $mine);
        $this->room(Carbon::today()->setTime(8, 0), seats: 5, room: 'B');

        $this->actingAs($mine);
        $this->assertSame('2', $this->stats(null)['Belum mulai']);
        $this->assertCount(1, MonitorScope::groupOptions($mine));
    }

    public function test_a_pengawas_has_no_new_schedule_button(): void
    {
        Livewire::actingAs(User::factory()->pengawas()->create())
            ->test(ListExamGroups::class)
            ->assertActionHidden('create');

        Livewire::actingAs(User::factory()->operator()->create())
            ->test(ListExamGroups::class)
            ->assertActionVisible('create');

        $this->actingAs(User::factory()->pengawas()->create())
            ->get('/admin/exam-groups/create')
            ->assertForbidden();
    }

    public function test_deleting_a_schedule_any_way_removes_its_unused_tokens(): void
    {
        $group = $this->room(Carbon::today()->setTime(8, 0), seats: 3);
        $used = $group->testSessions()->orderBy('id')->first();
        $used->forceFill(['status' => 'in_progress', 'opened_at' => Carbon::now()])->save();
        $tokens = $group->testSessions()->pluck('access_token');

        $group->delete();

        $this->assertSame(1, TestSession::query()->whereIn('access_token', $tokens)->count(), 'Kursi yang dipakai tetap disimpan.');
        $this->assertSame(0, Participant::query()->where('student_code', 'like', 'KURSI-%')->whereDoesntHave('testSessions')->count());
    }

    public function test_the_cleanup_migration_removes_orphan_unused_seats_only(): void
    {
        $group = $this->room(Carbon::today()->setTime(8, 0), seats: 3);
        $sessions = $group->testSessions()->orderBy('id')->get();
        $sessions[0]->forceFill(['status' => 'completed'])->save();
        // Seperti jadwal yang dulu terhapus: kursi tertinggal tanpa jadwal.
        TestSession::query()->whereIn('id', $sessions->pluck('id'))->update(['exam_group_id' => null]);
        $legacy = $this->makeSession(token: 'LEGA2345');

        (require database_path('migrations/2026_09_25_000014_purge_orphan_exam_seats.php'))->up();

        $this->assertModelExists($sessions[0]);
        $this->assertModelMissing($sessions[1]);
        $this->assertModelMissing($sessions[2]);
        $this->assertModelExists($legacy);
    }

    /** @return array<string, string> */
    private function stats(?array $filters): array
    {
        $widget = new SessionOverview;
        $widget->pageFilters = $filters;
        $stats = (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);

        return collect($stats)->mapWithKeys(fn ($stat): array => [$stat->getLabel() => (string) $stat->getValue()])->all();
    }

    private function room(Carbon $startsAt, int $seats, ?User $supervisor = null, string $room = 'A'): ExamGroup
    {
        $school = School::query()->firstOrCreate(['name' => 'SMA Uji'], ['city' => 'Tasikmalaya']);
        $config = TestConfig::query()->whereNull('exam_simulation_id')->firstOrFail();

        $group = ExamGroup::query()->create([
            'school_id' => $school->id,
            'name' => 'Ruang '.$room,
            'room' => $room.'-'.$startsAt->format('md'),
            'starts_at' => $startsAt,
            'supervisor_id' => ($supervisor ?? User::factory()->pengawas()->create())->id,
            'test_config_id' => $config->id,
            'capacity' => $seats,
        ]);

        app(ExamGroupSeater::class)->fill($group);

        return $group->fresh(['testSessions']);
    }
}
