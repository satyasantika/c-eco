<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\Candidate;
use App\CAT\ContentBalancer;
use App\CAT\EapEstimator;
use App\CAT\ItemSelector;
use App\CAT\NoCandidateException;
use App\CAT\Response;
use App\CAT\ResponseModel;
use App\CAT\StoppingRule;
use App\Models\Item;
use App\Models\SimulationResult;
use App\Models\SimulationRun;
use App\Models\TestConfig;
use Illuminate\Support\Carbon;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Simulasi Monte Carlo, langkah 09.
 *
 * Memakai kelas App\CAT yang sama persis dengan sesi sungguhan. Menulis salinan
 * mesin untuk simulasi akan membuat angka di laporan ini tidak lagi menjelaskan
 * apa pun tentang sistem yang berjalan pada 21 September.
 */
class SimulationRunner
{
    public function __construct(
        private readonly EapEstimator $estimator = new EapEstimator,
        private readonly ItemPool $pool = new ItemPool,
    ) {}

    /**
     * @param  list<float>  $thetaPoints
     * @param  callable(int, int): void|null  $onProgress
     */
    public function run(
        TestConfig $config,
        array $thetaPoints,
        int $replications,
        int $seed,
        ?StoppingRule $rule = null,
        ?callable $onProgress = null,
    ): SimulationRun {
        // Aturan berhenti boleh ditimpa agar skenario se_target x max_items
        // dapat dijajal tanpa menyentuh test_configs yang dipakai sesi nyata.
        $rule ??= new StoppingRule($config->min_items, $config->max_items, $config->se_target);

        $bank = $this->bank($config);

        if ($bank === []) {
            throw new RuntimeException(
                "Bank untuk config '{$config->name}' kosong atau tidak punya parameter aktif. ".
                'Jalankan cat:seed-items lalu cat:seed-provisional-parameters.'
            );
        }

        $run = SimulationRun::query()->create([
            'test_config_id' => $config->id,
            'n_replications' => $replications,
            'theta_distribution' => sprintf(
                'fixed-points:%s;min=%d;max=%d;se=%.2f',
                implode(',', $thetaPoints),
                $rule->minItems,
                $rule->maxItems,
                $rule->seTarget,
            ),
            'seed' => $seed,
            'started_at' => Carbon::now(),
        ]);

        $balancer = ContentBalancer::fromBank($bank);
        $selector = new ItemSelector($balancer, $this->estimator);

        $total = count($thetaPoints) * $replications;
        $done = 0;
        $buffer = [];

        foreach ($thetaPoints as $thetaTrue) {
            for ($replication = 1; $replication <= $replications; $replication++) {
                $buffer[] = $this->replicate($run, $bank, $selector, $rule, $thetaTrue, $replication, $seed);

                if (count($buffer) >= 500) {
                    SimulationResult::query()->insert($buffer);
                    $buffer = [];
                }

                if ($onProgress !== null && ++$done % 100 === 0) {
                    $onProgress($done, $total);
                }
            }
        }

        if ($buffer !== []) {
            SimulationResult::query()->insert($buffer);
        }

        $run->forceFill(['finished_at' => Carbon::now()])->save();

        return $run;
    }

    /**
     * @param  list<Candidate>  $bank
     * @return array<string, mixed>
     */
    private function replicate(
        SimulationRun $run,
        array $bank,
        ItemSelector $selector,
        StoppingRule $rule,
        float $thetaTrue,
        int $replication,
        int $seed,
    ): array {
        // Satu benih per (titik theta, replikasi): laporan dapat diulang persis,
        // dan replikasi tidak saling mewarisi aliran acak.
        $rngSeed = $this->derivedSeed($seed, $thetaTrue, $replication);
        $randomizer = new Randomizer(new Mt19937($rngSeed));

        $remaining = $bank;
        $responses = [];
        $dimensions = [];
        $itemIds = [];
        $estimate = $this->estimator->estimate([]);

        for ($sequence = 1; $sequence <= $rule->maxItems; $sequence++) {
            if ($rule->shouldStop(count($itemIds), $estimate->se, count($remaining))) {
                break;
            }

            try {
                $selection = $selector->select($remaining, $responses, $dimensions, $sequence, $rngSeed);
            } catch (NoCandidateException) {
                break;
            }

            $candidate = $selection->candidate;
            $itemIds[] = $candidate->itemId;
            $dimensions[] = $candidate->dimension;

            $remaining = array_values(array_filter(
                $remaining,
                static fn (Candidate $c): bool => $c->itemId !== $candidate->itemId
            ));

            $probability = (new ResponseModel($candidate->parameter))->probability($thetaTrue);
            $responses[] = new Response($candidate->parameter, $randomizer->nextFloat() < $probability);

            $estimate = $this->estimator->estimate($responses);
        }

        return [
            'simulation_run_id' => $run->id,
            'replication' => $replication,
            'true_theta' => $thetaTrue,
            'est_theta' => round($estimate->theta, 4),
            'se' => round($estimate->se, 4),
            'n_items' => count($itemIds),
            'items_json' => json_encode($itemIds),
        ];
    }

    private function derivedSeed(int $seed, float $thetaTrue, int $replication): int
    {
        return (int) sprintf('%u', crc32("{$seed}:{$thetaTrue}:{$replication}"));
    }

    /**
     * @return list<Candidate>
     */
    private function bank(TestConfig $config): array
    {
        return $this->pool->query($config)
            ->with(['activeParameter', 'dimension:id,code'])
            ->orderBy('id')
            ->get()
            ->map(fn (Item $item): Candidate => new Candidate(
                itemId: $item->id,
                dimension: $item->dimension->code,
                parameter: $item->activeParameter->toCat(),
            ))
            ->all();
    }
}
