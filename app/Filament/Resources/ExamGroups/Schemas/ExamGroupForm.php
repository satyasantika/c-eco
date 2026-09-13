<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Schemas;

use App\Enums\UserRole;
use App\Models\TestConfig;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExamGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal')
                ->description('Satu rombongan = satu ruang + satu jam + satu pengawas + satu paket. Ruang yang sama pada jam berbeda adalah jadwal baru.')
                ->schema([
                    Select::make('school_id')
                        ->label('Sekolah')
                        ->relationship('school', 'name')
                        ->searchable()
                        ->preload()
                        ->required(),
                    TextInput::make('name')
                        ->label('Nama rombongan')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Contoh: XI IPS 1 sesi pagi.'),
                    TextInput::make('room')
                        ->label('Ruang')
                        ->required()
                        ->maxLength(64),
                    DateTimePicker::make('starts_at')
                        ->label('Waktu mulai')
                        ->required()
                        ->seconds(false)
                        ->native(false),
                    Select::make('supervisor_id')
                        ->label('Pengawas')
                        ->options(fn (): array => User::query()
                            ->where('role', UserRole::Pengawas)
                            ->where('is_active', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Satu pengawas per rombongan. Pengawas membuka kartu QR di HP.'),
                    Select::make('test_config_id')
                        ->label('Paket ujian')
                        ->options(fn (): array => TestConfig::query()
                            ->where('is_active', true)
                            ->whereNull('exam_simulation_id')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->required()
                        ->searchable()
                        ->helperText('Paket disusun di menu Paket Ujian (operator).'),
                    TextInput::make('capacity')
                        ->label('Jumlah siswa (kursi token)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(80)
                        ->helperText('Satu token per siswa di ruangan itu. Setelah disimpan, kursi QR dibuat otomatis.'),
                    Textarea::make('notes')->label('Catatan')->rows(2)->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
