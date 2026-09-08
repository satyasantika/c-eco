<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemBanks\Tables;

use App\Models\ItemBank;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemBanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('grade')->label('Jenjang')->badge()->sortable(),
                TextColumn::make('version')->label('Versi')->searchable()->sortable(),
                TextColumn::make('items_count')->label('Butir')->counts('items'),
                TextColumn::make('test_configs_count')->label('Konfigurasi')->counts('testConfigs'),
                TextColumn::make('irt_model')->label('Model')->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('calibration_source')->label('Sumber')->limit(40)->toggleable(),
            ])
            ->filters([
                SelectFilter::make('grade')->label('Jenjang')->options(['X' => 'X', 'XI' => 'XI', 'XII' => 'XII']),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([1 => 'Aktif', 0 => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('grade');
    }

    /** @return list<string> */
    public static function searchable(): array
    {
        return [ItemBank::class];
    }
}
