<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\CAT\StoppingRule;
use App\Models\TestConfig;
use App\Services\SimulationRunner;
use Illuminate\Console\Command;
use RuntimeException;

class SimulateCommand extends Command
{
    protected $signature = 'cat:simulate
        {--grade= : Jenjang X, XI, atau XII}
        {--config= : Id test_config; menimpa --grade}
        {--reps=1000 : Replikasi per titik theta}
        {--theta-points=-2,-1.5,-1,-0.5,0,0.5,1,1.5,2 : Titik theta sebenarnya}
        {--seed=20260921 : Benih acak}
        {--min-items= : Menimpa min_items untuk skenario ini saja}
        {--max-items= : Menimpa max_items untuk skenario ini saja}
        {--se-target= : Menimpa se_target untuk skenario ini saja}';

    protected $description = 'Menjalankan simulasi Monte Carlo memakai mesin CAT yang sama dengan produksi';

    public function handle(SimulationRunner $runner): int
    {
        try {
            $config = $this->config();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $thetaPoints = array_map(
            static fn (string $value): float => (float) trim($value),
            explode(',', (string) $this->option('theta-points'))
        );

        $replications = (int) $this->option('reps');
        $total = count($thetaPoints) * $replications;

        $rule = new StoppingRule(
            minItems: (int) ($this->option('min-items') ?? $config->min_items),
            maxItems: (int) ($this->option('max-items') ?? $config->max_items),
            seTarget: (float) ($this->option('se-target') ?? $config->se_target),
        );

        $this->info("Konfigurasi: {$config->name} (min {$rule->minItems}, maks {$rule->maxItems}, SE ≤ {$rule->seTarget})");
        $this->info("{$total} sesi disimulasikan.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        try {
            $run = $runner->run(
                config: $config,
                thetaPoints: $thetaPoints,
                replications: $replications,
                seed: (int) $this->option('seed'),
                rule: $rule,
                onProgress: static fn (int $done) => $bar->setProgress($done),
            );
        } catch (RuntimeException $e) {
            $bar->clear();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("simulation_run #{$run->id} selesai. Laporan: php artisan cat:simulate-report {$run->id}");

        return self::SUCCESS;
    }

    private function config(): TestConfig
    {
        if ($this->option('config') !== null) {
            return TestConfig::query()->findOrFail((int) $this->option('config'));
        }

        $grade = $this->option('grade');

        if ($grade === null) {
            throw new RuntimeException('Sebutkan --grade atau --config.');
        }

        $config = TestConfig::query()
            ->whereRelation('itemBank', 'grade', (string) $grade)
            ->where('is_active', true)
            ->first();

        if ($config === null) {
            throw new RuntimeException("Tidak ada test_config aktif untuk jenjang {$grade}.");
        }

        return $config;
    }
}
