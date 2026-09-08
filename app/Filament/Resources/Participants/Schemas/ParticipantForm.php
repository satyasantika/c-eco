<?php

declare(strict_types=1);

namespace App\Filament\Resources\Participants\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('school_id')
                ->label('Sekolah')
                ->relationship('school', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('class_name')->label('Kelas')->required()->maxLength(255),
            TextInput::make('student_code')
                ->label('Kode siswa')
                ->required()
                ->maxLength(255)
                ->helperText('Unik dalam satu sekolah. Dipakai untuk mencocokkan impor ulang.'),
            TextInput::make('display_name')->label('Nama')->required()->maxLength(255),
        ]);
    }
}
