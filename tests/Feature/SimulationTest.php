<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\CAT\StoppingRule;
use App\Models\SimulationResult;
use App\Models\SimulationRun;
use App\Models\TestConfig;
use App\Services\SimulationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class SimulationTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_run_stores_one_result_per_replication(): void
    {
        $run = $this->simulate([-1.0, 0.0, 1.0], replications: 5);

        $this->assertSame(15, SimulationResult::query()->where('simulation_run_id', $run->id)->count());
        $this->assertNotNull($run->fresh()->finished_at);
    }

    public function test_the_same_seed_reproduces_the_same_results(): void
    {
        $first = $this->results($this->simulate([0.0], replications: 10, seed: 4242));
        $second = $this->results($this->simulate([0.0], replications: 10, seed: 4242));

        $this->assertSame($first, $second);
    }

    public function test_a_different_seed_produces_different_results(): void
    {
        $first = $this->results($this->simulate([0.0], replications: 10, seed: 4242));
        $second = $this->results($this->simulate([0.0], replications: 10, seed: 777));

        $this->assertNotSame($first, $second);
    }

    public function test_sessions_respect_the_stopping_rule_bounds(): void
    {
        $run = $this->simulate([-1.0, 0.0, 1.0], replications: 5);

        $lengths = SimulationResult::query()->where('simulation_run_id', $run->id)->pluck('n_items');

        $this->assertGreaterThanOrEqual(14, $lengths->min());
        $this->assertLessThanOrEqual(20, $lengths->max());
    }

    public function test_no_item_repeats_inside_a_simulated_session(): void
    {
        $run = $this->simulate([0.0], replications: 20);

        foreach (SimulationResult::query()->where('simulation_run_id', $run->id)->get() as $result) {
            $items = $result->items_json;

            $this->assertSame(count($items), count(array_unique($items)), "replikasi {$result->replication}");
        }
    }

    public function test_the_overrides_reach_the_engine(): void
    {
        $run = $this->simulate([0.0], replications: 5, rule: new StoppingRule(4, 6, 0.30));

        $lengths = SimulationResult::query()->where('simulation_run_id', $run->id)->pluck('n_items');

        $this->assertLessThanOrEqual(6, $lengths->max());
        $this->assertStringContainsString('max=6', $run->fresh()->theta_distribution);
    }

    /** Estimasi harus mengikuti theta yang sebenarnya, bukan mengambang di prior. */
    public function test_estimates_track_the_true_ability(): void
    {
        $run = $this->simulate([-1.5, 1.5], replications: 30);

        $low = SimulationResult::query()->where('simulation_run_id', $run->id)->where('true_theta', -1.5)->avg('est_theta');
        $high = SimulationResult::query()->where('simulation_run_id', $run->id)->where('true_theta', 1.5)->avg('est_theta');

        $this->assertLessThan(0.0, $low);
        $this->assertGreaterThan(0.0, $high);
        $this->assertGreaterThan(1.0, $high - $low);
    }

    /**
     * @param  list<float>  $thetaPoints
     */
    private function simulate(array $thetaPoints, int $replications, int $seed = 20260921, ?StoppingRule $rule = null): SimulationRun
    {
        return (new SimulationRunner)->run(
            config: TestConfig::query()->firstOrFail(),
            thetaPoints: $thetaPoints,
            replications: $replications,
            seed: $seed,
            rule: $rule,
        );
    }

    /**
     * @return list<array{float, float, int}>
     */
    private function results(SimulationRun $run): array
    {
        return SimulationResult::query()
            ->where('simulation_run_id', $run->id)
            ->orderBy('replication')
            ->get()
            ->map(static fn (SimulationResult $r): array => [$r->est_theta, $r->se, $r->n_items])
            ->all();
    }
}
