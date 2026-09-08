<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\ThetaEstimate;
use App\Models\TestSession;

/**
 * Pelaporan hasil, SPEC §5.
 */
class SessionReporter
{
    /**
     * @return array<string, mixed>
     */
    public function report(TestSession $session): array
    {
        $theta = (float) ($session->theta ?? 0.0);
        $se = (float) ($session->se ?? 1.0);
        $estimate = new ThetaEstimate($theta, $se);
        $tScore = $estimate->tScore();

        return [
            'theta' => round($theta, 4),
            'se' => round($se, 4),
            't_score' => round($tScore, 2),
            'category' => $this->category($tScore),
            'category_is_provisional' => (bool) config('cat.categories_are_provisional'),
            'items_administered' => $session->items_administered,
            'dimension_profile' => $this->dimensionProfile($session),
            // Wajib ikut ke mana pun profil ini dibawa: dengan 2-4 butir per
            // dimensi, persentase ini bukan estimasi kemampuan per dimensi.
            'dimension_profile_note' =>
                'Persentase benar per dimensi bersifat deskriptif, bukan estimasi θ per dimensi. '.
                'Jumlah butir per dimensi terlalu sedikit untuk diperlakukan sebagai skor.',
        ];
    }

    private function category(float $tScore): string
    {
        foreach ((array) config('cat.categories') as $band) {
            if ($tScore >= $band['min_t']) {
                return $band['label'];
            }
        }

        return 'Tidak terkategori';
    }

    /**
     * @return list<array{dimension: string, answered: int, correct: int, percent_correct: float}>
     */
    private function dimensionProfile(TestSession $session): array
    {
        $rows = $session->sessionItems()
            ->whereNotNull('response_label')
            ->with('item.dimension:id,code,display_order')
            ->get()
            ->groupBy(fn ($sessionItem): string => $sessionItem->item->dimension->code);

        return $rows->map(function ($group, string $dimension): array {
            $answered = $group->count();
            $correct = $group->where('is_correct', true)->count();

            return [
                'dimension' => $dimension,
                'answered' => $answered,
                'correct' => $correct,
                'percent_correct' => $answered === 0 ? 0.0 : round(100 * $correct / $answered, 1),
            ];
        })->values()->all();
    }
}
