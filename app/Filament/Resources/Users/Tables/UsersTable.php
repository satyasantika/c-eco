<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (UserRole $state): string => $state->label()),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Diubah')->since()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Peran')
                    ->options(
                        collect(UserRole::cases())
                            ->mapWithKeys(fn (UserRole $role): array => [$role->value => $role->label()])
                            ->all(),
                    ),
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Nonaktifkan')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record): bool => self::canToggle($record) && $record->is_active)
                    ->action(function (User $record): void {
                        $record->update(['is_active' => false]);
                        Notification::make()->title("{$record->name} dinonaktifkan.")->success()->send();
                    }),
                Action::make('activate')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (User $record): bool => self::canToggle($record) && ! $record->is_active)
                    ->action(function (User $record): void {
                        $record->update(['is_active' => true]);
                        Notification::make()->title("{$record->name} diaktifkan kembali.")->success()->send();
                    }),
            ])
            ->toolbarActions([])
            ->defaultSort('name');
    }

    private static function canToggle(User $record): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->isAdmin()
            && $record->isStaff()
            && $record->isNot($actor);
    }
}
