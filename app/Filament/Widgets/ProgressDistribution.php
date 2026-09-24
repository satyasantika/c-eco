<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\MonitorScope;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Sebaran butir ke-berapa yang sedang dikerjakan peserta aktif.
 *
 * Bentuk sebaran ini memberi tahu pengawas kapan gelombang selesai akan datang,
 * dan menunjukkan peserta yang tertinggal jauh di belakang rombongan.
 */
class ProgressDistribution extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Posisi butir peserta aktif';

    protected ?string $pollingInterval = '15s';

    protected function getData(): array
    {
        $counts = MonitorScope::sessions($this->pageFilters)
            ->where('status', 'in_progress')
            ->selectRaw('items_administered as n, count(*) as total')
            ->groupBy('items_administered')
            ->orderBy('items_administered')
            ->pluck('total', 'n');

        return [
            'datasets' => [[
                'label' => 'Peserta',
                'data' => $counts->values()->all(),
            ]],
            'labels' => $counts->keys()->map(static fn ($n): string => 'butir '.($n + 1))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
