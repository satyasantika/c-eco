<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\ParticipantImporter;
use App\Services\SchoolImporter;
use App\Services\UserImporter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

class ImportRosterAction
{
    /** @var list<string> */
    public const FILE_MIME = [
        'text/csv',
        'text/plain',
        'text/tab-separated-values',
        'application/csv',
        'application/vnd.ms-excel',
        'application/octet-stream',
        'text/html',
    ];

    public static function schools(): Action
    {
        return self::make(
            name: 'importSchools',
            heading: 'Impor sekolah',
            description: 'Kolom wajib: name. Opsional: city. Judul boleh bahasa Indonesia (nama, kota).',
            example: "name,city\nSMA Negeri 1 Tasikmalaya,Tasikmalaya\nSMA Negeri 2 Tasikmalaya,Tasikmalaya\n",
            import: fn (string $text, array $data): array => app(SchoolImporter::class)->importText($text),
            visible: fn (): bool => auth()->user() instanceof User && auth()->user()->canManageRoster(),
            createdLabel: 'sekolah baru',
        );
    }

    public static function participants(): Action
    {
        return self::make(
            name: 'importParticipants',
            heading: 'Impor peserta',
            description: 'Kolom wajib: school, class_name, student_code, display_name. Opsional: city. Kode siswa unik per sekolah; yang sudah ada dilewati.',
            example: "school,class_name,student_code,display_name\nSMA Negeri 1 Tasikmalaya,XI IPS 1,2026001,Sinta Lestari\nSMA Negeri 1 Tasikmalaya,XI IPS 1,2026002,Raka Putra\n",
            import: fn (string $text, array $data): array => app(ParticipantImporter::class)->importText(
                $text,
                isset($data['city']) && is_string($data['city']) ? $data['city'] : null,
            ),
            extraSchema: [
                TextInput::make('city')
                    ->label('Kota untuk sekolah baru')
                    ->maxLength(255)
                    ->helperText('Dipakai jika kolom city kosong. Sekolah yang sudah ada tidak diubah.'),
            ],
            visible: fn (): bool => auth()->user() instanceof User && auth()->user()->canManageRoster(),
            createdLabel: 'peserta baru',
        );
    }

    public static function users(): Action
    {
        return self::make(
            name: 'importUsers',
            heading: 'Impor pengguna',
            description: 'Kolom wajib: name, email, role (operator / pengawas / peneliti). Opsional: password. Admin tidak boleh diimpor massal. Email yang sudah ada dilewati.',
            example: "name,email,role,password\nSiti Aminah,siti@sekolah.sch.id,pengawas,\nBudi Santoso,budi@sekolah.sch.id,operator,\n",
            import: fn (string $text, array $data): array => app(UserImporter::class)->importText(
                $text,
                isset($data['default_password']) && is_string($data['default_password']) ? $data['default_password'] : null,
            ),
            extraSchema: [
                TextInput::make('default_password')
                    ->label('Kata sandi bila kolom kosong')
                    ->password()
                    ->revealable()
                    ->extraInputAttributes(['type' => 'password'])
                    ->minLength(8)
                    ->helperText('Minimal 8 karakter. Dipakai hanya untuk baris tanpa kolom password.'),
            ],
            visible: fn (): bool => auth()->user() instanceof User && auth()->user()->canManageStaff(),
            createdLabel: 'akun baru',
        );
    }

    /**
     * @param  callable(string, array<string, mixed>): array{created: int, skipped: int}  $import
     * @param  list<\Filament\Schemas\Components\Component>  $extraSchema
     */
    private static function make(
        string $name,
        string $heading,
        string $description,
        string $example,
        callable $import,
        array $extraSchema = [],
        ?\Closure $visible = null,
        string $createdLabel = 'baris baru',
    ): Action {
        return Action::make($name)
            ->label('Impor massal')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalHeading($heading)
            ->modalDescription($description)
            ->visible($visible ?? fn (): bool => false)
            ->schema([
                Radio::make('source')
                    ->label('Sumber')
                    ->options([
                        'paste' => 'Tempel dari Excel',
                        'file' => 'Unggah CSV',
                    ])
                    ->default('paste')
                    ->inline()
                    ->live()
                    ->required(),
                Textarea::make('paste')
                    ->label('Tempel baris')
                    ->rows(12)
                    ->placeholder($example)
                    ->extraInputAttributes(['spellcheck' => 'false', 'autocapitalize' => 'off'])
                    ->helperText(new HtmlString(
                        'Baris pertama judul kolom. Copas dari Excel boleh tab, koma, atau titik koma.'
                    ))
                    ->visible(fn (Get $get): bool => $get('source') === 'paste')
                    ->required(fn (Get $get): bool => $get('source') === 'paste'),
                FileUpload::make('file')
                    ->label('Berkas CSV')
                    ->acceptedFileTypes(self::FILE_MIME)
                    ->rules(['extensions:csv,txt,tsv'])
                    ->validationMessages([
                        'mimetypes' => 'Unggah CSV atau teks. Simpan Excel sebagai CSV, bukan .xlsx.',
                        'extensions' => 'Hanya .csv, .txt, atau .tsv.',
                    ])
                    ->maxSize(2048)
                    ->storeFiles(false)
                    ->helperText('Bukan .xlsx. Di Excel: Simpan sebagai → CSV (koma atau titik koma).')
                    ->visible(fn (Get $get): bool => $get('source') === 'file')
                    ->required(fn (Get $get): bool => $get('source') === 'file'),
                ...$extraSchema,
            ])
            ->action(function (array $data) use ($import, $createdLabel, $heading): void {
                try {
                    $text = self::textOf($data);
                    $counts = $import($text, $data);
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
                    ->title($heading.' selesai')
                    ->body("{$counts['created']} {$createdLabel}, {$counts['skipped']} sudah ada dan dilewati.")
                    ->success()
                    ->send();
            });
    }

    /** @param  array<string, mixed>  $data */
    private static function textOf(array $data): string
    {
        if (($data['source'] ?? 'paste') === 'file') {
            $path = self::pathOf($data['file'] ?? null);
            $raw = file_get_contents($path);

            if ($raw === false) {
                throw new RuntimeException('Berkas CSV tidak terbaca. Unggah ulang.');
            }

            return $raw;
        }

        $paste = $data['paste'] ?? '';

        if (! is_string($paste) || trim($paste) === '') {
            throw new RuntimeException('Tempel baris data, atau pilih unggah CSV.');
        }

        return $paste;
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

        throw new RuntimeException('Berkas CSV tidak terbaca. Unggah ulang.');
    }
}
