<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * CSV / TSV / copas Excel menjadi baris asosiatif. Header wajib ada.
 */
final class DelimitedTable
{
    public const MAX_ROWS = 2000;

    /**
     * @param  list<string>  $required
     * @param  array<string, list<string>>  $aliases
     * @param  list<string>  $optional
     * @return list<array<string, string>>
     */
    public static function parse(string $text, array $required, array $aliases = [], array $optional = []): array
    {
        $text = self::normalize($text);

        if ($text === '') {
            throw new RuntimeException('Tidak ada data. Tempel baris atau unggah CSV.');
        }

        $delimiter = self::detectDelimiter(strtok($text, "\n") ?: $text);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $headerRow = fgetcsv($handle, 0, $delimiter, '"', '\\');

        if ($headerRow === false) {
            fclose($handle);
            throw new RuntimeException('Baris judul tidak terbaca.');
        }

        $map = self::headerMap($headerRow, $required, $aliases, $optional);
        $rows = [];
        $problems = [];
        $line = 1;

        while (($raw = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            $line++;

            if (self::isEmptyRow($raw)) {
                continue;
            }

            $row = [];
            foreach ($map as $index => $column) {
                $row[$column] = trim((string) ($raw[$index] ?? ''));
            }

            foreach ($required as $column) {
                if (($row[$column] ?? '') === '') {
                    $problems[] = "baris {$line}: kolom {$column} kosong";
                }
            }

            $rows[] = ['line' => $line, 'values' => $row];
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException('Tidak ada baris data di bawah judul kolom.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Paling banyak '.self::MAX_ROWS.' baris sekali impor.');
        }

        if ($problems !== []) {
            throw new RuntimeException("Impor dibatalkan:\n  - ".implode("\n  - ", $problems));
        }

        return array_map(static fn (array $row): array => $row['values'] + ['_line' => (string) $row['line']], $rows);
    }

    /**
     * @param  list<string>  $required
     * @param  array<string, list<string>>  $aliases
     * @param  list<string>  $optional
     * @return list<array<string, string>>
     */
    public static function fromFile(string $path, array $required, array $aliases = [], array $optional = []): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas tidak ditemukan: {$path}");
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('Berkas tidak terbaca.');
        }

        return self::parse($raw, $required, $aliases, $optional);
    }

    private static function normalize(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim($text);
    }

    private static function detectDelimiter(string $firstLine): string
    {
        $counts = [
            "\t" => substr_count($firstLine, "\t"),
            ';' => substr_count($firstLine, ';'),
            ',' => substr_count($firstLine, ','),
        ];
        arsort($counts);
        $best = (string) array_key_first($counts);

        return ($counts[$best] ?? 0) > 0 ? $best : ',';
    }

    /**
     * @param  list<string|null>  $headerRow
     * @param  list<string>  $required
     * @param  array<string, list<string>>  $aliases
     * @param  list<string>  $optional
     * @return array<int, string>
     */
    private static function headerMap(array $headerRow, array $required, array $aliases, array $optional): array
    {
        $lookup = [];
        foreach ([...$required, ...$optional] as $canonical) {
            $lookup[self::key($canonical)] = $canonical;
        }
        foreach ($aliases as $canonical => $alts) {
            foreach ($alts as $alias) {
                $lookup[self::key($alias)] = $canonical;
            }
        }

        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = self::key((string) $cell);
            if ($key === '') {
                continue;
            }
            if (! isset($lookup[$key])) {
                continue;
            }
            $map[(int) $index] = $lookup[$key];
        }

        $found = array_values($map);
        $missing = array_values(array_diff($required, $found));

        if ($missing !== []) {
            throw new RuntimeException(
                'Baris pertama harus judul kolom. Wajib: '.implode(', ', $required)
                .'. Yang belum ada: '.implode(', ', $missing).'.'
            );
        }

        return $map;
    }

    /** @param  list<string|null>  $raw */
    private static function isEmptyRow(array $raw): bool
    {
        foreach ($raw as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function key(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace([' ', '-'], '_', $value);

        return $value;
    }
}
