<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Schemas;

use App\Models\ItemBank;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TestConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        $defaults = TestConfigProvisioner::adaptiveAttributes();

        return $schema->components([
            TextInput::make('name')
                ->label('Nama paket')
                ->required()
                ->maxLength(255)
                ->helperText('Contoh: Campuran sesi pagi, atau Adaptif XI ruang 2.'),
            Select::make('item_bank_id')
                ->label('Bank acuan')
                ->options(fn (): array => ItemBank::query()
                    ->orderBy('grade')
                    ->orderBy('version')
                    ->get()
                    ->mapWithKeys(fn (ItemBank $bank): array => [$bank->id => $bank->label()])
                    ->all())
                ->required()
                ->helperText('Cadangan jika persen di bawah semua 0: seluruh bank jenjang itu yang dipakai.'),
            Select::make('mode')
                ->label('Mode')
                ->options(['adaptive' => 'Adaptif', 'linear' => 'Linear'])
                ->required()
                ->default('adaptive')
                ->native(false),
            TextInput::make('min_items')->label('Minimal butir')->numeric()->required()->default($defaults['min_items']),
            TextInput::make('max_items')->label('Maksimal butir')->numeric()->required()->default($defaults['max_items'])->live(),
            TextInput::make('se_target')->label('SE target')->numeric()->required()->default($defaults['se_target']),
            Toggle::make('shuffle_options')->label('Acak opsi')->default(true),
            Toggle::make('is_active')->label('Aktif')->default(true),

            Section::make('Campuran jenjang')
                ->description('Isi persen X, XI, dan XII sampai 100, lalu jumlah butir. Kosongkan persen (semua 0) untuk memakai seluruh bank acuan.')
                ->schema([
                    TextInput::make('grade_share_x')
                        ->label('Persen jenjang X')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live()
                        ->suffix('%'),
                    TextInput::make('grade_share_xi')
                        ->label('Persen jenjang XI')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live()
                        ->suffix('%'),
                    TextInput::make('grade_share_xii')
                        ->label('Persen jenjang XII')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(0)
                        ->live()
                        ->suffix('%')
                        ->rules([self::shareSumRule()]),
                    TextInput::make('pool_size')
                        ->label('Jumlah butir dalam paket')
                        ->numeric()
                        ->minValue(1)
                        ->live()
                        ->required(fn (Get $get): bool => self::shareSum($get) === 100)
                        ->helperText(fn (Get $get): string => MixedPackageComposer::preview(
                            (int) $get('grade_share_x'),
                            (int) $get('grade_share_xi'),
                            (int) $get('grade_share_xii'),
                            $get('pool_size') !== null && $get('pool_size') !== '' ? (int) $get('pool_size') : null,
                        ))
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (self::shareSum($get) !== 100) {
                                    return;
                                }
                                $max = (int) $get('max_items');
                                if ((int) $value < $max) {
                                    $fail("Jumlah butir paket tidak boleh lebih kecil dari maksimal butir tes ({$max}).");
                                }
                            },
                        ]),
                ])
                ->columns(2),
        ]);
    }

    private static function shareSum(Get $get): int
    {
        return (int) $get('grade_share_x') + (int) $get('grade_share_xi') + (int) $get('grade_share_xii');
    }

    private static function shareSumRule(): Closure
    {
        return function (Get $get): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                $sum = TestConfigForm::shareSum($get);
                if ($sum !== 0 && $sum !== 100) {
                    $fail('Jumlah persen X + XI + XII harus 100, atau ketiga-tiganya 0 untuk memakai seluruh bank acuan.');
                }
            };
        };
    }
}
