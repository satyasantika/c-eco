<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations\Pages;

use App\Filament\Resources\ExamSimulations\ExamSimulationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExamSimulations extends ListRecords
{
    protected static string $resource = ExamSimulationResource::class;

    public function getTitle(): string
    {
        return 'Simulasi pelaksanaan';
    }

    public function getSubheading(): ?string
    {
        return 'Gelombang uji terpisah dari tes asli. Hapus satu baris untuk menghilangkan akun, jadwal, dan paketnya.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Simulasi baru'),
        ];
    }
}
