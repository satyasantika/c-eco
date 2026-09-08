<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Umpan balik halaman terakhir siswa.
 *
 * Sengaja tidak membawa kunci jawaban atau nama medan peneliti (theta,
 * t_score) supaya HTML siswa tidak membocorkan jargon IRT atau kunci butir.
 */
final readonly class StudentFeedback
{
    /**
     * @param  list<array<string, mixed>>  $ticks
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $dimensions
     */
    public function __construct(
        public string $category,
        public bool $provisional,
        public int $itemsAdministered,
        public string $precisionLabel,
        public float $testInformation,
        public float $personY,
        public float $bandY,
        public float $bandHeight,
        public array $ticks,
        public array $items,
        public array $dimensions,
    ) {}
}
