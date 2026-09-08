<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Tables;

use App\Models\Item;
use App\Models\ItemBank;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('itemBank.grade')->label('Jenjang')->badge()->sortable(),
                TextColumn::make('itemBank.version')->label('Paket')->toggleable(),
                TextColumn::make('dimension.name')->label('Dimensi')->badge()->searchable(),
                TextColumn::make('topic')->label('Topik')->limit(40)->searchable()->toggleable(),
                TextColumn::make('activeParameter.b')
                    ->label('b')
                    ->numeric(2)
                    ->placeholder('belum dikalibrasi')
                    ->description(fn (Item $record): ?string => $record->activeParameter === null
                        ? null
                        : 'a = '.number_format((float) $record->activeParameter->a, 2)),
                IconColumn::make('has_warning')
                    ->label('Peringatan')
                    ->boolean()
                    ->state(fn (Item $record): bool => str_contains((string) $record->source_note, 'PERINGATAN'))
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('heroicon-o-check')
                    ->trueColor('warning')
                    ->falseColor('gray'),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('item_bank_id')
                    ->label('Paket')
                    ->options(fn (): array => ItemBank::query()
                        ->orderBy('grade')
                        ->orderBy('version')
                        ->get()
                        ->mapWithKeys(fn (ItemBank $bank): array => [$bank->id => $bank->label()])
                        ->all()),
                SelectFilter::make('dimension_id')->label('Dimensi')->relationship('dimension', 'name'),
                SelectFilter::make('status')->label('Status')->options(['active' => 'Aktif', 'retired' => 'Dipensiunkan']),
                Filter::make('needs_review')
                    ->label('Ada peringatan ekstraksi')
                    ->query(fn ($query) => $query->where('source_note', 'like', '%PERINGATAN%')),
                Filter::make('uncalibrated')
                    ->label('Belum punya parameter aktif')
                    ->query(fn ($query) => $query->whereDoesntHave('parameters', fn ($q) => $q->where('is_active', true))),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            // Tanpa aksi hapus, tunggal maupun massal: session_items menunjuk
            // ke butir, dan menghapus satu butir berarti membuang jejak
            // jawaban peserta yang sudah mengerjakannya.
            ->toolbarActions([])
            ->defaultSort('code');
    }
}
