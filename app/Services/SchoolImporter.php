<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\School;
use App\Support\DelimitedTable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchoolImporter
{
    /** @var list<string> */
    public const REQUIRED = ['name'];

    /** @var list<string> */
    public const OPTIONAL = ['city'];

    /** @var array<string, list<string>> */
    public const ALIASES = [
        'name' => ['nama', 'sekolah', 'nama_sekolah'],
        'city' => ['kota'],
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function import(array $rows): array
    {
        $problems = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $row['_line'] ?? (string) ($index + 2);
            $name = trim($row['name'] ?? '');
            $key = mb_strtolower($name);

            if ($name === '') {
                $problems[] = "baris {$line}: nama sekolah kosong";
                continue;
            }

            if (isset($seen[$key])) {
                $problems[] = "baris {$line}: nama sekolah berulang dalam berkas ini ({$name})";
                continue;
            }

            $seen[$key] = true;
            $rows[$index]['name'] = $name;
            $rows[$index]['city'] = trim($row['city'] ?? '') ?: null;
        }

        if ($problems !== []) {
            throw new RuntimeException("Impor dibatalkan:\n  - ".implode("\n  - ", $problems));
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$created, &$skipped): void {
            foreach ($rows as $row) {
                $school = School::query()->firstOrNew([
                    'name' => $row['name'],
                    'exam_simulation_id' => null,
                ]);

                if ($school->exists) {
                    $skipped++;
                    continue;
                }

                $school->city = $row['city'];
                $school->save();
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array{created: int, skipped: int} */
    public function importText(string $text): array
    {
        return $this->import(DelimitedTable::parse($text, self::REQUIRED, self::ALIASES, self::OPTIONAL));
    }

    /** @return array{created: int, skipped: int} */
    public function importFile(string $path): array
    {
        return $this->import(DelimitedTable::fromFile($path, self::REQUIRED, self::ALIASES, self::OPTIONAL));
    }
}
