<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Tables;

use App\Models\TestConfig;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TestConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('itemBank.grade')->label('Bank')->badge(),
                TextColumn::make('mode')->label('Mode')->badge(),
                TextColumn::make('package_items_count')
                    ->label('Butir dipilih')
                    ->counts('packageItems')
                    ->placeholder('seluruh bank'),
                TextColumn::make('mix')
                    ->label('Campuran')
                    ->state(function (TestConfig $record): string {
                        if (! $record->usesGradeShares()) {
                            return '—';
                        }

                        return "{$record->grade_share_x}% X · {$record->grade_share_xi}% XI · {$record->grade_share_xii}% XII";
                    }),
                TextColumn::make('min_items')->label('Min'),
                TextColumn::make('max_items')->label('Maks'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->filters([
                SelectFilter::make('mode')->options(['adaptive' => 'Adaptif', 'linear' => 'Linear']),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    /** @return list<string> */
    public static function searchable(): array
    {
        return [TestConfig::class];
    }
}
