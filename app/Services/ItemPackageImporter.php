<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Dimension;
use App\Models\Item;
use App\Models\ItemBank;
use App\Models\ItemOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Memuat paket soal dari JSON (bentuk yang sama dengan data/items-all.json).
 *
 * Berbeda dari ItemBankImporter (seeder): impor paket menolak seluruh berkas
 * bila satu kode sudah ada, dan tidak pernah menimpa butir. Kode adalah
 * identitas lintas berkas (R7).
 */
class ItemPackageImporter
{
    public const GRADES = ['X', 'XI', 'XII'];

    public const OPTION_LABELS = ['A', 'B', 'C', 'D', 'E'];

    public const CODE_PATTERN = '/^(X|XI|XII)-\d{2,}$/';

    public const DIMENSIONS = [
        'fluency', 'flexibility', 'originality', 'elaboration',
        'solutif', 'adaptif', 'prediktif',
    ];

    public const C_FIXED = 0.20;

    public function __construct(
        private readonly ItemParameterImporter $parameters,
        private readonly TestConfigProvisioner $configs,
    ) {}

    /**
     * @return array{bank:ItemBank, created:bool, items:int, options:int, parameters:int, configs:int}
     */
    public function importFromFile(string $path, string $grade, string $version, bool $provisional = true): array
    {
        return $this->import($this->parseFile($path), $grade, $version, $provisional, basename($path));
    }

    /**
     * @param  array{dimensions?: list<array<string, mixed>>, items: list<array<string, mixed>>}  $payload
     * @return array{bank:ItemBank, created:bool, items:int, options:int, parameters:int, configs:int}
     */
    public function import(array $payload, string $grade, string $version, bool $provisional = true, string $source = 'impor'): array
    {
        $grade = strtoupper($grade);
        $version = trim($version);

        $this->guardGradeAndVersion($grade, $version);
        $items = $this->validatedItems($payload['items'] ?? [], $grade);

        $result = [
            'bank' => null,
            'created' => false,
            'items' => 0,
            'options' => 0,
            'parameters' => 0,
            'configs' => 0,
        ];

        DB::transaction(function () use ($payload, $items, $grade, $version, $provisional, $source, &$result): void {
            $this->syncDimensions($payload['dimensions'] ?? []);
            $this->ensureRequiredDimensions();
            $dimensions = Dimension::query()->get()->keyBy('code');

            $bank = ItemBank::query()->firstOrNew(['grade' => $grade, 'version' => $version]);
            $created = ! $bank->exists;

            if ($created) {
                $bank->fill([
                    'irt_model' => ItemBankImporter::IRT_MODEL,
                    'scale_note' => 'Skala θ ~ N(0,1) per jenjang; belum ditautkan antarjenjang.',
                    'calibration_source' => $source,
                    'is_active' => true,
                ])->save();
            }

            $createdItems = new Collection;

            foreach ($items as $raw) {
                $item = $this->insertItem($raw, $bank, $dimensions[$raw['dimension']]);
                $createdItems->push($item);
                $result['items']++;
                $result['options'] += count($raw['options']);
            }

            $config = $this->configs->ensureAdaptive($bank);
            $result['configs'] = $config->wasRecentlyCreated ? 1 : 0;
            $result['bank'] = $bank;
            $result['created'] = $created;

            if ($provisional) {
                $result['parameters'] = $this->seedProvisional($createdItems, $bank);
            }
        });

        return $result;
    }

    /**
     * @return array{dimensions: list<array<string, mixed>>, items: list<array<string, mixed>>}
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Berkas bukan JSON yang sah: '.$e->getMessage());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Berkas bukan JSON yang sah.');
        }

        if (array_is_list($decoded)) {
            return ['dimensions' => [], 'items' => $decoded];
        }

        if (! isset($decoded['items']) || ! is_array($decoded['items'])) {
            throw new RuntimeException("Struktur JSON tidak dikenali: butuh kunci 'items', atau array butir di akar berkas.");
        }

        return [
            'dimensions' => is_array($decoded['dimensions'] ?? null) ? $decoded['dimensions'] : [],
            'items' => $decoded['items'],
        ];
    }

    public static function suggestVersion(string $grade): string
    {
        $existing = ItemBank::query()->where('grade', strtoupper($grade))->pluck('version');

        if ($existing->isEmpty()) {
            return ItemBankImporter::BANK_VERSION;
        }

        $candidate = now()->format('Y.m.d');

        return $existing->contains($candidate) ? $candidate.'.'.now()->format('His') : $candidate;
    }

    private function guardGradeAndVersion(string $grade, string $version): void
    {
        if (! in_array($grade, self::GRADES, true)) {
            throw new RuntimeException('Jenjang harus X, XI, atau XII.');
        }

        if ($version === '') {
            throw new RuntimeException('Versi paket tidak boleh kosong.');
        }

        if (strlen($version) > 32) {
            throw new RuntimeException('Versi paket terlalu panjang (maksimum 32 karakter).');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rawItems
     * @return list<array<string, mixed>>
     */
    private function validatedItems(array $rawItems, string $grade): array
    {
        if ($rawItems === []) {
            throw new RuntimeException('Berkas tidak berisi butir.');
        }

        $knownDimensions = self::DIMENSIONS;

        $problems = [];
        $seen = [];
        $normalized = [];

        foreach ($rawItems as $index => $raw) {
            $line = $index + 1;

            if (! is_array($raw)) {
                $problems[] = "butir {$line}: bukan objek JSON";

                continue;
            }

            $code = strtoupper(trim((string) ($raw['code'] ?? '')));
            $itemGrade = strtoupper(trim((string) ($raw['grade'] ?? $grade)));
            $dimension = strtolower(trim((string) ($raw['dimension'] ?? '')));
            $stem = trim((string) ($raw['stem_html'] ?? ''));
            $options = $raw['options'] ?? null;

            if ($code === '') {
                $problems[] = "butir {$line}: code kosong";
            } elseif (! preg_match(self::CODE_PATTERN, $code)) {
                $problems[] = "{$code}: kode harus berbentuk {$grade}-01 (R7)";
            } elseif (! str_starts_with($code, $grade.'-')) {
                $problems[] = "{$code}: awalan kode tidak cocok dengan jenjang {$grade}";
            }

            if ($itemGrade !== $grade) {
                $problems[] = ($code !== '' ? $code : "butir {$line}").": jenjang {$itemGrade} tidak cocok dengan paket {$grade}";
            }

            if ($dimension === '') {
                $problems[] = ($code !== '' ? $code : "butir {$line}").': dimensi kosong';
            } elseif (! in_array($dimension, $knownDimensions, true)) {
                $problems[] = "{$code}: dimensi '{$dimension}' tidak dikenali";
            }

            if ($stem === '') {
                $problems[] = ($code !== '' ? $code : "butir {$line}").': stem_html kosong';
            }

            $optionProblems = $this->validateOptions(is_array($options) ? $options : [], $code !== '' ? $code : "butir {$line}");
            array_push($problems, ...$optionProblems);

            if ($code !== '') {
                if (isset($seen[$code])) {
                    $problems[] = "{$code}: muncul dua kali dalam berkas (pertama di butir {$seen[$code]})";
                } else {
                    $seen[$code] = $line;
                }
            }

            $normalized[] = [
                'code' => $code,
                'grade' => $itemGrade,
                'dimension' => $dimension,
                'learning_objective' => $raw['learning_objective'] ?? null,
                'topic' => $raw['topic'] ?? null,
                'semester' => $raw['semester'] ?? null,
                'indicator' => $raw['indicator'] ?? null,
                'bloom_level' => $raw['bloom_level'] ?? $raw['bloom'] ?? null,
                'stem_html' => $stem,
                'media_path' => $raw['media_path'] ?? null,
                'source' => $raw['source'] ?? $raw['source_note'] ?? $code,
                'warnings' => $raw['warnings'] ?? [],
                'options' => is_array($options) ? $options : [],
            ];
        }

        $existing = Item::query()->whereIn('code', array_keys($seen))->pluck('code')->all();
        foreach ($existing as $code) {
            $problems[] = "{$code}: sudah ada di bank — kode adalah identitas butir dan tidak boleh dipakai ulang (R7)";
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Impor dibatalkan, seluruh berkas ditolak:\n  - ".implode("\n  - ", $problems)
            );
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<string>
     */
    private function validateOptions(array $options, string $label): array
    {
        if (count($options) !== 5) {
            return ["{$label}: harus punya tepat 5 opsi A–E, ada ".count($options)];
        }

        $problems = [];
        $labels = [];
        $keys = 0;

        foreach ($options as $option) {
            if (! is_array($option)) {
                $problems[] = "{$label}: opsi bukan objek JSON";

                continue;
            }

            $optLabel = strtoupper(trim((string) ($option['label'] ?? '')));
            $body = trim((string) ($option['body_html'] ?? ''));

            if (! in_array($optLabel, self::OPTION_LABELS, true)) {
                $problems[] = "{$label}: label opsi '{$optLabel}' tidak sah (harus A–E)";
            } elseif (isset($labels[$optLabel])) {
                $problems[] = "{$label}: label {$optLabel} muncul dua kali";
            } else {
                $labels[$optLabel] = true;
            }

            if ($body === '') {
                $problems[] = "{$label} opsi {$optLabel}: body_html kosong";
            }

            if (! empty($option['is_key'])) {
                $keys++;
            }
        }

        $missing = array_diff(self::OPTION_LABELS, array_keys($labels));
        if ($missing !== []) {
            $problems[] = "{$label}: opsi ".implode(', ', $missing).' hilang';
        }

        if ($keys !== 1) {
            $problems[] = "{$label}: {$keys} opsi bertanda kunci (harus tepat 1)";
        }

        return $problems;
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     */
    private function syncDimensions(array $raw): void
    {
        foreach ($raw as $order => $row) {
            if (! isset($row['code'], $row['label']) && ! isset($row['code'], $row['name'])) {
                continue;
            }

            if (! in_array($row['code'], self::DIMENSIONS, true)) {
                continue;
            }

            Dimension::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['label'] ?? $row['name'],
                    'display_order' => $order + 1,
                ],
            );
        }
    }

    private function ensureRequiredDimensions(): void
    {
        $names = [
            'fluency' => 'Fluency (Kelancaran)',
            'flexibility' => 'Flexibility (Keluwesan)',
            'originality' => 'Originality (Orisinalitas)',
            'elaboration' => 'Elaboration (Elaborasi)',
            'solutif' => 'Solutif',
            'adaptif' => 'Adaptif',
            'prediktif' => 'Prediktif',
        ];

        foreach (self::DIMENSIONS as $order => $code) {
            Dimension::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $names[$code],
                    'display_order' => $order + 1,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function insertItem(array $raw, ItemBank $bank, Dimension $dimension): Item
    {
        $item = Item::query()->create([
            'item_bank_id' => $bank->id,
            'code' => $raw['code'],
            'dimension_id' => $dimension->id,
            'learning_objective' => $raw['learning_objective'],
            'topic' => $raw['topic'],
            'semester' => $raw['semester'],
            'indicator' => $raw['indicator'],
            'bloom_level' => $raw['bloom_level'],
            'stem_html' => $raw['stem_html'],
            'media_path' => $raw['media_path'],
            'status' => 'active',
            'source_note' => $this->sourceNote($raw),
        ]);

        foreach ($raw['options'] as $order => $option) {
            ItemOption::query()->create([
                'item_id' => $item->id,
                'label' => strtoupper((string) $option['label']),
                'body_html' => $option['body_html'],
                'is_key' => (bool) ($option['is_key'] ?? false),
                'display_order' => $order + 1,
            ]);
        }

        return $item;
    }

    /**
     * @param  Collection<int, Item>  $items
     */
    private function seedProvisional(Collection $items, ItemBank $bank): int
    {
        if ($items->isEmpty()) {
            return 0;
        }

        $rows = $items->map(function (Item $item): array {
            $rng = new Randomizer(new Mt19937($this->seedFor($item->code)));

            return [
                'item_code' => $item->code,
                'a' => round($rng->getFloat(0.8, 1.6), 4),
                'b' => round($this->truncatedNormal($rng), 4),
                'c' => self::C_FIXED,
                'se_a' => null,
                'se_b' => null,
                'model' => ItemBankImporter::IRT_MODEL,
                'infit' => null,
                'outfit' => null,
            ];
        })->values()->all();

        $counts = $this->parameters->store(
            rows: $rows,
            software: 'C-ECO impor paket',
            label: "Parameter sementara paket {$bank->grade} {$bank->version}",
            isProvisional: true,
        );

        return $counts['parameters'];
    }

    private function seedFor(string $code): int
    {
        return (crc32($code) ^ 20260921) & 0x7FFFFFFF;
    }

    private function truncatedNormal(Randomizer $randomizer): float
    {
        do {
            $u1 = max($randomizer->nextFloat(), 1e-12);
            $u2 = $randomizer->nextFloat();
            $z = sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
        } while ($z < -2.5 || $z > 2.5);

        return $z;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function sourceNote(array $raw): string
    {
        $note = (string) $raw['source'];
        $warnings = $raw['warnings'];

        if (is_array($warnings) && $warnings !== []) {
            $note .= ' | PERINGATAN: '.implode(' ; ', $warnings);
        }

        return $note;
    }
}
