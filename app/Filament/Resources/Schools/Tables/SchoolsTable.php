<?php

declare(strict_types=1);

namespace App\Filament\Resources\Schools\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('city')->label('Kota')->searchable(),
                TextColumn::make('participants_count')->label('Peserta')->counts('participants'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([])
            ->defaultSort('name');
    }
}
