<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Tables;

use App\Models\ExamSimulation;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamSimulationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('students')->label('Siswa')->sortable(),
                TextColumn::make('rooms')->label('Kelas')->sortable(),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('mix')
                    ->label('Porsi paket')
                    ->state(fn (ExamSimulation $record): string => "{$record->grade_share_x}% X · {$record->grade_share_xi}% XI · {$record->grade_share_xii}% XII · {$record->pool_size} butir"),
                TextColumn::make('pengawas_count')->label('Pengawas'),
                TextColumn::make('operators_count')->label('Operator'),
            ])
            ->recordActions([
                ViewAction::make()->label('Rincian'),
                DeleteAction::make()
                    ->label('Hapus')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus simulasi ini?')
                    ->modalDescription('Akun operator/pengawas gelombang ini, jadwal, kursi, dan paketnya dihapus. Bank soal dan tes asli tidak berubah.'),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Belum ada gelombang simulasi')
            ->emptyStateDescription('Susun jumlah siswa, kelas, akun, dan porsi jenjang. Gelombang bisa dihapus utuh kapan saja.');
    }
}
