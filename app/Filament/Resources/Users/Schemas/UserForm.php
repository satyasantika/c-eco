<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->label('Peran')
                ->options(UserRole::staffOptions())
                ->required()
                ->native(false)
                ->disabled(fn (?User $record): bool => $record?->isAdmin() ?? false),
            TextInput::make('password')
                ->label('Kata sandi')
                ->password()
                ->revealable()
                ->extraInputAttributes(['type' => 'password'])
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state)),
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->visibleOn('edit')
                ->disabled(fn (?User $record): bool => $record !== null && ($record->isAdmin() || $record->is(auth()->user()))),
        ]);
    }
}
