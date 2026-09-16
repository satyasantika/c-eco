<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\ItemPackageImporter;
use App\Support\ItemPackageFormat;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ImportItemPackageAction
{
    public static function make(): Action
    {
        return Action::make('importPackage')
            ->label('Unggah soal')
            ->modalHeading('Unggah soal')
            ->modalDescription('Hanya JSON. Format lengkap dan contoh ada di menu Unggah soal.')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->canEditItems())
            ->schema([
                Select::make('grade')
                    ->label('Jenjang')
                    ->options(['X' => 'X', 'XI' => 'XI', 'XII' => 'XII'])
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($state !== null && $state !== '') {
                            $set('version', ItemPackageImporter::suggestVersion($state));
                        }
                    }),
                TextInput::make('version')
                    ->label('Versi paket')
                    ->required()
                    ->maxLength(32)
                    ->helperText('Jenjang + versi adalah identitas paket. Versi baru = paket baru. Versi yang sudah ada hanya menerima butir berkode baru.'),
                FileUpload::make('json')
                    ->label('Berkas .json')
                    ->acceptedFileTypes(ItemPackageFormat::MIME_TYPES)
                    ->rules(['extensions:'.ItemPackageFormat::EXTENSION])
                    ->validationMessages([
                        'mimetypes' => 'Berkas harus JSON. Soal yang banyak tabel HTML kadang terdeteksi sebagai halaman web — itu biasa, unggah ulang berkas .json.',
                        'extensions' => 'Hanya berkas berekstensi .json.',
                    ])
                    ->maxSize(ItemPackageFormat::MAX_KILOBYTES)
                    ->storeFiles(false)
                    ->required()
                    ->helperText(new HtmlString(
                        e(ItemPackageFormat::shortHelper())
                        .' Ditolak: '.e(implode(', ', ItemPackageFormat::rejectedExtensions())).'.'
                    )),
                Toggle::make('provisional')
                    ->label('Buat parameter sementara')
                    ->default(true)
                    ->helperText('Tanpa parameter aktif, butir tidak bisa disajikan dalam tes. Ganti nanti dengan cat:import-parameters setelah kalibrasi.'),
            ])
            ->action(function (array $data): void {
                $path = self::pathOf($data['json'] ?? null);

                try {
                    $counts = app(ItemPackageImporter::class)->importFromFile(
                        path: $path,
                        grade: (string) $data['grade'],
                        version: (string) $data['version'],
                        provisional: (bool) ($data['provisional'] ?? true),
                    );
                } catch (RuntimeException $e) {
                    Notification::make()
                        ->title('Impor ditolak')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($counts['created']
                        ? "Paket {$counts['bank']->label()} dibuat"
                        : "Paket {$counts['bank']->label()} ditambah")
                    ->body("{$counts['items']} butir, {$counts['options']} opsi, {$counts['parameters']} parameter sementara.")
                    ->success()
                    ->send();
            });
    }

    private static function pathOf(mixed $file): string
    {
        if (is_array($file)) {
            $file = reset($file);
        }

        if ($file instanceof TemporaryUploadedFile) {
            $path = $file->getRealPath();

            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        if (is_string($file) && is_file($file)) {
            return $file;
        }

        throw new RuntimeException('Berkas JSON tidak terbaca. Unggah ulang berkasnya.');
    }
}
