<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Actions;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\ExamSimulationBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

final class RescheduleExamSimulationAction
{
    public static function make(?ExamSimulation $wave = null): Action
    {
        return Action::make('reschedule')
            ->label('Ubah waktu')
            ->modalHeading('Ubah tanggal dan jam')
            ->modalDescription('Kartu QR dan halaman siswa mengikuti jam server. Tes asli tidak berubah.')
            ->modalSubmitActionLabel('Simpan jam')
            ->color('gray')
            ->schema([
                DateTimePicker::make('starts_at')
                    ->label('Tanggal dan jam mulai')
                    ->required()
                    ->seconds(false)
                    ->native(false)
                    ->timezone((string) config('app.timezone'))
                    ->helperText('Jam HP siswa tidak dipakai. Untuk uji hari ini, geser ke sekarang — bukan 21 September.'),
            ])
            ->fillForm(function (array $arguments) use ($wave): array {
                return ['starts_at' => self::resolve($wave, $arguments)->starts_at];
            })
            ->action(function (array $data, array $arguments) use ($wave): void {
                abort_unless(ExamSimulationResource::canViewAny(), 403);

                $record = self::resolve($wave, $arguments);
                $when = $data['starts_at'] instanceof Carbon
                    ? $data['starts_at']
                    : Carbon::parse((string) $data['starts_at']);

                app(ExamSimulationBuilder::class)->reschedule($record, $when);

                Notification::make()
                    ->title('Jam gelombang disimpan.')
                    ->body($record->fresh()?->whenLabel() ?? '')
                    ->success()
                    ->send();
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private static function resolve(?ExamSimulation $wave, array $arguments): ExamSimulation
    {
        if ($wave instanceof ExamSimulation) {
            return $wave;
        }

        return ExamSimulation::query()->findOrFail((int) ($arguments['waveId'] ?? 0));
    }
}
