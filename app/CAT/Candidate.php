<?php

declare(strict_types=1);

namespace App\CAT;

/**
 * Butir yang masih boleh dipilih, dalam bentuk nilai murni.
 *
 * Sengaja bukan model Eloquent: pemilih butir harus bisa dijalankan jutaan kali
 * di simulasi Monte Carlo tanpa menyentuh basis data.
 */
final readonly class Candidate
{
    public function __construct(
        public int $itemId,
        public string $dimension,
        public ItemParameter $parameter,
    ) {}
}
