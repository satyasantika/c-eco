<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\ExamSimulationBuilder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreateExamSimulation extends CreateRecord
{
    protected static string $resource = ExamSimulationResource::class;

    public function getTitle(): string
    {
        return 'Simulasi baru';
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['preset']);

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        unset($data['preset']);

        try {
            return app(ExamSimulationBuilder::class)->create($data);
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('Simulasi gagal disusun')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw $e;
        }
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->record;

        if ($record instanceof ExamSimulation) {
            return ExamSimulationResource::getUrl('view', ['record' => $record]);
        }

        return ExamSimulationResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Gelombang simulasi siap. Bagikan akun di halaman rincian.';
    }
}
