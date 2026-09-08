<?php

declare(strict_types=1);

namespace App\CAT;

use InvalidArgumentException;

/**
 * Grid kuadratur tetap untuk EAP, SPEC §2: 61 titik pada θ ∈ [−4, +4]
 * dengan prior normal yang sudah dinormalkan.
 *
 * Titik dan prior dihitung sekali di konstruktor lalu dipakai ulang — grid ini
 * dievaluasi pada setiap pemilihan butir dan setiap estimasi ulang.
 */
final class QuadratureGrid
{
    /** @var list<float> */
    private array $points;

    /** @var list<float> */
    private array $priorWeights;

    /** @var list<float> */
    private array $logPriorWeights;

    public function __construct(
        private readonly float $min = -4.0,
        private readonly float $max = 4.0,
        private readonly int $size = 61,
        float $priorMean = 0.0,
        float $priorSd = 1.0,
    ) {
        if ($this->size < 2) {
            throw new InvalidArgumentException("Grid butuh minimal 2 titik, diberi {$this->size}.");
        }

        if ($priorSd <= 0.0) {
            throw new InvalidArgumentException("Simpangan baku prior harus positif, diberi {$priorSd}.");
        }

        $step = ($this->max - $this->min) / ($this->size - 1);
        $points = [];
        $density = [];

        for ($q = 0; $q < $this->size; $q++) {
            $theta = $this->min + $q * $step;
            $z = ($theta - $priorMean) / $priorSd;

            $points[] = $theta;
            $density[] = exp(-0.5 * $z * $z);
        }

        $total = array_sum($density);

        $this->points = $points;
        $this->priorWeights = array_map(static fn (float $d): float => $d / $total, $density);
        $this->logPriorWeights = array_map(static fn (float $w): float => log($w), $this->priorWeights);
    }

    /** @return list<float> */
    public function points(): array
    {
        return $this->points;
    }

    /** Prior yang sudah dinormalkan sehingga jumlahnya 1. @return list<float> */
    public function priorWeights(): array
    {
        return $this->priorWeights;
    }

    /** @return list<float> */
    public function logPriorWeights(): array
    {
        return $this->logPriorWeights;
    }

    public function size(): int
    {
        return $this->size;
    }
}
