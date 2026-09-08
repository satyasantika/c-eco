<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MixedPackageComposer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MixedPackageComposerTest extends TestCase
{
    public function test_allocate_splits_a_round_pool_by_largest_remainder(): void
    {
        $this->assertSame(
            ['X' => 12, 'XI' => 9, 'XII' => 9],
            MixedPackageComposer::allocate(30, 40, 30, 30),
        );
        $this->assertSame(
            ['X' => 4, 'XI' => 3, 'XII' => 3],
            MixedPackageComposer::allocate(10, 40, 30, 30),
        );
        $this->assertSame(
            ['X' => 20, 'XI' => 0, 'XII' => 0],
            MixedPackageComposer::allocate(20, 100, 0, 0),
        );
    }

    public function test_allocate_rejects_shares_that_do_not_sum_to_one_hundred(): void
    {
        $this->expectException(RuntimeException::class);
        MixedPackageComposer::allocate(20, 40, 40, 10);
    }
}
