<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CAT\Candidate;
use App\CAT\ContentBalancer;
use App\CAT\EapEstimator;
use App\CAT\ItemParameter;
use App\CAT\ItemSelector;
use App\CAT\NoCandidateException;
use App\CAT\Response;
use App\CAT\StoppingRule;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class CatItemSelectorTest extends TestCase
{
    private const DIMENSIONS = [
        'fluency', 'flexibility', 'originality', 'elaboration',
        'solutif', 'adaptif', 'prediktif',
    ];

    public function test_two_sessions_with_the_same_seed_administer_the_same_items(): void
    {
        $this->assertSame(
            $this->runSession(seed: 4242, length: 20),
            $this->runSession(seed: 4242, length: 20),
        );
    }

    public function test_different_seeds_spread_the_first_item_across_the_bank(): void
    {
        $firstItems = [];

        for ($seed = 1; $seed <= 50; $seed++) {
            $firstItems[] = $this->runSession(seed: $seed, length: 1)[0];
        }

        $this->assertGreaterThanOrEqual(3, count(array_unique($firstItems)));
    }

    public function test_no_item_is_administered_twice_in_a_session(): void
    {
        $administered = $this->runSession(seed: 7, length: 20);

        $this->assertSame($administered, array_values(array_unique($administered)));
        $this->assertCount(20, $administered);
    }

    /** SPEC §10 uji #4. */
    public function test_a_twenty_item_session_touches_every_dimension(): void
    {
        foreach ([1, 2, 3, 99, 20260921] as $seed) {
            $dimensions = $this->runSession(seed: $seed, length: 20, returnDimensions: true);

            $this->assertSame(
                [],
                array_diff(self::DIMENSIONS, array_unique($dimensions)),
                "seed {$seed} melewatkan satu dimensi"
            );
        }
    }

    public function test_the_first_item_comes_from_the_middle_of_the_scale(): void
    {
        $bank = $this->bank();
        $selector = new ItemSelector(ContentBalancer::fromBank($bank));

        for ($seed = 1; $seed <= 20; $seed++) {
            $result = $selector->select($bank, [], [], 1, $seed);

            $this->assertLessThanOrEqual(1.0, abs($result->candidate->parameter->b));
        }
    }

    public function test_it_switches_from_mpwi_to_mfi_after_the_fifth_item(): void
    {
        $bank = $this->bank();
        $selector = new ItemSelector(ContentBalancer::fromBank($bank));

        $this->assertStringStartsWith('MPWI', $selector->select($bank, [], [], 5, 11)->selectionRule);
        $this->assertStringStartsWith('MFI', $selector->select($bank, [], [], 6, 11)->selectionRule);
    }

    public function test_the_candidate_pool_is_the_top_k_by_information(): void
    {
        $bank = $this->bank();
        $selector = new ItemSelector(ContentBalancer::fromBank($bank));

        $first = $selector->select($bank, [], [], 1, 5);
        $later = $selector->select($bank, [], ['fluency'], 2, 5);

        $this->assertCount(5, $first->candidatePool);
        $this->assertCount(3, $later->candidatePool);

        $informations = array_column($first->candidatePool, 'information');
        $sorted = $informations;
        rsort($sorted);

        $this->assertSame($sorted, $informations);
        $this->assertContains($first->candidate->itemId, array_column($first->candidatePool, 'item_id'));
    }

    public function test_an_empty_bank_is_reported_rather_than_guessed_around(): void
    {
        $selector = new ItemSelector(ContentBalancer::fromBank($this->bank()));

        $this->expectException(NoCandidateException::class);

        $selector->select([], [], [], 1, 1);
    }

    public function test_the_balancer_orders_dimensions_by_deficit(): void
    {
        $balancer = ContentBalancer::fromCounts(['a' => 2, 'b' => 2]);

        $this->assertSame(['a', 'b'], $balancer->orderedByDeficit([]));
        $this->assertSame(['b', 'a'], $balancer->orderedByDeficit(['a']));
        $this->assertSame(['a', 'b'], $balancer->orderedByDeficit(['a', 'b', 'b']));
    }

    public function test_the_stopping_rule_respects_the_minimum_even_when_se_is_already_low(): void
    {
        $rule = new StoppingRule(minItems: 14, maxItems: 20, seTarget: 0.30);

        $this->assertFalse($rule->shouldStop(administered: 6, se: 0.10, remainingCandidates: 30));
        $this->assertFalse($rule->shouldStop(administered: 13, se: 0.10, remainingCandidates: 30));
        $this->assertSame('se_target', $rule->reason(administered: 14, se: 0.30, remainingCandidates: 30));
    }

    public function test_the_stopping_rule_stops_on_max_items_and_on_an_empty_bank(): void
    {
        $rule = new StoppingRule(minItems: 14, maxItems: 20, seTarget: 0.30);

        $this->assertSame('max_items', $rule->reason(administered: 20, se: 0.90, remainingCandidates: 30));
        $this->assertSame('bank_exhausted', $rule->reason(administered: 3, se: 0.90, remainingCandidates: 0));
        $this->assertNull($rule->reason(administered: 19, se: 0.90, remainingCandidates: 1));
    }

    /**
     * Menjalankan satu sesi sampai panjang tertentu, meniru cara CatSession
     * memanggil pemilih: kandidat menyusut, respons bertambah.
     *
     * @return list<int|string>
     */
    private function runSession(int $seed, int $length, bool $returnDimensions = false): array
    {
        $bank = $this->bank();
        $selector = new ItemSelector(ContentBalancer::fromBank($bank));
        $estimator = new EapEstimator;
        $truth = new Randomizer(new Mt19937($seed));

        $remaining = $bank;
        $responses = [];
        $dimensions = [];
        $administered = [];

        for ($sequence = 1; $sequence <= $length; $sequence++) {
            $result = $selector->select($remaining, $responses, $dimensions, $sequence, $seed);
            $candidate = $result->candidate;

            $administered[] = $candidate->itemId;
            $dimensions[] = $candidate->dimension;

            $remaining = array_values(array_filter(
                $remaining,
                static fn (Candidate $c): bool => $c->itemId !== $candidate->itemId
            ));

            // Jawaban tiruan yang tetap dapat diulang, hanya agar posterior bergerak.
            $responses[] = new Response($candidate->parameter, $truth->getInt(0, 1) === 1);
            $estimator->estimate($responses);
        }

        return $returnDimensions ? $dimensions : $administered;
    }

    /**
     * Bank tiruan 42 butir: 6 per dimensi, kesukaran tersebar merata.
     *
     * @return list<Candidate>
     */
    private function bank(): array
    {
        $bank = [];
        $id = 1;

        foreach (self::DIMENSIONS as $d => $dimension) {
            for ($i = 0; $i < 6; $i++) {
                $bank[] = new Candidate(
                    itemId: $id++,
                    dimension: $dimension,
                    parameter: new ItemParameter(
                        a: 0.8 + 0.1 * (($d + $i) % 8),
                        b: -2.0 + 0.8 * $i + 0.05 * $d,
                    ),
                );
            }
        }

        return $bank;
    }
}
