<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CAT\EapEstimator;
use App\CAT\ItemParameter;
use App\CAT\QuadratureGrid;
use App\CAT\Response;
use App\CAT\ResponseModel;
use PHPUnit\Framework\TestCase;

class CatEapEstimatorTest extends TestCase
{
    public function test_the_grid_matches_the_specification(): void
    {
        $grid = new QuadratureGrid;

        $this->assertSame(61, $grid->size());
        $this->assertSame(-4.0, $grid->points()[0]);
        $this->assertEqualsWithDelta(4.0, $grid->points()[60], 1e-12);
        $this->assertEqualsWithDelta(1.0, array_sum($grid->priorWeights()), 1e-12);
    }

    public function test_without_responses_the_estimate_is_the_prior(): void
    {
        $estimate = (new EapEstimator)->estimate([]);

        $this->assertEqualsWithDelta(0.0, $estimate->theta, 1e-12);
        $this->assertEqualsWithDelta(1.0, $estimate->se, 0.01);
    }

    /** Alasan memakai EAP: MLE tidak terdefinisi di sini. */
    public function test_an_all_correct_pattern_gives_a_finite_positive_theta(): void
    {
        $estimate = (new EapEstimator)->estimate($this->pattern(10, fn (): bool => true));

        $this->assertTrue(is_finite($estimate->theta));
        $this->assertGreaterThan(0.0, $estimate->theta);
        $this->assertLessThan(4.0, $estimate->theta);
    }

    public function test_an_all_wrong_pattern_gives_a_finite_negative_theta(): void
    {
        $estimate = (new EapEstimator)->estimate($this->pattern(10, fn (): bool => false));

        $this->assertTrue(is_finite($estimate->theta));
        $this->assertLessThan(0.0, $estimate->theta);
        $this->assertGreaterThan(-4.0, $estimate->theta);
    }

    public function test_se_falls_monotonically_as_a_consistent_pattern_grows(): void
    {
        $estimator = new EapEstimator;
        $responses = [];
        $previous = $estimator->estimate([])->se;

        // Pola Guttman terhadap θ = 0.5: benar untuk butir yang lebih mudah,
        // salah untuk yang lebih sukar.
        foreach ([-1.5, -1.0, -0.5, 0.0, 1.0, 1.5, 2.0, -0.8, 1.2, -0.2] as $b) {
            $responses[] = new Response(new ItemParameter(a: 1.2, b: $b, c: 0.0), $b < 0.5);
            $se = $estimator->estimate($responses)->se;

            $this->assertLessThan($previous, $se);
            $previous = $se;
        }
    }

    public function test_a_more_discriminating_item_cuts_se_further(): void
    {
        $estimator = new EapEstimator;

        $weak = $estimator->estimate([new Response(new ItemParameter(a: 0.5, b: 0.0, c: 0.0), true)]);
        $strong = $estimator->estimate([new Response(new ItemParameter(a: 2.5, b: 0.0, c: 0.0), true)]);

        $this->assertLessThan($weak->se, $strong->se);
    }

    /** Di atas 25 butir likelihood dihitung pada skala log; hasilnya harus tetap sama. */
    public function test_the_log_scale_path_agrees_with_the_direct_product(): void
    {
        $responses = $this->pattern(30, static fn (int $i): bool => $i % 3 !== 0);

        $estimate = (new EapEstimator)->estimate($responses);
        $reference = $this->eapByDirectProduct($responses);

        $this->assertEqualsWithDelta($reference['theta'], $estimate->theta, 1e-12);
        $this->assertEqualsWithDelta($reference['se'], $estimate->se, 1e-12);
    }

    public function test_a_long_all_correct_pattern_does_not_underflow(): void
    {
        $estimate = (new EapEstimator)->estimate($this->pattern(60, fn (): bool => true));

        $this->assertTrue(is_finite($estimate->theta));
        $this->assertGreaterThan(0.0, $estimate->theta);
        $this->assertGreaterThan(0.0, $estimate->se);
    }

    public function test_t_score_is_clamped_to_the_reporting_range(): void
    {
        $this->assertSame(50.0, (new EapEstimator)->estimate([])->tScore());
    }

    /**
     * Perkalian langsung pada grid, tanpa pengaman skala log — pembanding
     * independen untuk jalur log di EapEstimator.
     *
     * @param  list<Response>  $responses
     * @return array{theta: float, se: float}
     */
    private function eapByDirectProduct(array $responses): array
    {
        $grid = new QuadratureGrid;
        $points = $grid->points();
        $weights = $grid->priorWeights();

        foreach ($responses as $response) {
            $model = new ResponseModel($response->parameter);

            foreach ($points as $q => $theta) {
                $p = $model->probability($theta);
                $weights[$q] *= $response->isCorrect ? $p : 1.0 - $p;
            }
        }

        $total = array_sum($weights);
        $weights = array_map(static fn (float $w): float => $w / $total, $weights);

        $theta = 0.0;

        foreach ($points as $q => $point) {
            $theta += $point * $weights[$q];
        }

        $variance = 0.0;

        foreach ($points as $q => $point) {
            $variance += (($point - $theta) ** 2) * $weights[$q];
        }

        return ['theta' => $theta, 'se' => sqrt($variance)];
    }

    /**
     * @param  callable(int): bool  $isCorrect
     * @return list<Response>
     */
    private function pattern(int $n, callable $isCorrect): array
    {
        $responses = [];

        for ($i = 0; $i < $n; $i++) {
            $b = -2.0 + 4.0 * ($i / max($n - 1, 1));
            $responses[] = new Response(new ItemParameter(a: 1.0, b: $b), $isCorrect($i));
        }

        return $responses;
    }
}
