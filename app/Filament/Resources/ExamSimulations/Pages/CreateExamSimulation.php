<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\ExamSimulationBuilder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreateExamSimulation extends CreateRecord
{
    protected static string $resource = ExamSimulationResource::class;

    protected static bool $canCreateAnother = false;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected string $view = 'filament.resources.exam-simulations.create';

    /** @var array<string, string> */
    protected array $extraBodyAttributes = [
        'class' => 'ceco-roll-page',
    ];

    public function getTitle(): string
    {
        return 'Tulis gelombang';
    }

    public function getSubheading(): ?string
    {
        return 'Berapa siswa, berapa ruang, jam berapa. Akun pengawas dan operator dibuat otomatis, terpisah dari tes asli.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Susun denah');
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
