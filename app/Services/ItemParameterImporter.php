<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CalibrationRun;
use App\Models\Item;
use App\Models\ItemParameter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menulis versi baru parameter butir.
 *
 * R5: nilai a/b/c yang sudah tersimpan tidak pernah di-UPDATE. Kalibrasi baru
 * menyisipkan baris baru; baris lama hanya diturunkan benderanya menjadi
 * is_active = false supaya sesi lama tetap bisa direkonstruksi.
 */
class ItemParameterImporter
{
    public const HEADER = ['item_code', 'a', 'b', 'c', 'se_a', 'se_b', 'model', 'infit', 'outfit'];

    private const A_MIN = 0.3;

    private const A_MAX = 3.0;

    private const B_MIN = -4.0;

    private const B_MAX = 4.0;

    private const C_MIN = 0.0;

    private const C_MAX = 0.35;

    /**
     * @param  list<array{item_code:string, a:float, b:float, c:float, se_a:?float, se_b:?float, model:string, infit:?float, outfit:?float}>  $rows
     * @return array{runs:int, parameters:int, deactivated:int}
     */
    public function store(array $rows, string $software, string $label, bool $isProvisional, ?int $sampleN = null): array
    {
        $items = $this->resolveItems(array_column($rows, 'item_code'));
        $counts = ['runs' => 0, 'parameters' => 0, 'deactivated' => 0];

        DB::transaction(function () use ($rows, $items, $software, $label, $isProvisional, $sampleN, &$counts): void {
            $now = Carbon::now();
            $runs = [];

            foreach ($rows as $row) {
                $item = $items[$row['item_code']];
                $bankId = $item->item_bank_id;

                if (! isset($runs[$bankId])) {
                    $runs[$bankId] = CalibrationRun::query()->create([
                        'item_bank_id' => $bankId,
                        'software' => $software,
                        'version' => null,
                        'sample_n' => $sampleN,
                        'method' => $row['model'],
                        'is_provisional' => $isProvisional,
                        'notes' => $label,
                        'run_at' => $now,
                    ]);
                    $counts['runs']++;
                }

                $counts['deactivated'] += ItemParameter::query()
                    ->where('item_id', $item->id)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                ItemParameter::query()->create([
                    'item_id' => $item->id,
                    'calibration_run_id' => $runs[$bankId]->id,
                    'model' => $row['model'],
                    'a' => $row['a'],
                    'b' => $row['b'],
                    'c' => $row['c'],
                    'se_a' => $row['se_a'],
                    'se_b' => $row['se_b'],
                    'infit' => $row['infit'],
                    'outfit' => $row['outfit'],
                    'is_active' => true,
                    'calibrated_at' => $now,
                ]);

                $counts['parameters']++;
            }
        });

        return $counts;
    }

    /**
     * Membaca dan memvalidasi seluruh berkas lebih dulu. Satu baris bermasalah
     * membatalkan seluruh impor — parameter setengah terpasang lebih berbahaya
     * daripada impor yang gagal.
     *
     * @return list<array<string, mixed>>
     */
    public function parseCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak bisa dibuka: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('Berkas CSV kosong.');
            }

            $header = array_map(static fn ($c): string => trim((string) $c), $header);

            if ($header !== self::HEADER) {
                throw new RuntimeException(
                    'Header CSV harus persis: '.implode(',', self::HEADER).'. Ditemukan: '.implode(',', $header)
                );
            }

            $rows = [];
            $problems = [];
            $seen = [];
            $line = 1;

            while (($raw = fgetcsv($handle)) !== false) {
                $line++;

                if ($raw === [null] || $raw === []) {
                    continue;
                }

                if (count($raw) !== count(self::HEADER)) {
                    $problems[] = "baris {$line}: butuh ".count(self::HEADER).' kolom, ada '.count($raw);

                    continue;
                }

                $row = array_combine(self::HEADER, array_map(static fn ($c): string => trim((string) $c), $raw));

                if (isset($seen[$row['item_code']])) {
                    $problems[] = "baris {$line}: {$row['item_code']} muncul dua kali (pertama di baris {$seen[$row['item_code']]})";

                    continue;
                }

                $seen[$row['item_code']] = $line;
                $rowProblems = $this->validateRow($row, $line);

                if ($rowProblems !== []) {
                    array_push($problems, ...$rowProblems);

                    continue;
                }

                $rows[] = [
                    'item_code' => $row['item_code'],
                    'a' => (float) $row['a'],
                    'b' => (float) $row['b'],
                    'c' => (float) $row['c'],
                    'se_a' => $row['se_a'] === '' ? null : (float) $row['se_a'],
                    'se_b' => $row['se_b'] === '' ? null : (float) $row['se_b'],
                    'model' => $row['model'],
                    'infit' => $row['infit'] === '' ? null : (float) $row['infit'],
                    'outfit' => $row['outfit'] === '' ? null : (float) $row['outfit'],
                ];
            }
        } finally {
            fclose($handle);
        }

        if ($rows === [] && $problems === []) {
            throw new RuntimeException('Tidak ada baris data di CSV.');
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "Impor dibatalkan, seluruh berkas ditolak:\n  - ".implode("\n  - ", $problems)
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function validateRow(array $row, int $line): array
    {
        $problems = [];

        if ($row['item_code'] === '') {
            $problems[] = "baris {$line}: item_code kosong";
        }

        if ($row['model'] === '') {
            $problems[] = "baris {$line}: model kosong";
        }

        foreach (['a' => [self::A_MIN, self::A_MAX], 'b' => [self::B_MIN, self::B_MAX], 'c' => [self::C_MIN, self::C_MAX]] as $column => [$min, $max]) {
            if (! is_numeric($row[$column])) {
                $problems[] = "baris {$line}: {$column} bukan angka ('{$row[$column]}')";

                continue;
            }

            $value = (float) $row[$column];

            if ($value < $min || $value > $max) {
                $problems[] = sprintf('baris %d: %s = %s di luar rentang [%s, %s]', $line, $column, $row[$column], $min, $max);
            }
        }

        foreach (['se_a', 'se_b', 'infit', 'outfit'] as $column) {
            if ($row[$column] !== '' && ! is_numeric($row[$column])) {
                $problems[] = "baris {$line}: {$column} bukan angka ('{$row[$column]}')";
            }
        }

        return $problems;
    }

    /**
     * @param  list<string>  $codes
     * @return array<string, Item>
     */
    private function resolveItems(array $codes): array
    {
        $items = Item::query()->whereIn('code', $codes)->get()->keyBy('code');
        $missing = array_values(array_diff($codes, $items->keys()->all()));

        if ($missing !== []) {
            throw new RuntimeException(
                'Impor dibatalkan, butir tidak ada di bank: '.implode(', ', $missing)
            );
        }

        return $items->all();
    }
}
