<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Actions\ImportItemPackageAction;
use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    /** Butir tidak diketik di panel: masuk lewat seeder atau impor JSON. */
    protected function getHeaderActions(): array
    {
        return [
            ImportItemPackageAction::make(),
        ];
    }
}
