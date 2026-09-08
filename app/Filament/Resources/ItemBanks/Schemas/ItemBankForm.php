<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemBanks\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ItemBankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('grade')
                ->label('Jenjang')
                ->disabled()
                ->dehydrated(false),
            TextInput::make('version')
                ->label('Versi')
                ->disabled()
                ->dehydrated(false)
                ->helperText('Identitas paket. Tidak diubah setelah impor — sesi menunjuk ke paket ini.'),
            TextInput::make('irt_model')
                ->label('Model IRT')
                ->disabled()
                ->dehydrated(false),
            Toggle::make('is_active')
                ->label('Aktif')
                ->helperText('Paket nonaktif tidak dipilih untuk sesi baru. Data sesi lama tetap utuh.'),
            Textarea::make('scale_note')
                ->label('Catatan skala')
                ->rows(3)
                ->columnSpanFull(),
            TextInput::make('calibration_source')
                ->label('Sumber')
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull(),
        ]);
    }
}
