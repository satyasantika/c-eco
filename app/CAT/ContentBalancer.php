<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Penyeimbang isi Kingsbury–Zara, SPEC §3 langkah 2.
 *
 * Target proporsi tiap dimensi adalah proporsinya di dalam bank jenjang itu,
 * jadi tes 20 butir tetap mewakili ketujuh dimensi walaupun kriteria informasi
 * cenderung menarik butir dari dimensi yang sama.
 */
final readonly class ContentBalancer
{
    /**
     * @param  array<string, float>  $targetProportions
     */
    public function __construct(private array $targetProportions) {}

    /**
     * @param  list<Candidate>  $bank
     */
    public static function fromBank(array $bank): self
    {
        $counts = [];

        foreach ($bank as $candidate) {
            $counts[$candidate->dimension] = ($counts[$candidate->dimension] ?? 0) + 1;
        }

        return self::fromCounts($counts);
    }

    /**
     * @param  array<string, int>  $counts
     */
    public static function fromCounts(array $counts): self
    {
        $total = array_sum($counts);

        if ($total === 0) {
            return new self([]);
        }

        return new self(array_map(static fn (int $n): float => $n / $total, $counts));
    }

    /**
     * Dimensi diurutkan dari defisit terbesar. Pemanggil menuruni daftar ini
     * sampai menemukan dimensi yang masih punya kandidat.
     *
     * @param  list<string>  $administered  dimensi butir yang sudah disajikan
     * @return list<string>
     */
    public function orderedByDeficit(array $administered): array
    {
        $total = count($administered);
        $seen = array_count_values($administered);
        $deficits = [];

        foreach ($this->targetProportions as $dimension => $target) {
            $observed = $total === 0 ? 0.0 : ($seen[$dimension] ?? 0) / $total;
            $deficits[$dimension] = $target - $observed;
        }

        // Urutan dimensi pada target dipakai sebagai pemecah seri supaya
        // hasilnya tetap dapat diulang.
        $order = array_flip(array_keys($this->targetProportions));

        uksort($deficits, static function (string $a, string $b) use ($deficits, $order): int {
            return $deficits[$b] <=> $deficits[$a] ?: $order[$a] <=> $order[$b];
        });

        return array_keys($deficits);
    }

    /**
     * @return array<string, float>
     */
    public function targetProportions(): array
    {
        return $this->targetProportions;
    }
}
