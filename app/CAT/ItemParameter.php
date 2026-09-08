<?php

declare(strict_types=1);

namespace App\CAT;

use InvalidArgumentException;

/**
 * Parameter butir sebagai nilai, lepas dari basis data.
 *
 * Model 2PL dengan asimtot bawah c dipaku (SPEC §1) — formalnya 3PL dengan c
 * tidak diestimasi.
 */
final readonly class ItemParameter
{
    public function __construct(
        public float $a,
        public float $b,
        public float $c = 0.20,
    ) {
        if ($a <= 0.0) {
            throw new InvalidArgumentException("Daya beda a harus positif, diberi {$a}.");
        }

        if ($c < 0.0 || $c >= 1.0) {
            throw new InvalidArgumentException("Asimtot bawah c harus di [0, 1), diberi {$c}.");
        }
    }
}
