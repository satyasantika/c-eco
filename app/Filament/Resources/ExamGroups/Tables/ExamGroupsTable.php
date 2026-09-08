<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Tables;

use App\Models\ExamGroup;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ExamGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('school.name')->label('Sekolah')->searchable()->sortable(),
                TextColumn::make('name')->label('Rombongan')->searchable(),
                TextColumn::make('room')->label('Ruang')->searchable(),
                TextColumn::make('starts_at')->label('Waktu')->dateTime('d M H:i')->sortable(),
                TextColumn::make('supervisor.name')->label('Pengawas')->placeholder('—'),
                TextColumn::make('testConfig.name')->label('Paket')->wrap(),
                TextColumn::make('progress')
                    ->label('Muat / kursi')
                    ->state(fn (ExamGroup $record): string => $record->openedCount().' / '.$record->capacity),
            ])
            ->filters([
                SelectFilter::make('school_id')->label('Sekolah')->relationship('school', 'name'),
                SelectFilter::make('supervisor_id')->label('Pengawas')->relationship('supervisor', 'name'),
                SelectFilter::make('test_config_id')->label('Paket')->relationship('testConfig', 'name'),
            ])
            ->groups([
                Group::make('starts_at')
                    ->label('Hari')
                    ->getTitleFromRecordUsing(fn (ExamGroup $record): string => $record->starts_at
                        ->timezone((string) config('app.timezone'))
                        ->translatedFormat('l, d M Y')),
                Group::make('school.name')->label('Sekolah'),
            ])
            ->defaultGroup('starts_at')
            ->recordActions([
                Action::make('qr')
                    ->label('Kartu QR')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn (ExamGroup $record): string => route('proctor.qr', $record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->canManageExamGroups() ?? false),
            ])
            ->toolbarActions([])
            ->defaultSort('starts_at');
    }
}
