<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Tables;

use App\Models\TestConfig;
use App\Services\MixedPackageComposer;
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
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->state(fn (TestConfig $record): string => $record->usesGradeShares() ? 'Gabungan' : 'Bank '.$record->itemBank?->grade),
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

                        $counts = MixedPackageComposer::allocate(
                            (int) $record->pool_size,
                            (int) $record->grade_share_x,
                            (int) $record->grade_share_xi,
                            (int) $record->grade_share_xii,
                        );

                        return "X {$record->grade_share_x}% ({$counts['X']}) · XI {$record->grade_share_xi}% ({$counts['XI']}) · XII {$record->grade_share_xii}% ({$counts['XII']})";
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
