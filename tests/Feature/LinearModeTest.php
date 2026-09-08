<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SessionItem;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Services\CatSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

/**
 * Aturan R10 — mode linear sebagai kondisi pembanding sekaligus jaring
 * pengaman kalau pemilih adaptif bermasalah pada hari-H.
 */
class LinearModeTest extends TestCase
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

    public function test_a_linear_session_always_runs_the_full_form(): void
    {
        $this->makeConfigLinear();
        $session = $this->runFullSession();

        $config = TestConfig::query()->firstOrFail();

        $this->assertSame($config->max_items, $session->fresh()->items_administered);
        $this->assertSame($config->max_items, SessionItem::query()->count());
    }

    public function test_a_linear_session_still_touches_every_dimension(): void
    {
        $this->makeConfigLinear();
        $this->runFullSession();

        $dimensions = SessionItem::query()
            ->join('items', 'session_items.item_id', '=', 'items.id')
            ->join('dimensions', 'items.dimension_id', '=', 'dimensions.id')
            ->distinct()
            ->pluck('dimensions.code');

        $this->assertCount(7, $dimensions);
    }

    /** Bentuk tetap: dua peserta menerima butir yang sama, urutan sama. */
    public function test_every_participant_receives_the_same_form(): void
    {
        $this->makeConfigLinear();

        $first = $this->administeredItems($this->runFullSession('AAAA3333', rngSeed: 111));
        $second = $this->administeredItems($this->runFullSession('BBBB4444', rngSeed: 999));

        $this->assertSame($first, $second);
    }

    public function test_linear_rows_carry_the_parameter_they_were_scored_with(): void
    {
        $this->makeConfigLinear();
        $this->runFullSession();

        $this->assertSame(0, SessionItem::query()->whereNull('item_parameter_id')->count());
        $this->assertSame(
            ['linear-fixed-form'],
            SessionItem::query()->distinct()->pluck('selection_rule')->all(),
        );
    }

    public function test_the_environment_flag_overrides_an_adaptive_config(): void
    {
        config(['cat.mode' => 'linear']);

        $session = $this->makeSession();

        $this->assertSame('adaptive', $session->testConfig->mode);

        $this->cat->start($session);

        $this->assertSame(
            'linear-fixed-form',
            SessionItem::query()->where('sequence', 1)->value('selection_rule'),
        );
    }

    /** Sesi berjalan tidak boleh berpindah mode di tengah jalan. */
    public function test_a_running_adaptive_session_stays_adaptive_when_the_flag_flips(): void
    {
        $session = $this->makeSession();
        $state = $this->cat->start($session);

        $this->assertStringStartsWith('MPWI', SessionItem::query()->where('sequence', 1)->value('selection_rule'));

        // Pengawas mengubah CAT_MODE di tengah pelaksanaan.
        config(['cat.mode' => 'linear']);

        $this->cat->answer($session->fresh(), 1, $state->item->options[0]['label']);

        $this->assertStringStartsWith(
            'MPWI',
            SessionItem::query()->where('sequence', 2)->value('selection_rule'),
        );
    }

    public function test_a_running_linear_session_stays_linear_when_the_flag_flips_back(): void
    {
        config(['cat.mode' => 'linear']);

        $session = $this->makeSession();
        $state = $this->cat->start($session);

        config(['cat.mode' => 'adaptive']);

        $this->cat->answer($session->fresh(), 1, $state->item->options[0]['label']);

        $this->assertSame(
            ['linear-fixed-form'],
            SessionItem::query()->distinct()->pluck('selection_rule')->all(),
        );
    }

    /** Idempotensi tidak boleh bergantung pada mode. */
    public function test_resending_an_answer_in_linear_mode_changes_nothing(): void
    {
        $this->makeConfigLinear();

        $session = $this->makeSession();
        $state = $this->cat->start($session);
        $label = $state->item->options[0]['label'];

        $this->cat->answer($session->fresh(), 1, $label);
        $duplicate = $this->cat->answer($session->fresh(), 1, $label);

        $this->assertTrue($duplicate->duplicate);
        $this->assertSame(1, TestSession::query()->find($session->id)->items_administered);
    }

    private function makeConfigLinear(): void
    {
        TestConfig::query()->update(['mode' => 'linear']);
    }

    private function runFullSession(string $token = 'ABCD2345', int $rngSeed = 20260921): TestSession
    {
        $session = $this->makeSession(rngSeed: $rngSeed, token: $token);
        $state = $this->cat->start($session);

        $guard = 0;

        while (! $state->isFinished() && $guard++ < 60) {
            $state = $this->cat->answer(
                $session->fresh(),
                $state->item->sequence,
                $state->item->options[0]['label'],
            );
        }

        return $session;
    }

    /**
     * @return list<string>
     */
    private function administeredItems(TestSession $session): array
    {
        return SessionItem::query()
            ->where('test_session_id', $session->id)
            ->join('items', 'session_items.item_id', '=', 'items.id')
            ->orderBy('session_items.sequence')
            ->pluck('items.code')
            ->all();
    }
}
