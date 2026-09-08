<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ConnectionMix;
use App\Filament\Widgets\LiveSessions;
use App\Filament\Widgets\ProgressDistribution;
use App\Filament\Widgets\ResponseHealth;
use App\Filament\Widgets\SessionOverview;
use App\Services\DataExporter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Layar yang dibuka pengawas selama pelaksanaan.
 *
 * Menyegarkan diri lewat polling: pengawas sedang berkeliling ruangan, tidak
 * sedang menekan tombol muat ulang. Anggaran R1 tidak berlaku di halaman ini —
 * pengawas memakai laptop dan wifi venue, bukan kuota pribadi siswa.
 */
class Monitor extends Page
{
    protected string $view = 'filament.pages.monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Monitor';

    protected static ?string $title = 'Monitor Pelaksanaan';

    protected static ?int $navigationSort = 1;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Ekspor CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => auth()->user()?->canExport() ?? false)
                ->action(fn (): ?StreamedResponse => $this->export()),
        ];
    }

    public function getWidgets(): array
    {
        return [
            SessionOverview::class,
            ResponseHealth::class,
            ProgressDistribution::class,
            ConnectionMix::class,
            LiveSessions::class,
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->getWidgets();
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    /** Memanggil DataExporter yang sama dengan cat:export, lalu menyodorkan zip. */
    private function export(): ?StreamedResponse
    {
        try {
            $result = app(DataExporter::class)->export();
        } catch (RuntimeException $e) {
            Notification::make()->title('Ekspor gagal')->body($e->getMessage())->danger()->send();

            return null;
        }

        $zipPath = Storage::disk('local')->path($result['directory']).'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach (array_keys($result['files']) as $name) {
            $zip->addFile(Storage::disk('local')->path($result['directory'].'/'.$name), $name);
        }

        $zip->close();

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend();
    }
}
