<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Aturan berhenti, SPEC §4.
 *
 * Angkanya hidup di test_configs, bukan di kode: simulasi langkah 09 dapat
 * mengubahnya tanpa menyentuh mesin.
 */
final readonly class StoppingRule
{
    public function __construct(
        public int $minItems,
        public int $maxItems,
        public float $seTarget,
    ) {}

    public function shouldStop(int $administered, float $se, int $remainingCandidates): bool
    {
        return $this->reason($administered, $se, $remainingCandidates) !== null;
    }

    /**
     * @return 'max_items'|'bank_exhausted'|'se_target'|null
     */
    public function reason(int $administered, float $se, int $remainingCandidates): ?string
    {
        if ($administered >= $this->maxItems) {
            return 'max_items';
        }

        if ($remainingCandidates <= 0) {
            return 'bank_exhausted';
        }

        // Syarat minimum menang atas SE: profil tujuh dimensi butuh dasar,
        // dan SE bisa jatuh di bawah target setelah 5-6 butir.
        if ($administered >= $this->minItems && $se <= $this->seTarget) {
            return 'se_target';
        }

        return null;
    }
}
