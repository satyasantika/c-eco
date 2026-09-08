<?php

declare(strict_types=1);

namespace App\CAT;

final readonly class SelectionResult
{
    /**
     * @param  list<array{item_id: int, information: float}>  $candidatePool
     */
    public function __construct(
        public Candidate $candidate,
        public float $information,
        public string $selectionRule,
        public array $candidatePool,
        public string $balancedDimension,
    ) {}
}
