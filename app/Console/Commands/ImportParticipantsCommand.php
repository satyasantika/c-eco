<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Participant;
use App\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportParticipantsCommand extends Command
{
    public const HEADER = ['school', 'class_name', 'student_code', 'display_name'];

    protected $signature = 'cat:import-participants
        {file : CSV berkolom '.'school,class_name,student_code,display_name}
        {--city= : Kota untuk sekolah yang baru dibuat}';

    protected $description = 'Mengimpor daftar peserta dari CSV';

    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false || array_map('trim', $header) !== self::HEADER) {
            fclose($handle);
            $this->error('Header CSV harus persis: '.implode(',', self::HEADER));

            return self::FAILURE;
        }

        $rows = [];
        $problems = [];
        $line = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $line++;

            if ($raw === [null] || $raw === []) {
                continue;
            }

            if (count($raw) !== count(self::HEADER)) {
                $problems[] = "baris {$line}: butuh 4 kolom, ada ".count($raw);

                continue;
            }

            $row = array_combine(self::HEADER, array_map(static fn ($c): string => trim((string) $c), $raw));

            foreach (self::HEADER as $column) {
                if ($row[$column] === '') {
                    $problems[] = "baris {$line}: {$column} kosong";
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        if ($problems !== []) {
            $this->error("Impor dibatalkan:\n  - ".implode("\n  - ", $problems));

            return self::FAILURE;
        }

        $created = 0;
        $existing = 0;

        DB::transaction(function () use ($rows, &$created, &$existing): void {
            foreach ($rows as $row) {
                $school = School::query()->firstOrCreate(
                    ['name' => $row['school']],
                    ['city' => $this->option('city')],
                );

                // student_code unik per sekolah: impor ulang daftar yang sama
                // tidak menggandakan peserta, dan peserta yang sudah punya
                // token tetap memakai token yang sama.
                $participant = Participant::query()->firstOrNew([
                    'school_id' => $school->id,
                    'student_code' => $row['student_code'],
                ]);

                if ($participant->exists) {
                    $existing++;

                    continue;
                }

                $participant->fill([
                    'class_name' => $row['class_name'],
                    'display_name' => $row['display_name'],
                ])->save();

                $created++;
            }
        });

        $this->info("{$created} peserta baru, {$existing} sudah ada.");

        return self::SUCCESS;
    }
}
