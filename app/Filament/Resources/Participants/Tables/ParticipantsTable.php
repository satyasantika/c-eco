<?php

declare(strict_types=1);

namespace App\Filament\Resources\Participants\Tables;

use App\Models\Participant;
use App\Models\TestConfig;
use App\Services\TokenIssuer;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ParticipantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('display_name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('class_name')->label('Kelas')->searchable()->sortable(),
                TextColumn::make('school.name')->label('Sekolah')->searchable()->sortable()->toggleable(),
                TextColumn::make('student_code')->label('Kode')->searchable()->toggleable(),
                TextColumn::make('testSessions.access_token')
                    ->label('Token')
                    ->badge()
                    ->copyable()
                    ->placeholder('belum terbit'),
                TextColumn::make('consent_at')->label('Setuju')->dateTime('d M H:i')->placeholder('belum')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('school_id')->label('Sekolah')->relationship('school', 'name'),
                Filter::make('no_token')
                    ->label('Belum punya token')
                    ->query(fn ($query) => $query->whereDoesntHave('testSessions')),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('issue_tokens')
                        ->label('Terbitkan token')
                        ->icon('heroicon-o-ticket')
                        ->schema([
                            Select::make('test_config_id')
                                ->label('Konfigurasi tes')
                                ->options(fn (): array => TestConfig::query()
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->all())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $config = TestConfig::query()->findOrFail($data['test_config_id']);
                            $sessions = app(TokenIssuer::class)->issue($records, $config);
                            $fresh = $sessions->filter(fn ($s): bool => $s->wasRecentlyCreated)->count();

                            Notification::make()
                                ->title("{$fresh} token baru diterbitkan")
                                ->body(($sessions->count() - $fresh).' peserta sudah punya sesi dan tidak diubah.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('display_name');
    }

    /** @return list<string> */
    public static function searchable(): array
    {
        return [Participant::class];
    }
}
