<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CAT\ResponseModel;
use App\Models\SessionItem;
use App\Models\TestSession;
use App\Services\CatSession;
use App\Services\StudentFeedbackBuilder;
use App\Support\SessionState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class StudentFeedbackTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_higher_ability_sits_higher_on_the_map(): void
    {
        $this->assertLessThan(
            StudentFeedbackBuilder::logitToY(-1.0, -3.0, 3.0),
            StudentFeedbackBuilder::logitToY(1.0, -3.0, 3.0),
        );
    }

    public function test_test_information_is_the_sum_of_item_information_at_the_estimate(): void
    {
        $session = $this->runFullSession();
        $feedback = (new StudentFeedbackBuilder)->for($session->fresh());
        $ability = (float) $session->fresh()->theta;

        $rows = SessionItem::query()
            ->where('test_session_id', $session->id)
            ->whereNotNull('response_label')
            ->with('itemParameter')
            ->orderBy('sequence')
            ->get();

        $sum = $rows->sum(
            fn (SessionItem $row): float => (new ResponseModel($row->itemParameter->toCat()))->information($ability),
        );

        $this->assertEqualsWithDelta($sum, $feedback->testInformation, 0.015);
        $this->assertCount($rows->count(), $feedback->items);
        $this->assertNotEmpty($feedback->dimensions);
        $this->assertNotSame('', $feedback->category);

        foreach ($feedback->items as $item) {
            $this->assertArrayNotHasKey('is_correct', $item);
            $this->assertArrayNotHasKey('theta', $item);
            $this->assertArrayHasKey('information', $item);
            $this->assertArrayHasKey('map_y', $item);
        }
    }

    private function runFullSession(): TestSession
    {
        $session = $this->makeSession();
        $cat = app(CatSession::class);
        $state = $cat->start($session);
        $guard = 0;

        while (! $state->isFinished() && $guard++ < 60) {
            $state = $this->answerFirstOption($cat, $session, $state);
        }

        $this->assertTrue($state->isFinished());

        return $session->fresh();
    }

    private function answerFirstOption(CatSession $cat, TestSession $session, SessionState $state): SessionState
    {
        return $cat->answer(
            $session->fresh(),
            $state->item->sequence,
            $state->item->options[0]['label'],
        );
    }
}
