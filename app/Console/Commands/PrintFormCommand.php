<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TestConfig;
use App\Services\LinearFormBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menyiapkan bentuk kertas untuk lapis mundur L3 (docs/RUNBOOK.md).
 *
 * Dijalankan H-1, bukan pada hari-H: kalau listrik atau jaringan padam,
 * perintah ini tidak akan bisa dijalankan lagi.
 */
class PrintFormCommand extends Command
{
    protected $signature = 'cat:print-form
        {--config= : Id test_config; bila kosong, semua konfigurasi aktif}
        {--dir=fallback : Folder tujuan di disk local}';

    protected $description = 'Menulis lembar soal dan lembar jawaban bentuk linear ke HTML siap cetak';

    public function handle(LinearFormBuilder $builder): int
    {
        $configs = TestConfig::query()
            ->when($this->option('config'), fn ($q) => $q->whereKey((int) $this->option('config')))
            ->when(! $this->option('config'), fn ($q) => $q->where('is_active', true))
            ->get();

        if ($configs->isEmpty()) {
            $this->error('Tidak ada test_config yang cocok.');

            return self::FAILURE;
        }

        $directory = trim((string) $this->option('dir'), '/');

        foreach ($configs as $config) {
            $items = $builder->build($config);

            if ($items->count() < $config->max_items) {
                $this->warn(sprintf(
                    '%s: hanya %d dari %d butir tersusun — periksa parameter aktif dan cakupan dimensi.',
                    $config->name, $items->count(), $config->max_items,
                ));
            }

            $path = sprintf('%s/bentuk-cetak-%d.html', $directory, $config->id);

            Storage::disk('local')->put($path, view('admin.print-form', [
                'config' => $config,
                'items' => $items,
            ])->render());

            $this->info(sprintf('%s (%d butir): %s', $config->name, $items->count(), Storage::disk('local')->path($path)));
        }

        $this->line('Buka berkasnya di peramban lalu cetak. Bawa dalam map, jangan hanya di layar.');

        return self::SUCCESS;
    }
}
