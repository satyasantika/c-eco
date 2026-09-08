<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pencacah galat dan waktu respons, disimpan di cache per menit.
 *
 * Sengaja di cache, bukan tabel: SPEC §6 sudah lengkap, dan menambah tabel
 * metrik berarti mengubah skema untuk data yang umurnya lima menit. Cache
 * hilang saat Redis di-restart, dan itu tidak apa-apa — angka ini untuk
 * pengawas yang sedang melihat layar, bukan untuk analisis.
 */
class ResponseMetrics
{
    /** Sampel durasi per menit dibatasi supaya satu kunci cache tidak membengkak. */
    private const MAX_SAMPLES = 400;

    private const TTL_SECONDS = 900;

    public function record(string $route, int $status, float $durationMs): void
    {
        $key = $this->key($route, $this->minute());

        $bucket = Cache::get($key, ['n' => 0, 'e4' => 0, 'e5' => 0, 'ms' => []]);

        $bucket['n']++;

        if ($status >= 500) {
            $bucket['e5']++;
        } elseif ($status >= 400) {
            $bucket['e4']++;
        }

        if (count($bucket['ms']) < self::MAX_SAMPLES) {
            $bucket['ms'][] = (int) round($durationMs);
        }

        Cache::put($key, $bucket, self::TTL_SECONDS);
    }

    /**
     * @return array{requests: int, rate_4xx: float, rate_5xx: float, mean_ms: ?float, p95_ms: ?int}
     */
    public function snapshot(string $route, int $minutes = 5): array
    {
        $requests = 0;
        $errors4 = 0;
        $errors5 = 0;
        $samples = [];

        for ($back = 0; $back < $minutes; $back++) {
            $bucket = Cache::get($this->key($route, $this->minute($back)));

            if ($bucket === null) {
                continue;
            }

            $requests += $bucket['n'];
            $errors4 += $bucket['e4'];
            $errors5 += $bucket['e5'];
            $samples = array_merge($samples, $bucket['ms']);
        }

        sort($samples);

        return [
            'requests' => $requests,
            'rate_4xx' => $requests === 0 ? 0.0 : 100 * $errors4 / $requests,
            'rate_5xx' => $requests === 0 ? 0.0 : 100 * $errors5 / $requests,
            'mean_ms' => $samples === [] ? null : array_sum($samples) / count($samples),
            'p95_ms' => $samples === [] ? null : $samples[(int) floor(0.95 * (count($samples) - 1))],
        ];
    }

    private function minute(int $back = 0): string
    {
        return now()->subMinutes($back)->format('YmdHi');
    }

    private function key(string $route, string $minute): string
    {
        return "metrics:{$route}:{$minute}";
    }
}
