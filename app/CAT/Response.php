<?php

declare(strict_types=1);

namespace App\CAT;

/** Satu jawaban yang sudah dinilai, sebagai nilai murni. */
final readonly class Response
{
    public function __construct(
        public ItemParameter $parameter,
        public bool $isCorrect,
    ) {}
}
