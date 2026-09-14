<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\Actions\RescheduleExamSimulationAction;
use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\MixedPackageComposer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExamSimulation extends ViewRecord
{
    protected static string $resource = ExamSimulationResource::class;

    protected string $view = 'filament.resources.exam-simulations.view';

    /** @var array<string, string> */
    protected array $extraBodyAttributes = [
        'class' => 'ceco-roll-page',
    ];

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof ExamSimulation ? $record->name : 'Simulasi';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return $record instanceof ExamSimulation ? $record->whenLabel() : null;
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $reschedule = $record instanceof ExamSimulation
            ? RescheduleExamSimulationAction::make($record)
            : RescheduleExamSimulationAction::make();

        return [
            $reschedule,
            Action::make('back')
                ->label('Kembali ke denah')
                ->url(ExamSimulationResource::getUrl('index'))
                ->color('gray'),
            DeleteAction::make()
                ->label('Hapus gelombang')
                ->requiresConfirmation()
                ->modalHeading('Hapus gelombang ini?')
                ->modalDescription('Akun, jadwal, kursi, dan paket gelombang ini hilang. Bank soal dan tes asli tetap.')
                ->successRedirectUrl(ExamSimulationResource::getUrl('index')),
        ];
    }

    /** @return array{X: int, XI: int, XII: int} */
    public function allocation(): array
    {
        $record = $this->getRecord();

        if (! $record instanceof ExamSimulation) {
            return ['X' => 0, 'XI' => 0, 'XII' => 0];
        }

        return MixedPackageComposer::allocate(
            $record->pool_size,
            $record->grade_share_x,
            $record->grade_share_xi,
            $record->grade_share_xii,
        );
    }
}
