<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\LiveSessions;
use App\Filament\Widgets\SessionOverview;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Layar yang dibuka pengawas selama pelaksanaan.
 *
 * Menyegarkan diri sendiri lewat polling: pengawas sedang berkeliling ruangan,
 * tidak sedang menekan tombol muat ulang.
 */
class Monitor extends Page
{
    protected string $view = 'filament.pages.monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Monitor';

    protected static ?string $title = 'Monitor Pelaksanaan';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            SessionOverview::class,
            LiveSessions::class,
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->getWidgets();
    }

    public function getColumns(): int|array
    {
        return 3;
    }
}
