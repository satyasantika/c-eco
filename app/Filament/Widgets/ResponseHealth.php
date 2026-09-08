<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\ResponseMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Kesehatan endpoint /answer selama lima menit terakhir.
 *
 * Anggaran SPEC §8: p95 /answer ≤ 800 ms, galat 5xx < 0,5%.
 */
class ResponseHealth extends StatsOverviewWidget
{
    private const P95_BUDGET_MS = 800;

    private const ERROR_BUDGET_PERCENT = 0.5;

    protected ?string $pollingInterval = '10s';

    protected ?string $heading = 'Kesehatan /answer — 5 menit terakhir';

    protected function getStats(): array
    {
        $answer = app(ResponseMetrics::class)->snapshot('answer', minutes: 5);

        return [
            Stat::make('Permintaan', (string) $answer['requests'])
                ->description('POST /answer')
                ->color('gray'),

            Stat::make('p95', $answer['p95_ms'] === null ? '—' : $answer['p95_ms'].' ms')
                ->description('Anggaran SPEC §8: ≤ '.self::P95_BUDGET_MS.' ms')
                ->color(match (true) {
                    $answer['p95_ms'] === null => 'gray',
                    $answer['p95_ms'] <= self::P95_BUDGET_MS => 'success',
                    default => 'danger',
                }),

            Stat::make('Rata-rata', $answer['mean_ms'] === null ? '—' : round($answer['mean_ms']).' ms')
                ->color('gray'),

            Stat::make('Galat 5xx', number_format($answer['rate_5xx'], 2).'%')
                ->description('Anggaran: < '.self::ERROR_BUDGET_PERCENT.'%')
                ->color($answer['rate_5xx'] < self::ERROR_BUDGET_PERCENT ? 'success' : 'danger'),

            Stat::make('Galat 4xx', number_format($answer['rate_4xx'], 2).'%')
                // 409 yang wajar ikut terhitung di sini: outbox memang
                // mengirim ulang. Angka yang meloncat tetap layak dilihat.
                ->description('Termasuk 409 konflik urutan')
                ->color($answer['rate_4xx'] > 10 ? 'warning' : 'gray'),
        ];
    }
}
