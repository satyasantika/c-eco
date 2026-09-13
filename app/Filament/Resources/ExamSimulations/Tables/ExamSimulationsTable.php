<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Tables;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamSimulationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Gelombang')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(fn (ExamSimulation $record): string => $record->staffLabel()),
                TextColumn::make('students')
                    ->label('Skala')
                    ->sortable()
                    ->state(fn (ExamSimulation $record): string => $record->scaleLabel()),
                TextColumn::make('starts_at')
                    ->label('Mulai')
                    ->dateTime('d M Y · H:i')
                    ->sortable()
                    ->description(fn (ExamSimulation $record): string => $record->hasStarted()
                        ? 'QR sudah boleh dibuka'
                        : 'Menunggu jam server'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (ExamSimulation $record): string => $record->hasStarted() ? 'Berjalan' : 'Terjadwal')
                    ->color(fn (ExamSimulation $record): string => $record->hasStarted() ? 'success' : 'warning'),
                TextColumn::make('mix')
                    ->label('Porsi jenjang')
                    ->state(fn (ExamSimulation $record): string => $record->mixLabel())
                    ->description(fn (ExamSimulation $record): string => $record->pool_size.' butir')
                    ->toggleable(),
            ])
            ->recordUrl(fn (ExamSimulation $record): string => ExamSimulationResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label('Buka')->icon(Heroicon::OutlinedEye),
                DeleteAction::make()
                    ->label('Hapus')
                    ->icon(Heroicon::OutlinedTrash)
                    ->requiresConfirmation()
                    ->modalHeading('Hapus simulasi ini?')
                    ->modalDescription('Akun operator/pengawas gelombang ini, jadwal, kursi, dan paketnya dihapus. Bank soal dan tes asli tidak berubah.'),
            ])
            ->toolbarActions([])
            ->striped()
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25])
            ->emptyStateIcon(Heroicon::OutlinedBeaker)
            ->emptyStateHeading('Belum ada gelombang')
            ->emptyStateDescription('Susun siswa, kelas, akun, dan porsi jenjang. Satu tombol hapus menghilangkan seluruh gelombang.')
            ->emptyStateActions([
                CreateAction::make()->label('Simulasi baru'),
            ]);
    }
}
