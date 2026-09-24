<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\ExamGroup;
use App\Models\TestSession;
use App\Models\User;
use App\Services\SeatReleaser;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;

class LiveSessions extends TableWidget
{
    protected ?string $pollingInterval = '15s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sesi')
            ->description('Cari nama siswa, NIS, kelas, atau token di slip/QR (spasi boleh diketik).')
            ->query(fn (): Builder => $this->baseQuery())
            ->searchPlaceholder('Nama, NIS, kelas, atau token')
            ->persistSearchInSession()
            ->columns([
                TextColumn::make('participant.display_name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TestSession $record): ?string => $this->studentCode($record)),
                TextColumn::make('participant.class_name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('examGroup.room')->label('Ruang')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('access_token')
                    ->label('Token')
                    ->formatStateUsing(fn (string $state): string => implode(' ', str_split($state, 4)))
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyableState(fn (TestSession $record): string => $record->access_token)
                    // Token di slip dicetak berspasi: "ABCD EFGH". Cari tanpa peduli spasi dan huruf besar.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(
                        'access_token',
                        'like',
                        '%'.strtoupper(preg_replace('/\s+/', '', $search) ?? '').'%',
                    )),
                TextColumn::make('participant.student_code')
                    ->label('NIS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Belum mulai',
                        'in_progress' => 'Mengerjakan',
                        'completed' => 'Selesai',
                        'abandoned' => 'Ditinggalkan',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'info',
                        'abandoned' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('device')
                    ->label('HP')
                    ->badge()
                    ->state(fn (TestSession $record): string => $this->deviceState($record))
                    ->color(fn (string $state): string => match (true) {
                        str_starts_with($state, 'Boleh pindah') => 'warning',
                        $state === 'Terkunci' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('items_administered')->label('Butir')->sortable(),
                TextColumn::make('se')
                    ->label('SE')
                    ->numeric(3)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('exam_group_id')
                    ->label('Ruang')
                    ->options(fn (): array => $this->groupOptions())
                    ->searchable(),
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
                Filter::make('moving')
                    ->label('Izin pindah HP berlaku')
                    ->query(fn (Builder $query): Builder => $query->whereHas('seatReleases', fn (Builder $q): Builder => $q
                        ->whereNull('used_at')
                        ->whereNull('restored_at')
                        ->where('expires_at', '>', Carbon::now()))),
            ])
            ->recordActions([
                Action::make('releaseSeat')
                    ->label('Pindah HP')
                    ->tooltip('Izinkan siswa melanjutkan di HP lain')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->color('warning')
                    ->visible(fn (TestSession $record): bool => $this->mayRelease($record)
                        && app(SeatReleaser::class)->blockReason($record) === null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (TestSession $record): string => 'Izinkan '.$record->participant?->display_name.' pindah HP?')
                    ->modalDescription(fn (TestSession $record): string => 'Pastikan siswanya benar ada di depan Anda. HP lama ditolak, lalu siswa membuka token '
                        .implode(' ', str_split($record->access_token, 4))
                        .' di HP baru dalam '.SeatReleaser::RELEASE_MINUTES.' menit dan melanjutkan dari soal terakhir. '
                        .'Kalau tidak dipakai, HP lama kembali menjadi pemilik token.')
                    ->schema([
                        TextInput::make('reason')
                            ->label('Alasan (opsional)')
                            ->placeholder('Contoh: baterai habis, layar pecah')
                            ->maxLength(255),
                    ])
                    ->modalSubmitActionLabel('Izinkan pindah')
                    ->action(function (TestSession $record, array $data): void {
                        try {
                            app(SeatReleaser::class)->release($this->user(), $record, $data['reason'] ?? null);
                        } catch (RuntimeException $e) {
                            Notification::make()->danger()->title('Izin tidak dibuat')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Boleh pindah HP')
                            ->body('Minta siswa membuka token yang sama di HP baru dalam '.SeatReleaser::RELEASE_MINUTES.' menit.')
                            ->send();
                    }),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([])
            ->defaultSort('last_seen_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    /** Pengawas hanya melihat siswa di ruang yang ia jaga, termasuk ruang simulasi. */
    private function baseQuery(): Builder
    {
        $query = TestSession::query()->with(['participant.school', 'examGroup']);
        $user = auth()->user();

        if ($user instanceof User && $user->isPengawas()) {
            return $query->whereHas('examGroup', fn (Builder $q): Builder => $q->where('supervisor_id', $user->id));
        }

        if ($user instanceof User && $user->isExamSimulationAccount()) {
            return $query->whereHas('examGroup', fn (Builder $q): Builder => $q->where('exam_simulation_id', $user->exam_simulation_id));
        }

        return $query;
    }

    /** @return array<int, string> */
    private function groupOptions(): array
    {
        $groups = ExamGroup::query()->with('school')->orderBy('starts_at')->orderBy('room');
        $user = auth()->user();

        if ($user instanceof User && $user->isPengawas()) {
            $groups->where('supervisor_id', $user->id);
        } elseif ($user instanceof User && $user->isExamSimulationAccount()) {
            $groups->where('exam_simulation_id', $user->exam_simulation_id);
        }

        return $groups->get()->mapWithKeys(fn (ExamGroup $g): array => [$g->id => $g->label()])->all();
    }

    private function studentCode(TestSession $record): ?string
    {
        $code = $record->participant?->student_code;

        return $code === null || str_starts_with($code, 'KURSI-') ? null : 'NIS '.$code;
    }

    private function deviceState(TestSession $record): string
    {
        $release = $record->resume_token === null ? app(SeatReleaser::class)->activeRelease($record) : null;

        if ($release !== null) {
            return 'Boleh pindah s.d. '.$release->expires_at->timezone((string) config('app.timezone'))->format('H:i');
        }

        if ($record->status === 'completed') {
            return '—';
        }

        return $record->resume_token !== null ? 'Terkunci' : 'Belum terikat';
    }

    private function mayRelease(TestSession $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && app(SeatReleaser::class)->canRelease($user, $record);
    }

    private function user(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new RuntimeException('Sesi masuk berakhir. Masuk lagi.');
        }

        return $user;
    }

    private function isStuck(TestSession $session): bool
    {
        return $session->status === 'in_progress'
            && ($session->last_seen_at === null
                || $session->last_seen_at->lt(Carbon::now()->subMinutes(SessionOverview::STUCK_AFTER_MINUTES)));
    }
}
