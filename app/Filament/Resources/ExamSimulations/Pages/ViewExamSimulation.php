<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\MixedPackageComposer;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewExamSimulation extends ViewRecord
{
    protected static string $resource = ExamSimulationResource::class;

    protected string $view = 'filament.resources.exam-simulations.view';

    public function getTitle(): string
    {
        $record = $this->getRecord();

        return $record instanceof ExamSimulation ? $record->label() : 'Simulasi';
    }

    public function getSubheading(): ?string
    {
        return 'Akun dan jadwal ini hanya untuk gelombang uji. Hapus di sini jika tes asli sudah tidak membutuhkannya.';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus simulasi')
                ->requiresConfirmation()
                ->modalHeading('Hapus simulasi ini?')
                ->modalDescription('Akun operator/pengawas gelombang ini, jadwal, kursi, dan paketnya dihapus. Bank soal dan tes asli tidak berubah.')
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
