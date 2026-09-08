<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Estimasi kemampuan dengan EAP, SPEC §2.
 *
 * EAP dan bukan MLE: MLE tidak terdefinisi untuk pola semua-benar dan
 * semua-salah, yang pasti muncul pada butir-butir pertama setiap sesi.
 */
final class EapEstimator
{
    /** Di atas ambang ini likelihood dihitung pada skala log agar tidak underflow. */
    private const LOG_SCALE_THRESHOLD = 25;

    public function __construct(private readonly QuadratureGrid $grid = new QuadratureGrid) {}

    /**
     * @param  list<Response>  $responses
     */
    public function estimate(array $responses): ThetaEstimate
    {
        $weights = count($responses) > self::LOG_SCALE_THRESHOLD
            ? $this->posteriorViaLogScale($responses)
            : $this->posteriorViaProduct($responses);

        return $this->moments($weights);
    }

    /**
     * Posterior pada grid — dipakai pemilih butir MPWI (SPEC §3).
     *
     * @param  list<Response>  $responses
     * @return list<float>
     */
    public function posterior(array $responses): array
    {
        return count($responses) > self::LOG_SCALE_THRESHOLD
            ? $this->posteriorViaLogScale($responses)
            : $this->posteriorViaProduct($responses);
    }

    /**
     * @param  list<Response>  $responses
     * @return list<float>
     */
    private function posteriorViaProduct(array $responses): array
    {
        $weights = $this->grid->priorWeights();

        foreach ($responses as $response) {
            $model = new ResponseModel($response->parameter);

            foreach ($this->grid->points() as $q => $theta) {
                $p = $model->probability($theta);
                $weights[$q] *= $response->isCorrect ? $p : 1.0 - $p;
            }
        }

        return $this->normalize($weights);
    }

    /**
     * @param  list<Response>  $responses
     * @return list<float>
     */
    private function posteriorViaLogScale(array $responses): array
    {
        $logWeights = $this->grid->logPriorWeights();

        foreach ($responses as $response) {
            $model = new ResponseModel($response->parameter);

            foreach ($this->grid->points() as $q => $theta) {
                $p = $model->probability($theta);
                $likelihood = $response->isCorrect ? $p : 1.0 - $p;
                $logWeights[$q] += log(max($likelihood, PHP_FLOAT_MIN));
            }
        }

        // Kurangi maksimum sebelum exp: menggeser konstanta tidak mengubah
        // posterior yang ternormalkan, tapi menjauhkan hasilnya dari underflow.
        $shift = max($logWeights);

        return $this->normalize(array_map(
            static fn (float $logWeight): float => exp($logWeight - $shift),
            $logWeights
        ));
    }

    /**
     * @param  list<float>  $weights
     * @return list<float>
     */
    private function normalize(array $weights): array
    {
        $total = array_sum($weights);

        if ($total <= 0.0) {
            return $this->grid->priorWeights();
        }

        return array_map(static fn (float $w): float => $w / $total, $weights);
    }

    /**
     * @param  list<float>  $weights
     */
    private function moments(array $weights): ThetaEstimate
    {
        $points = $this->grid->points();
        $theta = 0.0;

        foreach ($points as $q => $point) {
            $theta += $point * $weights[$q];
        }

        $variance = 0.0;

        foreach ($points as $q => $point) {
            $variance += (($point - $theta) ** 2) * $weights[$q];
        }

        return new ThetaEstimate($theta, sqrt(max($variance, 0.0)));
    }
}
