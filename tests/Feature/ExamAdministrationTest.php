<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ExamGroups\ExamGroupResource;
use App\Filament\Resources\ExamGroups\Pages\EditExamGroup;
use App\Filament\Resources\ExamGroups\Pages\ListExamGroups;
use App\Filament\Resources\TestConfigs\Pages\CreateTestConfig;
use App\Filament\Resources\TestConfigs\Schemas\TestConfigForm;
use App\Models\ExamGroup;
use App\Models\Item;
use App\Models\ItemBank;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\CatSession;
use App\Services\ExamGroupPlanner;
use App\Services\ExamGroupSeater;
use App\Services\ItemPool;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class ExamAdministrationTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_mixed_package_draws_only_from_the_selected_items(): void
    {
        $config = $this->mixedConfig();
        $allowed = $config->packageItems()->pluck('id')->sort()->values();

        $this->assertSame($allowed->count(), app(ItemPool::class)->query($config)->count());

        $session = $this->sessionFor($config);
        $cat = app(CatSession::class);
        $cat->start($session);

        $used = [];
        for ($i = 1; $i <= $config->max_items; $i++) {
            $session->refresh();
            $row = $session->sessionItems()->where('sequence', $i)->first();
            if ($row === null) {
                break;
            }
            $used[] = $row->item_id;
            if ($row->response_label === null) {
                $cat->answer($session, $i, 'A');
            }
        }

        $this->assertNotEmpty($used);
        $this->assertEmpty(array_diff($used, $allowed->all()));
        $this->assertGreaterThan(
            1,
            Item::query()->whereIn('id', $used)->pluck('item_bank_id')->unique()->count()
        );
    }

    public function test_an_exam_group_gets_one_token_seat_per_capacity(): void
    {
        $group = $this->group(capacity: 4);

        $this->assertSame(4, $group->testSessions()->count());
        $this->assertSame(4, $group->testSessions()->whereNull('opened_at')->count());
        $this->assertTrue($group->testSessions()->first()->isUnclaimed());
    }

    public function test_loading_the_token_advances_the_proctor_qr_before_identity(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 2, supervisor: $pengawas);
        $first = $group->testSessions()->orderBy('id')->firstOrFail();
        $second = $group->testSessions()->orderBy('id')->skip(1)->firstOrFail();

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('data-token="'.$first->access_token.'"', false)
            ->assertSee('sudah memuat')
            ->assertDontSee('filament', false);

        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Nomor induk siswa')
            ->assertSee('Isi identitas');

        $this->assertNotNull($first->fresh()->opened_at);
        $this->assertNull($first->fresh()->claimed_at);

        $this->actingAs($pengawas)
            ->getJson(route('proctor.qr.current', $group))
            ->assertOk()
            ->assertJsonPath('token', $second->access_token)
            ->assertJsonPath('opened', 1)
            ->assertJsonPath('claimed', 0);
    }

    public function test_scanning_asks_for_identity_then_the_student_can_continue(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 2, supervisor: $pengawas);
        $first = $group->testSessions()->orderBy('id')->firstOrFail();

        $this->get("/t/{$first->access_token}")->assertOk();

        $this->post("/t/{$first->access_token}/identitas", [
            'student_code' => '12345678',
            'display_name' => 'Siti Uji',
            'grade' => 'XI',
            'class_name' => 'IPS 1',
        ])->assertRedirect(route('student.show', $first->access_token));

        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Siti Uji')
            ->assertSee('Saya bersedia mengikuti tes ini.');
    }

    public function test_a_taken_token_cannot_be_claimed_by_someone_else(): void
    {
        $group = $this->group(capacity: 1);
        $session = $group->testSessions()->firstOrFail();

        $this->post("/t/{$session->access_token}/identitas", [
            'student_code' => '111',
            'display_name' => 'A',
            'grade' => 'X',
            'class_name' => '1',
        ])->assertRedirect();

        $this->post("/t/{$session->access_token}/identitas", [
            'student_code' => '222',
            'display_name' => 'B',
            'grade' => 'X',
            'class_name' => '1',
        ])->assertRedirect()->assertSessionHasErrors('student_code');
    }

    public function test_a_proctor_can_advance_the_qr_without_waiting_for_the_student_page(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 2, supervisor: $pengawas);
        $first = $group->testSessions()->orderBy('id')->firstOrFail();
        $second = $group->testSessions()->orderBy('id')->skip(1)->firstOrFail();

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('Token berikutnya', false)
            ->assertSee('data-advance-url', false)
            ->assertSee($first->access_token);

        $this->actingAs($pengawas)
            ->postJson(route('proctor.qr.advance', $group))
            ->assertOk()
            ->assertJsonPath('token', $second->access_token)
            ->assertJsonPath('opened', 1)
            ->assertJsonPath('claimed', 0);

        $this->assertNotNull($first->fresh()->opened_at);
        $this->assertNull($first->fresh()->claimed_at);
        $this->assertNull($first->fresh()->resume_token);

        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Nomor induk siswa');
    }

    public function test_another_phone_cannot_continue_a_claimed_token(): void
    {
        $group = $this->group(capacity: 1);
        $session = $group->testSessions()->firstOrFail();
        $token = $session->access_token;

        $this->get("/t/{$token}")->assertOk()->assertSee('Nomor induk siswa');
        $this->post("/t/{$token}/identitas", [
            'student_code' => '111',
            'display_name' => 'Peserta Pertama',
            'grade' => 'X',
            'class_name' => '1',
        ])->assertRedirect();

        $this->get("/t/{$token}")
            ->assertOk()
            ->assertSee('Peserta Pertama')
            ->assertSee('Saya bersedia mengikuti tes ini.');

        $this->assertNotNull($session->fresh()->resume_token);

        $this->flushSession();
        $this->withUnencryptedCookie('ceco_seat_'.strtolower($token), '00000000000000000000000000000000');

        $this->get("/t/{$token}")
            ->assertForbidden()
            ->assertSee('Token sudah dipakai', false)
            ->assertDontSee('Peserta Pertama', false);

        $this->assertSame('Peserta Pertama', $session->fresh()->participant->display_name);
    }

    public function test_a_pengawas_cannot_open_another_groups_qr(): void
    {
        $mine = User::factory()->pengawas()->create();
        $other = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 1, supervisor: $other);

        $this->actingAs($mine)->get(route('proctor.qr', $group))->assertForbidden();
        $this->actingAs($other)->get(route('proctor.qr', $group))->assertOk();
    }

    public function test_grade_share_percentages_compose_a_mixed_pool(): void
    {
        $bank = ItemBank::query()->where('grade', 'XI')->firstOrFail();
        $config = TestConfig::query()->create(array_merge(TestConfigProvisioner::adaptiveAttributes(), [
            'item_bank_id' => $bank->id,
            'name' => 'Campuran persen',
            'min_items' => 14,
            'max_items' => 20,
            'grade_share_x' => 40,
            'grade_share_xi' => 30,
            'grade_share_xii' => 30,
            'pool_size' => 30,
        ]));

        app(MixedPackageComposer::class)->apply($config);

        $items = $config->packageItems()->with('itemBank')->get();
        $this->assertSame(30, $items->count());
        $this->assertSame(12, $items->where('itemBank.grade', 'X')->count());
        $this->assertSame(9, $items->where('itemBank.grade', 'XI')->count());
        $this->assertSame(9, $items->where('itemBank.grade', 'XII')->count());
        $this->assertSame(30, app(ItemPool::class)->query($config)->count());
    }

    public function test_only_operators_can_manage_schedules_and_packages(): void
    {
        $operator = User::factory()->operator()->create();
        $admin = User::factory()->create();
        $pengawas = User::factory()->pengawas()->create();
        $peneliti = User::factory()->peneliti()->create();

        $this->actingAs($operator)->get('/admin/exam-groups')->assertOk();
        $this->actingAs($operator)->get('/admin/exam-groups/create')->assertOk()->assertSee('Ruang');
        $this->actingAs($operator)->get('/admin/test-configs')->assertOk();
        $this->actingAs($operator)->get('/admin/test-configs/create')->assertOk()->assertSee('Persen jenjang X');

        $this->actingAs($admin)->get('/admin/exam-groups')->assertOk();
        $this->actingAs($admin)->get('/admin/exam-groups/create')->assertForbidden();
        $this->actingAs($admin)->get('/admin/test-configs')->assertForbidden();
        $this->actingAs($admin)->get('/admin/test-configs/create')->assertForbidden();

        $this->actingAs($pengawas)->get('/admin/exam-groups')->assertOk();
        $this->actingAs($pengawas)->get('/admin/exam-groups/create')->assertForbidden();
        $this->actingAs($pengawas)->get('/admin/test-configs')->assertForbidden();

        $this->actingAs($peneliti)->get('/admin/exam-groups')->assertForbidden();
        $this->actingAs($peneliti)->get('/admin/test-configs')->assertForbidden();
    }

    public function test_a_student_cannot_open_a_seat_before_the_scheduled_start(): void
    {
        $pengawas = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 2, supervisor: $pengawas);
        $group->forceFill(['starts_at' => Carbon::now()->addHour()])->save();
        $first = $group->testSessions()->orderBy('id')->firstOrFail();
        $second = $group->testSessions()->orderBy('id')->skip(1)->firstOrFail();

        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Tes belum dimulai')
            ->assertDontSee('Nomor induk siswa');

        $this->assertNull($first->fresh()->opened_at);

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('Kartu QR belum dibuka')
            ->assertDontSee($first->access_token)
            ->assertDontSee('id="qr-svg"', false);

        $this->actingAs($pengawas)
            ->getJson(route('proctor.qr.current', $group))
            ->assertOk()
            ->assertJsonPath('started', false)
            ->assertJsonPath('token', null)
            ->assertJsonPath('qr', null)
            ->assertJsonPath('opened', 0);

        $this->post("/t/{$first->access_token}/identitas", [
            'student_code' => '12345678',
            'display_name' => 'Siti Uji',
            'grade' => 'XI',
            'class_name' => 'IPS 1',
        ])->assertRedirect(route('student.show', $first->access_token));

        $this->assertNull($first->fresh()->claimed_at);

        $this->travelTo($group->starts_at);

        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Nomor induk siswa');

        $this->assertNotNull($first->fresh()->opened_at);

        $this->actingAs($pengawas)
            ->get(route('proctor.qr', $group))
            ->assertOk()
            ->assertSee('id="qr-svg"', false)
            ->assertSee($second->access_token)
            ->assertDontSee($first->access_token);

        $this->actingAs($pengawas)
            ->getJson(route('proctor.qr.current', $group))
            ->assertJsonPath('started', true)
            ->assertJsonPath('token', $second->access_token);
    }

    public function test_an_operator_can_print_group_slips_before_the_start_without_letting_students_in(): void
    {
        $operator = User::factory()->operator()->create();
        $group = $this->group(capacity: 3);
        $group->forceFill(['starts_at' => Carbon::now()->addDay()])->save();
        $sessions = $group->testSessions()->orderBy('id')->get();

        $html = (string) $this->actingAs($operator)
            ->get(route('admin.group-slips', $group))
            ->assertOk()
            ->assertSee('Kursi 1 / 3')
            // Gaya cetak inline; CSP style-src 'self' akan membuat slip tampil polos.
            ->assertHeaderMissing('Content-Security-Policy')
            ->assertSee('Tes belum dimulai')
            ->assertDontSee('filament', false)
            ->getContent();

        foreach ($sessions as $session) {
            $this->assertStringContainsString(route('student.show', $session->access_token), $html);
        }
        $this->assertSame(3, substr_count($html, '<svg'));

        // Mencetak tidak menyentuh kursi: tidak ada yang dianggap dibuka, diklaim, atau terikat.
        foreach ($sessions as $session) {
            $fresh = $session->fresh();
            $this->assertNull($fresh->opened_at);
            $this->assertNull($fresh->claimed_at);
            $this->assertNull($fresh->resume_token);
        }

        // Siswa memindai slip sebelum jadwal: semua pintu masuk tertahan.
        $first = $sessions->first();
        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Tes belum dimulai')
            ->assertDontSee('Nomor induk siswa');

        $this->post("/t/{$first->access_token}/identitas", [
            'student_code' => '12345678',
            'display_name' => 'Siti Uji',
            'grade' => 'XI',
            'class_name' => 'IPS 1',
        ])->assertRedirect(route('student.show', $first->access_token));
        $this->post("/t/{$first->access_token}/mulai", ['consent' => '1'])
            ->assertRedirect(route('student.show', $first->access_token));
        $this->post("/t/{$first->access_token}/tes")
            ->assertRedirect(route('student.show', $first->access_token));

        $fresh = $first->fresh();
        $this->assertNull($fresh->opened_at);
        $this->assertNull($fresh->claimed_at);
        $this->assertNull($fresh->resume_token);
        $this->assertSame('pending', $fresh->status);

        // Tepat di jam mulai, slip yang sama langsung bisa dipakai.
        $this->travelTo($group->starts_at);
        $this->get("/t/{$first->access_token}")
            ->assertOk()
            ->assertSee('Nomor induk siswa');
    }

    public function test_group_slips_are_limited_to_staff_of_that_group(): void
    {
        $owner = User::factory()->pengawas()->create();
        $group = $this->group(capacity: 2, supervisor: $owner);

        $this->get(route('admin.group-slips', $group))->assertRedirect('/login');
        $this->actingAs(User::factory()->peneliti()->create())
            ->get(route('admin.group-slips', $group))->assertForbidden();
        $this->actingAs(User::factory()->pengawas()->create())
            ->get(route('admin.group-slips', $group))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.group-slips', $group))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('admin.group-slips', $group))->assertOk();
    }

    public function test_an_operator_can_change_the_package_of_a_schedule_before_it_starts(): void
    {
        $operator = User::factory()->operator()->create();
        $group = $this->group(capacity: 3);
        $group->forceFill(['starts_at' => Carbon::now()->addDay()])->save();
        $tokens = $group->testSessions()->orderBy('id')->pluck('access_token')->all();
        $other = $this->mixedConfig();

        Livewire::actingAs($operator)
            ->test(EditExamGroup::class, ['record' => $group->getRouteKey()])
            ->fillForm(['test_config_id' => $other->id, 'capacity' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $group->refresh();
        $this->assertSame($other->id, $group->test_config_id);
        $sessions = $group->testSessions()->orderBy('id')->get();
        $this->assertCount(2, $sessions);
        // Token lama tetap berlaku (kursi dari ujung antrean yang dibuang).
        $this->assertSame(array_slice($tokens, 0, 2), $sessions->pluck('access_token')->all());
        foreach ($sessions as $session) {
            $this->assertSame($other->id, $session->test_config_id);
            $this->assertSame($other->item_bank_id, $session->item_bank_id);
        }
        $this->assertSame(0, Participant::query()->where('student_code', 'KURSI-'.$tokens[2])->count());
    }

    public function test_the_package_is_locked_once_the_schedule_has_started(): void
    {
        $operator = User::factory()->operator()->create();
        $group = $this->group(capacity: 2);
        $original = $group->test_config_id;
        $other = $this->mixedConfig();

        Livewire::actingAs($operator)
            ->test(EditExamGroup::class, ['record' => $group->getRouteKey()])
            ->assertFormFieldIsDisabled('test_config_id');

        $this->assertSame('Jam pelaksanaan sudah lewat.', app(ExamGroupPlanner::class)->lockReason($group));

        $group->forceFill(['test_config_id' => $other->id])->save();
        $this->expectExceptionMessage('Paket tidak bisa diganti');
        app(ExamGroupPlanner::class)->sync($group, $original);
    }

    public function test_an_unstarted_schedule_can_be_deleted_with_its_seats(): void
    {
        $operator = User::factory()->operator()->create();
        $group = $this->group(capacity: 3);
        $group->forceFill(['starts_at' => Carbon::now()->addDay()])->save();
        $token = $group->testSessions()->value('access_token');
        $participants = Participant::query()->count();

        Livewire::actingAs($operator)
            ->test(ListExamGroups::class)
            ->assertTableActionVisible('delete', $group)
            ->callTableAction('delete', $group);

        $this->assertModelMissing($group);
        $this->assertSame(0, TestSession::query()->where('exam_group_id', $group->id)->count());
        $this->assertSame($participants - 3, Participant::query()->count());
        $this->get("/t/{$token}")->assertNotFound();
    }

    public function test_a_started_or_used_schedule_cannot_be_deleted(): void
    {
        $operator = User::factory()->operator()->create();
        $started = $this->group(capacity: 1);

        $future = $this->group(capacity: 2);
        $future->forceFill(['starts_at' => Carbon::now()->addDay()])->save();
        $future->testSessions()->orderBy('id')->first()->forceFill(['opened_at' => Carbon::now()])->save();

        Livewire::actingAs($operator)
            ->test(ListExamGroups::class)
            ->assertTableActionHidden('delete', $started)
            ->assertTableActionHidden('delete', $future);

        $this->assertSame('1 kursi sudah dipakai siswa.', app(ExamGroupPlanner::class)->lockReason($future));
        $this->expectException(\RuntimeException::class);
        app(ExamGroupPlanner::class)->delete($started);
    }

    public function test_only_real_test_operators_see_the_delete_action(): void
    {
        $group = $this->group(capacity: 1);
        $group->forceFill(['starts_at' => Carbon::now()->addDay()])->save();

        foreach ([User::factory()->create(), User::factory()->pengawas()->create()] as $user) {
            $this->actingAs($user);
            $this->assertFalse(ExamGroupResource::canDelete($group));
        }

        $this->actingAs(User::factory()->operator()->create());
        $this->assertTrue(ExamGroupResource::canDelete($group));
    }

    public function test_an_operator_builds_a_mixed_package_like_a_simulation(): void
    {
        $operator = User::factory()->operator()->create();

        Livewire::actingAs($operator)
            ->test(CreateTestConfig::class)
            ->assertFormSet([
                'source' => TestConfigForm::SOURCE_MIXED,
                'grade_share_x' => 34,
                'grade_share_xi' => 33,
                'grade_share_xii' => 33,
            ])
            ->fillForm([
                'name' => 'Gabungan uji',
                'grade_share_x' => 50,
                'grade_share_xi' => 0,
                'grade_share_xii' => 50,
                'pool_size' => 4,
                'min_items' => 2,
                'max_items' => 4,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $config = TestConfig::query()->where('name', 'Gabungan uji')->firstOrFail();
        $this->assertTrue($config->usesGradeShares());
        $this->assertNotNull($config->item_bank_id);

        $grades = $config->packageItems()->with('itemBank')->get()->countBy(fn (Item $item): string => $item->itemBank->grade)->all();
        ksort($grades);
        $this->assertSame(['X' => 2, 'XII' => 2], $grades);
    }

    public function test_package_shares_must_total_one_hundred(): void
    {
        Livewire::actingAs(User::factory()->operator()->create())
            ->test(CreateTestConfig::class)
            ->fillForm(['name' => 'Salah', 'grade_share_x' => 50, 'grade_share_xi' => 20, 'grade_share_xii' => 20, 'pool_size' => 30])
            ->call('create')
            ->assertHasFormErrors(['grade_share_xii']);
    }

    public function test_a_single_bank_package_is_still_possible(): void
    {
        $bank = ItemBank::query()->where('grade', 'X')->firstOrFail();

        Livewire::actingAs(User::factory()->operator()->create())
            ->test(CreateTestConfig::class)
            ->fillForm(['name' => 'Hanya X', 'source' => TestConfigForm::SOURCE_BANK, 'item_bank_id' => $bank->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $config = TestConfig::query()->where('name', 'Hanya X')->firstOrFail();
        $this->assertFalse($config->usesGradeShares());
        $this->assertSame($bank->id, $config->item_bank_id);
    }

    private function mixedConfig(): TestConfig
    {
        $fromX = Item::query()->whereRelation('itemBank', 'grade', 'X')->orderBy('code')->limit(2)->pluck('id');
        $fromXi = Item::query()->whereRelation('itemBank', 'grade', 'XI')->orderBy('code')->limit(2)->pluck('id');
        $bank = ItemBank::query()->where('grade', 'XI')->firstOrFail();

        $config = TestConfig::query()->create(array_merge(TestConfigProvisioner::adaptiveAttributes(), [
            'item_bank_id' => $bank->id,
            'name' => 'Campuran uji',
            'min_items' => 4,
            'max_items' => 4,
        ]));

        $config->packageItems()->sync($fromX->concat($fromXi)->all());

        return $config;
    }

    private function sessionFor(TestConfig $config): TestSession
    {
        $school = School::query()->firstOrCreate(['name' => 'SMA Uji'], ['city' => 'Tasikmalaya']);
        $participant = $school->participants()->create([
            'class_name' => 'XI IPS 1',
            'student_code' => 'MIX01',
            'display_name' => 'Peserta Campuran',
        ]);

        return TestSession::query()->create([
            'participant_id' => $participant->id,
            'test_config_id' => $config->id,
            'item_bank_id' => $config->item_bank_id,
            'access_token' => 'CAMPURAN',
            'rng_seed' => 20260921,
            'status' => 'pending',
        ]);
    }

    private function group(int $capacity, ?User $supervisor = null): ExamGroup
    {
        $school = School::query()->firstOrCreate(['name' => 'SMA Uji'], ['city' => 'Tasikmalaya']);
        $bank = ItemBank::query()->where('grade', 'XI')->firstOrFail();
        $config = TestConfig::query()->where('item_bank_id', $bank->id)->firstOrFail();
        $supervisor ??= User::factory()->pengawas()->create();

        $group = ExamGroup::query()->create([
            'school_id' => $school->id,
            'name' => 'Ruang A sesi pagi',
            'room' => 'A-'.$capacity,
            'starts_at' => Carbon::now()->subMinute(),
            'supervisor_id' => $supervisor->id,
            'test_config_id' => $config->id,
            'capacity' => $capacity,
        ]);

        app(ExamGroupSeater::class)->fill($group);

        return $group->fresh(['testSessions', 'school', 'testConfig']);
    }
}
