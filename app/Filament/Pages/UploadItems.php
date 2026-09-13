<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Actions\ImportItemPackageAction;
use App\Models\User;
use App\Support\ItemPackageFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unggah paket soal JSON. Butir tidak diketik di panel.
 */
class UploadItems extends Page
{
    protected string $view = 'filament.pages.upload-items';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Unggah soal';

    protected static ?string $title = 'Unggah soal';

    protected static string|\UnitEnum|null $navigationGroup = 'Bank soal';

    protected static ?int $navigationSort = 28;

    protected static ?string $slug = 'unggah-soal';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canEditItems();
    }

    public function getHeading(): string
    {
        return 'Unggah soal';
    }

    public function getSubheading(): ?string
    {
        return 'Butir masuk sebagai paket JSON. Kode yang sudah ada tidak ditimpa.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadExample')
                ->label('Unduh contoh JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadExample()),
            ImportItemPackageAction::make()
                ->label('Pilih berkas JSON'),
        ];
    }

    /** @return list<string> */
    public function rules(): array
    {
        return ItemPackageFormat::rules();
    }

    public function sampleJson(): string
    {
        return ItemPackageFormat::sampleJson();
    }

    /** @return list<string> */
    public function rejectedExtensions(): array
    {
        return ItemPackageFormat::rejectedExtensions();
    }

    public function maxMegabytes(): int
    {
        return ItemPackageFormat::maxMegabytes();
    }

    private function downloadExample(): StreamedResponse
    {
        $json = ItemPackageFormat::sampleJson();

        return response()->streamDownload(
            function () use ($json): void {
                echo $json;
            },
            ItemPackageFormat::EXAMPLE_FILENAME,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
