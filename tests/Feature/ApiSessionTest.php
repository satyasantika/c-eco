<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SessionEvent;
use App\Models\SessionItem;
use App\Models\TestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class ApiSessionTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_start_returns_the_first_item_without_any_hint_of_the_key(): void
    {
        $session = $this->makeSession();

        $response = $this->postJson("/api/t/{$session->access_token}/start", [
            'device_uuid' => 'device-1',
            'screen_w' => 390,
            'effective_connection' => '4g',
        ]);

        $response->assertOk()
            ->assertJsonPath('session.status', 'in_progress')
            ->assertJsonPath('item.sequence', 1)
            ->assertJsonCount(5, 'item.options');

        $body = $response->getContent();

        $this->assertStringNotContainsString('is_key', (string) $body);
        $this->assertStringNotContainsString('is_correct', (string) $body);
    }

    public function test_start_is_idempotent(): void
    {
        $session = $this->makeSession();

        $this->postJson("/api/t/{$session->access_token}/start")->assertOk();
        $this->postJson("/api/t/{$session->access_token}/start")->assertOk()->assertJsonPath('item.sequence', 1);

        $this->assertSame(1, SessionItem::query()->count());
    }

    public function test_answering_moves_to_the_next_item(): void
    {
        $session = $this->makeSession();
        $start = $this->postJson("/api/t/{$session->access_token}/start")->json();

        $this->postJson("/api/t/{$session->access_token}/answer", [
            'sequence' => 1,
            'option' => $start['item']['options'][0]['label'],
            'client_ts' => 1758400000,
            'latency_ms' => 72310,
        ])->assertOk()->assertJsonPath('item.sequence', 2);
    }

    /** SPEC §7: kiriman ulang dengan jawaban sama tetap 200 dan ditandai duplicate. */
    public function test_resending_the_same_answer_is_answered_with_duplicate_true(): void
    {
        $session = $this->makeSession();
        $start = $this->postJson("/api/t/{$session->access_token}/start")->json();
        $label = $start['item']['options'][0]['label'];

        $this->postJson("/api/t/{$session->access_token}/answer", ['sequence' => 1, 'option' => $label])->assertOk();

        $this->postJson("/api/t/{$session->access_token}/answer", ['sequence' => 1, 'option' => $label])
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('item.sequence', 2);

        $this->assertSame(1, SessionItem::query()->whereNotNull('response_label')->count());
    }

    /** SPEC §10 uji #3. */
    public function test_a_conflicting_answer_returns_409(): void
    {
        $session = $this->makeSession();
        $start = $this->postJson("/api/t/{$session->access_token}/start")->json();

        $this->postJson("/api/t/{$session->access_token}/answer", [
            'sequence' => 1, 'option' => $start['item']['options'][0]['label'],
        ])->assertOk();

        $this->postJson("/api/t/{$session->access_token}/answer", [
            'sequence' => 1, 'option' => $start['item']['options'][1]['label'],
        ])->assertStatus(409)->assertJsonPath('error', 'sequence_conflict');
    }

    public function test_state_recovers_a_session_whose_response_never_reached_the_client(): void
    {
        $session = $this->makeSession();
        $start = $this->postJson("/api/t/{$session->access_token}/start")->json();

        // Server memproses jawaban ini; balasannya hilang di jaringan.
        $this->postJson("/api/t/{$session->access_token}/answer", [
            'sequence' => 1, 'option' => $start['item']['options'][0]['label'],
        ])->assertOk();

        $this->getJson("/api/t/{$session->access_token}/state")
            ->assertOk()
            ->assertJsonPath('session.status', 'in_progress')
            ->assertJsonPath('item.sequence', 2);
    }

    public function test_state_reports_pending_before_start(): void
    {
        $session = $this->makeSession();

        $this->getJson("/api/t/{$session->access_token}/state")
            ->assertOk()
            ->assertJsonPath('session.status', 'pending')
            ->assertJsonMissingPath('item');
    }

    public function test_event_is_always_accepted_and_never_blocks_the_test(): void
    {
        $session = $this->makeSession();

        $this->postJson("/api/t/{$session->access_token}/event", [
            'type' => 'visibility_hidden',
            'payload' => ['at' => 123],
        ])->assertNoContent();

        // Muatan tak masuk akal pun tidak boleh mengganggu alur tes.
        $this->postJson("/api/t/{$session->access_token}/event", ['type' => 'bukan-tipe'])->assertNoContent();

        $this->assertSame(1, SessionEvent::query()->count());
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->getJson('/api/t/ZZZZ9999/state')->assertNotFound();
    }

    public function test_a_finished_session_keeps_returning_its_result(): void
    {
        $session = $this->makeSession();
        $state = $this->postJson("/api/t/{$session->access_token}/start")->json();

        $guard = 0;

        while (isset($state['item']) && $guard++ < 60) {
            $state = $this->postJson("/api/t/{$session->access_token}/answer", [
                'sequence' => $state['item']['sequence'],
                'option' => $state['item']['options'][0]['label'],
            ])->json();
        }

        $this->assertTrue($state['finished']);
        $this->assertArrayHasKey('t_score', $state['result']);

        $this->getJson("/api/t/{$session->access_token}/state")
            ->assertOk()
            ->assertJsonPath('session.status', 'completed')
            ->assertJsonPath('finished', true);

        $this->assertSame('completed', TestSession::query()->find($session->id)->status);
    }
}
