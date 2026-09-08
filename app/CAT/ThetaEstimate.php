<?php

declare(strict_types=1);

namespace App\CAT;

final readonly class ThetaEstimate
{
    public function __construct(
        public float $theta,
        public float $se,
    ) {}

    /** Skor-T, SPEC §5. */
    public function tScore(): float
    {
        return min(90.0, max(10.0, 50.0 + 10.0 * $this->theta));
    }
}
