<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ItemParameterImporter;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

class SeedProvisionalParametersCommand extends Command
{
    /** Bersemai tetap supaya bank yang sama selalu menghasilkan parameter yang sama. */
    public const SEED = 20260921;

    private const C_FIXED = 0.20;

    protected $signature = 'cat:seed-provisional-parameters
        {--grade= : Batasi ke satu jenjang (X, XI, atau XII)}';

    protected $description = 'Membuat parameter butir SEMENTARA untuk uji fisibilitas 21 September';

    public function handle(ItemParameterImporter $importer): int
    {
        $grade = $this->option('grade') === null ? null : (string) $this->option('grade');

        $items = Item::query()
            ->when($grade !== null, fn ($q) => $q->whereRelation('itemBank', 'grade', $grade))
            ->orderBy('code')
            ->get();

        if ($items->isEmpty()) {
            $this->error('Tidak ada butir'.($grade !== null ? " untuk jenjang {$grade}" : '').'. Jalankan cat:seed-items lebih dulu.');

            return self::FAILURE;
        }

        $randomizer = new Randomizer(new Mt19937(self::SEED));

        $rows = $items->map(fn (Item $item): array => [
            'item_code' => $item->code,
            'a' => round($randomizer->getFloat(0.8, 1.6), 4),
            'b' => round($this->truncatedNormal($randomizer, -2.5, 2.5), 4),
            'c' => self::C_FIXED,
            'se_a' => null,
            'se_b' => null,
            'model' => '3PL-c-fixed',
            'infit' => null,
            'outfit' => null,
        ])->all();

        try {
            $counts = $importer->store(
                rows: $rows,
                software: 'C-ECO cat:seed-provisional-parameters',
                label: 'Parameter sementara, seed '.self::SEED,
                isProvisional: true,
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printWarning($counts['parameters']);

        return self::SUCCESS;
    }

    /** N(0,1) dipotong ke [min, max] lewat penolakan, bukan pemangkasan — pemangkasan menumpuk massa di tepi. */
    private function truncatedNormal(Randomizer $randomizer, float $min, float $max): float
    {
        do {
            $u1 = max($randomizer->nextFloat(), 1e-12);
            $u2 = $randomizer->nextFloat();
            $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
        } while ($z < $min || $z > $max);

        return $z;
    }

    private function printWarning(int $count): void
    {
        $this->newLine();
        $this->error('  ============================================================  ');
        $this->error('   PARAMETER INI SEMENTARA DAN DIBANGKITKAN SECARA ACAK.        ');
        $this->error('   Tidak sah untuk klaim psikometrik apa pun: bukan hasil       ');
        $this->error('   kalibrasi, tidak mencerminkan sifat butir yang sebenarnya.   ');
        $this->error('   Hanya untuk menjalankan uji fisibilitas 21 September.        ');
        $this->error('   Ganti dengan cat:import-parameters setelah kalibrasi nyata.  ');
        $this->error('  ============================================================  ');
        $this->newLine();
        $this->info("{$count} butir menerima parameter sementara (seed ".self::SEED.').');
    }
}
