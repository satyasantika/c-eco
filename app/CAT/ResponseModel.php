<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Model respons butir, SPEC §1.
 *
 *   P(θ)  = c + (1 − c) · P*(θ)          P*(θ) = 1 / (1 + exp(−D·a·(θ − b)))
 *   P'(θ) = D · a · (1 − c) · Q* · P*
 *   I(θ)  = P'(θ)² / (P(θ) · (1 − P(θ)))
 */
final readonly class ResponseModel
{
    /** Konstanta penskalaan logistik→normal, dipakai konsisten di seluruh sistem. */
    public const D = 1.7;

    public function __construct(private ItemParameter $parameter) {}

    public function probability(float $theta): float
    {
        return $this->parameter->c + (1.0 - $this->parameter->c) * $this->logistic($theta);
    }

    public function information(float $theta): float
    {
        $pStar = $this->logistic($theta);
        $derivative = self::D * $this->parameter->a * (1.0 - $this->parameter->c) * (1.0 - $pStar) * $pStar;

        $p = $this->parameter->c + (1.0 - $this->parameter->c) * $pStar;
        $denominator = $p * (1.0 - $p);

        if ($denominator <= 0.0) {
            return 0.0;
        }

        return ($derivative ** 2) / $denominator;
    }

    /** P*(θ): logistik tanpa asimtot bawah. */
    private function logistic(float $theta): float
    {
        return 1.0 / (1.0 + exp(-self::D * $this->parameter->a * ($theta - $this->parameter->b)));
    }
}
