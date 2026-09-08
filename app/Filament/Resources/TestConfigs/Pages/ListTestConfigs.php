<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Pages;

use App\Filament\Resources\TestConfigs\TestConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTestConfigs extends ListRecords
{
    protected static string $resource = TestConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Paket ujian baru'),
        ];
    }
}
