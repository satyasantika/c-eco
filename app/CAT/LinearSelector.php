<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Mode linear, aturan R10.
 *
 * Bentuk tetap: setiap peserta menerima butir yang sama dalam urutan yang sama.
 * Ini kondisi pembanding untuk mengukur nilai tambah CAT, sekaligus jaring
 * pengaman kalau pemilih adaptif ternyata bermasalah pada hari-H — mode diganti
 * lewat satu variabel lingkungan, tanpa deploy ulang.
 *
 * Penyeimbang isi tetap dipakai supaya ketujuh dimensi tersentuh. Yang hilang
 * hanyalah adaptasi terhadap jawaban peserta.
 */
final readonly class LinearSelector
{
    public function __construct(
        private ContentBalancer $balancer,
        private int $formLength,
    ) {}

    /**
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $administeredDimensions
     */
    public function select(array $candidates, array $administeredDimensions, int $sequence, float $theta): SelectionResult
    {
        if ($candidates === []) {
            throw new NoCandidateException('Bank habis: tidak ada butir tersisa untuk sesi ini.');
        }

        [$pool, $dimension] = $this->neediestDimension($candidates, $administeredDimensions);
        $target = $this->targetDifficulty($sequence);

        // Butir dengan b terdekat ke target, item_id sebagai pemecah seri agar
        // bentuk tetapnya benar-benar sama untuk setiap peserta.
        usort($pool, static fn (Candidate $x, Candidate $y): int => abs($x->parameter->b - $target) <=> abs($y->parameter->b - $target)
            ?: $x->itemId <=> $y->itemId);

        $chosen = $pool[0];

        return new SelectionResult(
            candidate: $chosen,
            information: (new ResponseModel($chosen->parameter))->information($theta),
            selectionRule: 'linear-fixed-form',
            candidatePool: [['item_id' => $chosen->itemId, 'information' => 0.0]],
            balancedDimension: $dimension,
        );
    }

    /** Kesukaran menyapu dari mudah ke sukar sepanjang bentuk tes. */
    private function targetDifficulty(int $sequence): float
    {
        if ($this->formLength <= 1) {
            return 0.0;
        }

        $position = min($sequence, $this->formLength) - 1;

        return -1.5 + 3.0 * ($position / ($this->formLength - 1));
    }

    /**
     * @param  list<Candidate>  $candidates
     * @param  list<string>  $administeredDimensions
     * @return array{0: list<Candidate>, 1: string}
     */
    private function neediestDimension(array $candidates, array $administeredDimensions): array
    {
        foreach ($this->balancer->orderedByDeficit($administeredDimensions) as $dimension) {
            $subset = array_values(array_filter(
                $candidates,
                static fn (Candidate $c): bool => $c->dimension === $dimension
            ));

            if ($subset !== []) {
                return [$subset, $dimension];
            }
        }

        return [$candidates, $candidates[0]->dimension];
    }
}
