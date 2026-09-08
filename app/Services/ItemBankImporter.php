<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dimension;
use App\Models\Item;
use App\Models\ItemBank;
use App\Models\ItemOption;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Memuat bank soal dari data/items-all.json.
 *
 * Dipakai oleh ItemBankSeeder dan command cat:seed-items — satu logika,
 * dua pintu masuk.
 */
class ItemBankImporter
{
    public const BANK_VERSION = '2026.1';

    public const IRT_MODEL = '3PL-c-fixed';

    public function __construct(
        private readonly string $jsonPath,
        private readonly string $reportPath,
    ) {}

    public static function fromDataDirectory(?string $directory = null): self
    {
        $directory ??= base_path('data');

        return new self(
            $directory.'/items-all.json',
            $directory.'/EXTRACTION-REPORT.md',
        );
    }

    /**
     * @return array{dimensions:int, banks:int, items:int, options:int, skipped:int}
     */
    public function import(bool $force = false): array
    {
        $payload = $this->readJson();
        $this->guardAgainstMissingKeys($payload['items']);

        $counts = ['dimensions' => 0, 'banks' => 0, 'items' => 0, 'options' => 0, 'skipped' => 0];

        DB::transaction(function () use ($payload, $force, &$counts): void {
            $dimensions = $this->syncDimensions($payload['dimensions'], $force, $counts);
            $banks = $this->syncBanks($payload['items'], $force, $counts);

            foreach ($payload['items'] as $raw) {
                $this->syncItem($raw, $dimensions, $banks, $force, $counts);
            }
        });

        return $counts;
    }

    /**
     * @return array{dimensions: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    private function readJson(): array
    {
        if (! is_file($this->jsonPath)) {
            throw new RuntimeException(
                "Bank soal tidak ditemukan di {$this->jsonPath}. ".
                'Berkas data/ tidak ikut di Git — salin dari mesin pengembang lebih dulu.'
            );
        }

        $decoded = json_decode((string) file_get_contents($this->jsonPath), true, 512, JSON_THROW_ON_ERROR);

        if (! isset($decoded['dimensions'], $decoded['items'])) {
            throw new RuntimeException("Struktur {$this->jsonPath} tidak dikenali: butuh kunci 'dimensions' dan 'items'.");
        }

        return $decoded;
    }

    /**
     * Butir tanpa kunci harus menghentikan seeder, bukan masuk diam-diam:
     * sesi yang memuat butir seperti itu tidak bisa dinilai.
     *
     * @param  list<array<string, mixed>>  $items
     */
    private function guardAgainstMissingKeys(array $items): void
    {
        $problems = [];

        foreach ($items as $raw) {
            $keys = array_filter($raw['options'] ?? [], static fn (array $o): bool => (bool) ($o['is_key'] ?? false));

            if (count($keys) !== 1) {
                $problems[] = sprintf('%s: %d opsi bertanda kunci (harus tepat 1)', $raw['code'], count($keys));
            }
        }

        foreach ($this->reportedMissingKeys() as $line) {
            $problems[] = 'EXTRACTION-REPORT.md: '.$line;
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Bank soal ditolak — butir tanpa kunci tunggal:\n  - ".implode("\n  - ", $problems)
            );
        }
    }

    /**
     * @return list<string>
     */
    private function reportedMissingKeys(): array
    {
        if (! is_file($this->reportPath)) {
            return [];
        }

        $lines = preg_split('/\R/', (string) file_get_contents($this->reportPath)) ?: [];

        return array_values(array_filter(
            array_map('trim', $lines),
            static fn (string $line): bool => (bool) preg_match(
                '/(tanpa kunci|kunci tidak ditemukan|kunci hilang|tidak berkunci)/i',
                $line
            )
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return array<string, Dimension>
     */
    private function syncDimensions(array $raw, bool $force, array &$counts): array
    {
        $dimensions = [];

        foreach ($raw as $order => $row) {
            $attributes = [
                'name' => $row['label'],
                'display_order' => $order + 1,
            ];

            $dimension = Dimension::query()->firstOrNew(['code' => $row['code']]);

            if (! $dimension->exists || $force) {
                $dimension->fill($attributes)->save();
                $counts['dimensions']++;
            }

            $dimensions[$row['code']] = $dimension;
        }

        return $dimensions;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, ItemBank>
     */
    private function syncBanks(array $items, bool $force, array &$counts): array
    {
        $banks = [];

        foreach (array_unique(array_column($items, 'grade')) as $grade) {
            $bank = ItemBank::query()->firstOrNew([
                'grade' => $grade,
                'version' => self::BANK_VERSION,
            ]);

            if (! $bank->exists || $force) {
                $bank->fill([
                    'irt_model' => self::IRT_MODEL,
                    'scale_note' => 'Skala θ ~ N(0,1) per jenjang; belum ditautkan antarjenjang.',
                    'calibration_source' => 'data/items-all.json',
                    'is_active' => true,
                ])->save();
                $counts['banks']++;
            }

            $banks[$grade] = $bank;
        }

        return $banks;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, Dimension>  $dimensions
     * @param  array<string, ItemBank>  $banks
     */
    private function syncItem(array $raw, array $dimensions, array $banks, bool $force, array &$counts): void
    {
        if (! isset($dimensions[$raw['dimension']])) {
            throw new RuntimeException("{$raw['code']}: dimensi '{$raw['dimension']}' tidak ada di blok dimensions.");
        }

        // R7: identitas butir adalah code, bukan urutan atau id.
        $item = Item::query()->firstOrNew(['code' => $raw['code']]);

        // Butir yang sudah ada TIDAK ditimpa: perbaikan manual lewat ItemResource
        // di Filament harus bertahan setiap kali seeder dijalankan ulang (SPEC §9).
        if ($item->exists && ! $force) {
            $counts['skipped']++;

            return;
        }

        $item->fill([
            'item_bank_id' => $banks[$raw['grade']]->id,
            'dimension_id' => $dimensions[$raw['dimension']]->id,
            'learning_objective' => $raw['learning_objective'],
            'topic' => $raw['topic'],
            'semester' => $raw['semester'],
            'indicator' => $raw['indicator'],
            'bloom_level' => $raw['bloom'],
            'stem_html' => $raw['stem_html'],
            'status' => 'active',
            'source_note' => $this->sourceNote($raw),
        ])->save();

        $counts['items']++;

        foreach ($raw['options'] as $order => $option) {
            $row = ItemOption::query()->firstOrNew([
                'item_id' => $item->id,
                'label' => $option['label'],
            ]);

            if ($row->exists && ! $force) {
                continue;
            }

            $row->fill([
                'body_html' => $option['body_html'],
                'is_key' => (bool) $option['is_key'],
                'display_order' => $order + 1,
            ])->save();

            $counts['options']++;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function sourceNote(array $raw): string
    {
        $note = (string) $raw['source'];

        if (! empty($raw['warnings'])) {
            $note .= ' | PERINGATAN: '.implode(' ; ', $raw['warnings']);
        }

        return $note;
    }
}
