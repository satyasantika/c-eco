<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\SequenceConflictException;
use App\Exceptions\SessionCompletedException;
use App\Models\SessionEvent;
use App\Models\SessionItem;
use App\Models\TestSession;
use App\Services\CatSession;
use App\Support\SessionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class CatSessionTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    private CatSession $cat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
        $this->cat = new CatSession;
    }

    public function test_start_presents_the_first_item_and_never_leaks_the_key(): void
    {
        $session = $this->makeSession();

        $state = $this->cat->start($session);

        $this->assertSame('in_progress', $state->session->status);
        $this->assertNotNull($state->item);
        $this->assertSame(1, $state->item->sequence);
        $this->assertCount(5, $state->item->options);

        $payload = json_encode($state->toArray());
        $this->assertStringNotContainsString('is_key', (string) $payload);
        $this->assertStringNotContainsString('is_correct', (string) $payload);
    }

    public function test_start_is_idempotent_on_a_running_session(): void
    {
        $session = $this->makeSession();

        $first = $this->cat->start($session);
        $second = $this->cat->start($session->fresh());

        $this->assertSame($first->item->sequence, $second->item->sequence);
        $this->assertSame(1, SessionItem::query()->count());
    }

    /** SPEC §10 uji #2. */
    public function test_resending_the_same_answer_three_times_changes_nothing(): void
    {
        $session = $this->makeSession();
        $state = $this->cat->start($session);
        $label = $state->item->options[0]['label'];

        $this->cat->answer($session->fresh(), 1, $label);

        $afterFirst = TestSession::query()->find($session->id);
        $thetaAfterFirst = $afterFirst->theta;
        $exposureAfterFirst = $this->exposureRows();

        $second = $this->cat->answer($session->fresh(), 1, $label);
        $third = $this->cat->answer($session->fresh(), 1, $label);

        $this->assertTrue($second->duplicate);
        $this->assertTrue($third->duplicate);

        $this->assertSame(1, SessionItem::query()->where('sequence', 1)->count());
        $this->assertSame(1, TestSession::query()->find($session->id)->items_administered);
        $this->assertSame($thetaAfterFirst, TestSession::query()->find($session->id)->theta);
        $this->assertSame($exposureAfterFirst, $this->exposureRows());
        $this->assertSame(2, SessionEvent::query()->where('type', 'duplicate_submit')->count());
    }

    /** SPEC §10 uji #3. */
    public function test_a_different_answer_on_the_same_sequence_is_a_conflict(): void
    {
        $session = $this->makeSession();
        $state = $this->cat->start($session);

        $this->cat->answer($session->fresh(), 1, $state->item->options[0]['label']);

        $this->expectException(SequenceConflictException::class);

        $this->cat->answer($session->fresh(), 1, $state->item->options[1]['label']);
    }

    public function test_a_sequence_that_was_never_presented_is_rejected(): void
    {
        $session = $this->makeSession();
        $this->cat->start($session);

        $this->expectException(SequenceConflictException::class);

        $this->cat->answer($session->fresh(), 7, 'A');
    }

    public function test_a_completed_session_refuses_further_answers(): void
    {
        $session = $this->runFullSession();

        $this->assertSame('completed', $session->fresh()->status);
        $this->expectException(SessionCompletedException::class);

        $this->cat->answer($session->fresh(), 1, 'A');
    }

    /** R4: satu-satunya yang membuat data 21 September bisa dianalisis ulang. */
    public function test_every_session_item_records_the_parameter_it_was_scored_with(): void
    {
        $this->runFullSession();

        $this->assertSame(0, SessionItem::query()->whereNull('item_parameter_id')->count());
        $this->assertGreaterThan(0, SessionItem::query()->count());
    }

    public function test_a_session_stops_between_min_and_max_items(): void
    {
        $session = $this->runFullSession();
        $config = $session->fresh()->testConfig;

        $administered = $session->fresh()->items_administered;

        $this->assertGreaterThanOrEqual($config->min_items, $administered);
        $this->assertLessThanOrEqual($config->max_items, $administered);
        $this->assertSame($administered, SessionItem::query()->whereNotNull('response_label')->count());
    }

    public function test_no_item_repeats_within_a_session(): void
    {
        $this->runFullSession();

        $itemIds = SessionItem::query()->pluck('item_id')->all();

        $this->assertSame(count($itemIds), count(array_unique($itemIds)));
    }

    public function test_options_are_shuffled_and_map_back_to_the_original_labels(): void
    {
        $session = $this->makeSession();
        $this->cat->start($session);

        $row = SessionItem::query()->where('sequence', 1)->firstOrFail();
        $permutation = $row->option_permutation_json;

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], array_keys($permutation));
        $this->assertSame(['A', 'B', 'C', 'D', 'E'], collect($permutation)->sort()->values()->all());
    }

    public function test_the_finished_session_reports_a_descriptive_dimension_profile(): void
    {
        $session = $this->runFullSession();
        $state = $this->cat->state($session->fresh());

        $this->assertTrue($state->isFinished());
        $this->assertNotNull($state->result);
        $this->assertArrayHasKey('t_score', $state->result);
        $this->assertArrayHasKey('dimension_profile', $state->result);
        $this->assertStringContainsString('deskriptif', $state->result['dimension_profile_note']);
        $this->assertNotEmpty($state->result['dimension_profile']);
    }

    /** SPEC §10 uji #8: server memproses jawaban, respons tidak sampai ke klien. */
    public function test_state_returns_the_waiting_item_after_a_lost_response(): void
    {
        $session = $this->makeSession();
        $state = $this->cat->start($session);

        // Server sudah menyimpan jawaban ini; klien tidak pernah menerima balasannya.
        $this->cat->answer($session->fresh(), 1, $state->item->options[0]['label']);

        $recovered = $this->cat->state($session->fresh());

        $this->assertSame(2, $recovered->item->sequence);
        $this->assertSame(1, SessionItem::query()->where('sequence', 1)->count());
        $this->assertSame(1, SessionItem::query()->whereNotNull('response_label')->count());
    }

    public function test_state_on_an_untouched_session_reports_pending_without_creating_items(): void
    {
        $session = $this->makeSession();

        $state = $this->cat->state($session);

        $this->assertSame('pending', $state->session->status);
        $this->assertNull($state->item);
        $this->assertSame(0, SessionItem::query()->count());
    }

    private function runFullSession(string $token = 'ABCD2345'): TestSession
    {
        $session = $this->makeSession(token: $token);
        $state = $this->cat->start($session);

        $guard = 0;

        while (! $state->isFinished() && $guard++ < 60) {
            $state = $this->answerFirstOption($session, $state);
        }

        return $session;
    }

    private function answerFirstOption(TestSession $session, SessionState $state): SessionState
    {
        return $this->cat->answer(
            $session->fresh(),
            $state->item->sequence,
            $state->item->options[0]['label'],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exposureRows(): array
    {
        return DB::table('exposure_counters')
            ->orderBy('item_id')
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->all();
    }
}
