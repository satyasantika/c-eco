<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\Actions\RescheduleExamSimulationAction;
use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use App\Models\ExamSimulation;
use App\Services\DemoSimulation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use RuntimeException;

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
            Action::make('generateDemo')
                ->label('Buat Simulasi')
                ->icon('heroicon-o-sparkles')
                ->authorize('manage-simulation')
                ->visible(fn (): bool => $this->demo() === null)
                ->requiresConfirmation()
                ->modalHeading('Buat simulasi demo?')
                ->modalDescription('Satu gelombang demo: '.DemoSimulation::STUDENTS.' kursi di '.DemoSimulation::ROOMS.' ruang, akun admin, operator, pengawas, dan peneliti, serta sesi tes yang sebagian sudah dijawab. Semua ditandai simulasi dan bisa dihapus utuh. Bank soal dan tes asli tidak berubah.')
                ->modalSubmitActionLabel('Buat')
                ->action(function (): void {
                    $this->runDemo(fn (DemoSimulation $demo): string => $demo->generate()['created']
                        ? 'Simulasi demo dibuat. Sandi akun ada di lembar gelombangnya.'
                        : 'Simulasi demo sudah aktif; tidak dibuat ulang.');
                }),
            Action::make('resetDemo')
                ->label('Hapus Simulasi')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->authorize('manage-simulation')
                ->visible(fn (): bool => $this->demo() !== null)
                ->requiresConfirmation()
                ->modalHeading('Hapus simulasi demo?')
                ->modalDescription('Akun demo (termasuk admin demo), kursi, jawaban, dan paket gelombang demo dihapus. Gelombang yang Anda tulis sendiri, bank soal, dan tes asli tidak tersentuh.')
                ->modalSubmitActionLabel('Hapus')
                ->action(function (): void {
                    $this->runDemo(fn (DemoSimulation $demo): string => $demo->reset() > 0
                        ? 'Simulasi demo dihapus. Tes asli tidak berubah.'
                        : 'Tidak ada simulasi demo aktif.');
                }),
            CreateAction::make()
                ->label('Tulis gelombang baru')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function demo(): ?ExamSimulation
    {
        return app(DemoSimulation::class)->active();
    }

    /** @param  callable(DemoSimulation): string  $run */
    private function runDemo(callable $run): void
    {
        try {
            $message = $run(app(DemoSimulation::class));
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title($message)->success()->send();
    }

    public function rescheduleAction(): Action
    {
        return RescheduleExamSimulationAction::make();
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
