<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CAT\ItemParameter;
use App\CAT\ResponseModel;
use PHPUnit\Framework\TestCase;

class CatResponseModelTest extends TestCase
{
    public function test_probability_at_theta_equal_to_b_is_the_midpoint_above_the_guessing_floor(): void
    {
        foreach ([0.0, 0.20, 0.25] as $c) {
            $model = new ResponseModel(new ItemParameter(a: 1.2, b: 0.7, c: $c));

            $this->assertEqualsWithDelta($c + (1.0 - $c) / 2.0, $model->probability(0.7), 1e-12);
        }
    }

    public function test_probability_rises_with_theta_and_stays_between_c_and_one(): void
    {
        $model = new ResponseModel(new ItemParameter(a: 1.0, b: 0.0, c: 0.20));
        $previous = 0.0;

        foreach (range(-4, 4) as $theta) {
            $p = $model->probability((float) $theta);

            $this->assertGreaterThan($previous, $p);
            $this->assertGreaterThan(0.20, $p);
            $this->assertLessThan(1.0, $p);

            $previous = $p;
        }
    }

    public function test_information_peaks_at_b_when_there_is_no_guessing(): void
    {
        $b = 0.6;
        $model = new ResponseModel(new ItemParameter(a: 1.4, b: $b, c: 0.0));
        $peak = $model->information($b);

        foreach ([-2.0, -0.5, 0.2, 0.5, 0.7, 1.2, 2.5] as $theta) {
            $this->assertLessThan($peak, $model->information($theta));
        }
    }

    public function test_a_more_discriminating_item_carries_more_information_at_its_difficulty(): void
    {
        $low = new ResponseModel(new ItemParameter(a: 0.6, b: 0.0));
        $high = new ResponseModel(new ItemParameter(a: 1.8, b: 0.0));

        $this->assertGreaterThan($low->information(0.0), $high->information(0.0));
    }

    public function test_the_scaling_constant_stays_at_the_documented_value(): void
    {
        $this->assertSame(1.7, ResponseModel::D);
    }
}
