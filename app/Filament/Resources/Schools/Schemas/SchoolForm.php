<?php

declare(strict_types=1);

namespace App\Filament\Resources\Schools\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SchoolForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama sekolah')->required()->maxLength(255),
            TextInput::make('city')->label('Kota')->maxLength(255),
        ]);
    }
}
