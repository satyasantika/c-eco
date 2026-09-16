<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Participant;
use App\Models\School;
use App\Support\DelimitedTable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ParticipantImporter
{
    /** @var list<string> */
    public const REQUIRED = ['school', 'class_name', 'student_code', 'display_name'];

    /** @var list<string> */
    public const OPTIONAL = ['city'];

    /** @var array<string, list<string>> */
    public const ALIASES = [
        'school' => ['sekolah', 'nama_sekolah'],
        'class_name' => ['kelas', 'class'],
        'student_code' => ['nis', 'nisn', 'kode', 'kode_siswa'],
        'display_name' => ['nama', 'name', 'nama_lengkap'],
        'city' => ['kota'],
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function import(array $rows, ?string $defaultCity = null): array
    {
        $defaultCity = ($defaultCity !== null && trim($defaultCity) !== '') ? trim($defaultCity) : null;
        $problems = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $row['_line'] ?? (string) ($index + 2);
            $school = trim($row['school'] ?? '');
            $code = trim($row['student_code'] ?? '');
            $key = mb_strtolower($school)."\0".$code;

            foreach (['school' => $school, 'class_name' => trim($row['class_name'] ?? ''), 'student_code' => $code, 'display_name' => trim($row['display_name'] ?? '')] as $column => $value) {
                if ($value === '') {
                    $problems[] = "baris {$line}: {$column} kosong";
                }
            }

            if ($school !== '' && $code !== '' && isset($seen[$key])) {
                $problems[] = "baris {$line}: kode siswa {$code} berulang di {$school}";
            }

            $seen[$key] = true;
            $rows[$index]['school'] = $school;
            $rows[$index]['class_name'] = trim($row['class_name'] ?? '');
            $rows[$index]['student_code'] = $code;
            $rows[$index]['display_name'] = trim($row['display_name'] ?? '');
            $city = trim($row['city'] ?? '');
            $rows[$index]['city'] = $city !== '' ? $city : $defaultCity;
        }

        if ($problems !== []) {
            throw new RuntimeException("Impor dibatalkan:\n  - ".implode("\n  - ", $problems));
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, &$created, &$skipped): void {
            foreach ($rows as $row) {
                $school = School::query()->firstOrCreate(
                    [
                        'name' => $row['school'],
                        'exam_simulation_id' => null,
                    ],
                    ['city' => $row['city']],
                );

                $participant = Participant::query()->firstOrNew([
                    'school_id' => $school->id,
                    'student_code' => $row['student_code'],
                ]);

                if ($participant->exists) {
                    $skipped++;
                    continue;
                }

                $participant->fill([
                    'class_name' => $row['class_name'],
                    'display_name' => $row['display_name'],
                ])->save();

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** @return array{created: int, skipped: int} */
    public function importText(string $text, ?string $defaultCity = null): array
    {
        return $this->import(
            DelimitedTable::parse($text, self::REQUIRED, self::ALIASES, self::OPTIONAL),
            $defaultCity,
        );
    }

    /** @return array{created: int, skipped: int} */
    public function importFile(string $path, ?string $defaultCity = null): array
    {
        return $this->import(
            DelimitedTable::fromFile($path, self::REQUIRED, self::ALIASES, self::OPTIONAL),
            $defaultCity,
        );
    }
}
