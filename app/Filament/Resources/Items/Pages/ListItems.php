<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    /** Butir masuk lewat seeder, tidak diketik di panel. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
