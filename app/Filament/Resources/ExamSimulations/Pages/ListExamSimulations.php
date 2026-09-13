<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;

class ListExamSimulations extends ListRecords
{
    protected static string $resource = ExamSimulationResource::class;

    protected string $view = 'filament.resources.exam-simulations.index';

    /** @var array<string, string> */
    protected array $extraBodyAttributes = [
        'class' => 'ceco-roll-page',
    ];

    public function getTitle(): string
    {
        return 'Denah simulasi';
    }

    public function getSubheading(): ?string
    {
        return 'Gelombang uji terpisah dari tes asli. Setiap lembar adalah satu denah ruang: jam, kursi, pengawas. Hapus lembarnya jika hari-H sudah tidak memakainya.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tulis gelombang baru')
                ->icon('heroicon-o-plus'),
        ];
    }

    /** @return Collection<int, ExamSimulation> */
    public function waves(): Collection
    {
        return ExamSimulation::query()
            ->with('examGroups')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get();
    }

    public function removeWave(int $id): void
    {
        $wave = ExamSimulation::query()->findOrFail($id);
        abort_unless(ExamSimulationResource::canDelete($wave), 403);
        $wave->delete();

        Notification::make()->title('Gelombang dihapus. Tes asli tidak berubah.')->success()->send();
    }
}
