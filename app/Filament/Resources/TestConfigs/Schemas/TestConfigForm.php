<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Schemas;

use App\Models\ItemBank;
use App\Models\TestConfig;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Paket ujian asli, disusun seperti gelombang simulasi: bawaannya gabungan
 * butir X + XI + XII dengan porsi persen dan jumlah butir sendiri.
 * "Satu bank jenjang" tetap tersedia sebagai pilihan kedua.
 */
class TestConfigForm
{
    public const SOURCE_MIXED = 'campuran';

    public const SOURCE_BANK = 'bank';

    public static function configure(Schema $schema): Schema
    {
        $defaults = TestConfigProvisioner::adaptiveAttributes();
        $mixed = fn (Get $get): bool => $get('source') !== self::SOURCE_BANK;

        return $schema->components([
            Section::make('Paket')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama paket')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: Campuran sesi pagi, atau Gabungan X–XII ruang 2.'),
                    Select::make('mode')
                        ->label('Mode')
                        ->options(['adaptive' => 'Adaptif', 'linear' => 'Linear'])
                        ->required()
                        ->default('adaptive')
                        ->native(false),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ])
                ->columns(3),

            Section::make('Isi paket')
                ->description('Seperti simulasi: butir X, XI, dan XII digabung dengan porsi yang Anda tentukan. Butir diambil dari bank, tidak disalin.')
                ->schema([
                    Radio::make('source')
                        ->label('Sumber butir')
                        ->options([
                            self::SOURCE_MIXED => 'Gabungan X + XI + XII dengan porsi',
                            self::SOURCE_BANK => 'Seluruh butir satu bank jenjang',
                        ])
                        ->default(self::SOURCE_MIXED)
                        ->afterStateHydrated(function (Radio $component, ?TestConfig $record): void {
                            if ($record !== null) {
                                $component->state($record->usesGradeShares() ? self::SOURCE_MIXED : self::SOURCE_BANK);
                            }
                        })
                        ->dehydrated(false)
                        ->live()
                        ->inline()
                        ->columnSpanFull(),
                    TextInput::make('grade_share_x')
                        ->label('Persen jenjang X')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(34)
                        ->live()
                        ->suffix('%')
                        ->visible($mixed)
                        ->required($mixed),
                    TextInput::make('grade_share_xi')
                        ->label('Persen jenjang XI')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(33)
                        ->live()
                        ->suffix('%')
                        ->visible($mixed)
                        ->required($mixed),
                    TextInput::make('grade_share_xii')
                        ->label('Persen jenjang XII')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(33)
                        ->live()
                        ->suffix('%')
                        ->visible($mixed)
                        ->required($mixed)
                        ->rules([self::shareSumRule()]),
                    TextInput::make('pool_size')
                        ->label('Jumlah butir dalam paket')
                        ->numeric()
                        ->minValue(1)
                        ->default(30)
                        ->live()
                        ->visible($mixed)
                        ->required($mixed)
                        ->columnSpanFull()
                        ->helperText(fn (Get $get): string => MixedPackageComposer::preview(
                            (int) $get('grade_share_x'),
                            (int) $get('grade_share_xi'),
                            (int) $get('grade_share_xii'),
                            $get('pool_size') !== null && $get('pool_size') !== '' ? (int) $get('pool_size') : null,
                        ).' Porsi 0% berarti jenjang itu tidak dipakai.')
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                $max = (int) $get('max_items');
                                if ((int) $value < $max) {
                                    $fail("Jumlah butir paket tidak boleh lebih kecil dari maksimal butir tes ({$max}).");
                                }
                            },
                        ]),
                    Select::make('item_bank_id')
                        ->label('Bank jenjang')
                        ->options(fn (): array => ItemBank::query()
                            ->orderBy('grade')
                            ->orderBy('version')
                            ->get()
                            ->mapWithKeys(fn (ItemBank $bank): array => [$bank->id => $bank->label()])
                            ->all())
                        ->visible(fn (Get $get): bool => ! $mixed($get))
                        ->required(fn (Get $get): bool => ! $mixed($get))
                        ->columnSpanFull()
                        ->helperText('Semua butir aktif di bank ini dipakai.'),
                ])
                ->columns(3),

            Section::make('Aturan berhenti')
                ->description('Bawaan sama dengan simulasi. Ubah hanya bila desain penelitian memintanya.')
                ->schema([
                    TextInput::make('min_items')->label('Minimal butir')->numeric()->required()->default($defaults['min_items']),
                    TextInput::make('max_items')->label('Maksimal butir')->numeric()->required()->default($defaults['max_items'])->live(),
                    TextInput::make('se_target')->label('SE target')->numeric()->required()->default($defaults['se_target']),
                    Toggle::make('shuffle_options')->label('Acak opsi')->default(true),
                ])
                ->columns(4)
                ->collapsible(),
        ]);
    }

    private static function shareSumRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $sum = (int) $get('grade_share_x') + (int) $get('grade_share_xi') + (int) $get('grade_share_xii');
                if ($sum !== 100) {
                    $fail("Jumlah persen X + XI + XII harus 100 (sekarang {$sum}).");
                }
            };
        };
    }
}
