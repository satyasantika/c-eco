<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\TestSession;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class LiveSessions extends TableWidget
{
    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sesi')
            ->query(fn (): Builder => TestSession::query()->with('participant.school'))
            ->columns([
                TextColumn::make('participant.display_name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('participant.class_name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('access_token')->label('Token')->searchable()->copyable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'info',
                        'abandoned' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('items_administered')->label('Butir')->sortable(),
                TextColumn::make('se')
                    ->label('SE')
                    ->numeric(3)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('last_seen_at')
                    ->label('Kabar terakhir')
                    ->since()
                    ->placeholder('belum pernah')
                    // Sesi tersendat diwarnai merah supaya terlihat tanpa
                    // membaca kolom waktu satu per satu.
                    ->color(fn (TestSession $record): string => $this->isStuck($record) ? 'danger' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    'pending' => 'Belum mulai',
                    'in_progress' => 'Mengerjakan',
                    'completed' => 'Selesai',
                    'abandoned' => 'Ditinggalkan',
                ]),
                Filter::make('stuck')
                    ->label('Tersendat > '.SessionOverview::STUCK_AFTER_MINUTES.' menit')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', 'in_progress')
                        ->where(fn (Builder $q): Builder => $q
                            ->where('last_seen_at', '<', Carbon::now()->subMinutes(SessionOverview::STUCK_AFTER_MINUTES))
                            ->orWhereNull('last_seen_at'))),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    private function isStuck(TestSession $session): bool
    {
        return $session->status === 'in_progress'
            && ($session->last_seen_at === null
                || $session->last_seen_at->lt(Carbon::now()->subMinutes(SessionOverview::STUCK_AFTER_MINUTES)));
    }
}
