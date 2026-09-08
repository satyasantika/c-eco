<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SimulationResult;
use App\Models\SimulationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SimulateReportCommand extends Command
{
    protected $signature = 'cat:simulate-report
        {run : Id simulation_run}
        {--csv : Tulis juga CSV per replikasi untuk dianalisis di R}';

    protected $description = 'Meringkas satu simulasi: bias, RMSE, panjang tes, dan laju eksposur';

    public function handle(): int
    {
        $run = SimulationRun::query()->find((int) $this->argument('run'));

        if ($run === null) {
            $this->error("simulation_run #{$this->argument('run')} tidak ada.");

            return self::FAILURE;
        }

        $results = SimulationResult::query()->where('simulation_run_id', $run->id)->get();

        if ($results->isEmpty()) {
            $this->error("simulation_run #{$run->id} tidak punya hasil.");

            return self::FAILURE;
        }

        $this->info("simulation_run #{$run->id} — {$run->theta_distribution}, {$run->n_replications} replikasi, seed {$run->seed}");
        $this->newLine();

        $this->table(
            ['theta', 'bias', 'RMSE', 'rata-rata SE', 'panjang tes', 'n'],
            $this->accuracyRows($results),
        );

        $this->newLine();
        $this->line(sprintf('Korelasi theta_hat ~ theta : %.4f', $this->correlation($results)));
        $this->newLine();

        $this->exposureSummary($results);

        if ($this->option('csv')) {
            $this->writeCsv($run, $results);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<list<string>>
     */
    private function accuracyRows(mixed $results): array
    {
        // Dikelompokkan lewat kunci string: PHP memotong kunci array pecahan
        // menjadi integer, sehingga -1,5 dan -1,0 akan menyatu diam-diam.
        return $results->groupBy(static fn (SimulationResult $r): string => sprintf('%+.4f', $r->true_theta))
            ->sortKeys()
            ->map(function ($group, $theta): array {
                $errors = $group->map(static fn (SimulationResult $r): float => $r->est_theta - $r->true_theta);

                return [
                    sprintf('%+.2f', (float) $theta),
                    sprintf('%+.4f', $errors->avg()),
                    sprintf('%.4f', sqrt($errors->map(static fn (float $e): float => $e ** 2)->avg())),
                    sprintf('%.4f', $group->avg('se')),
                    sprintf('%.1f', $group->avg('n_items')),
                    (string) $group->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function correlation(mixed $results): float
    {
        $x = $results->pluck('true_theta')->all();
        $y = $results->pluck('est_theta')->all();

        $n = count($x);
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;

        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx * $dx;
            $varianceY += $dy * $dy;
        }

        $denominator = sqrt($varianceX * $varianceY);

        return $denominator === 0.0 ? 0.0 : $covariance / $denominator;
    }

    private function exposureSummary(mixed $results): void
    {
        $sessions = $results->count();
        $counts = [];

        foreach ($results as $result) {
            foreach ($result->items_json as $itemId) {
                $counts[$itemId] = ($counts[$itemId] ?? 0) + 1;
            }
        }

        $bankSize = \App\Models\Item::query()
            ->where('item_bank_id', $results->first()->simulationRun->testConfig->item_bank_id)
            ->active()
            ->count();

        $rates = array_map(static fn (int $n): float => $n / $sessions, $counts);
        $overExposed = count(array_filter($rates, static fn (float $r): bool => $r > 0.40));
        $unused = $bankSize - count($counts);

        $this->line('Eksposur');
        $this->line(sprintf('  laju tertinggi          : %.3f', $rates === [] ? 0.0 : max($rates)));
        $this->line(sprintf('  butir dengan laju > 0,40: %d', $overExposed));
        $this->line(sprintf('  butir tak pernah dipakai: %d dari %d (%.1f%%)', $unused, $bankSize, $bankSize === 0 ? 0 : 100 * $unused / $bankSize));

        if ($overExposed > 0) {
            $this->warn('  SPEC §10 uji #5 menuntut nol butir dengan laju > 0,40.');
        }
    }

    private function writeCsv(SimulationRun $run, mixed $results): void
    {
        $path = "exports/simulation-{$run->id}.csv";
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, ['simulation_run_id', 'replication', 'true_theta', 'est_theta', 'se', 'n_items', 'items']);

        foreach ($results as $result) {
            fputcsv($handle, [
                $result->simulation_run_id,
                $result->replication,
                $result->true_theta,
                $result->est_theta,
                $result->se,
                $result->n_items,
                implode(' ', $result->items_json),
            ]);
        }

        rewind($handle);
        Storage::disk('local')->put($path, stream_get_contents($handle));
        fclose($handle);

        $this->newLine();
        $this->info('CSV: '.Storage::disk('local')->path($path));
    }
}
