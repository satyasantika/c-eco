<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\MonitorScope;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Sebaran kualitas koneksi peserta.
 *
 * Kalau sebagian besar peserta ternyata di 3G atau 2G, itu penjelasan pertama
 * untuk waktu respons yang melar — dan alasan mengapa anggaran R1 ada.
 */
class ConnectionMix extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Koneksi peserta';

    protected ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $counts = MonitorScope::sessions($this->pageFilters)
            ->whereIn('status', ['in_progress', 'completed'])
            ->selectRaw('coalesce(effective_connection, ?) as kind, count(*) as total', ['tidak dilaporkan'])
            ->groupBy('kind')
            ->orderByDesc('total')
            ->pluck('total', 'kind');

        return [
            'datasets' => [[
                'label' => 'Sesi',
                'data' => $counts->values()->all(),
            ]],
            'labels' => $counts->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
