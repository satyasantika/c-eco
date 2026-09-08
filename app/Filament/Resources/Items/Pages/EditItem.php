<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\EditRecord;

class EditItem extends EditRecord
{
    protected static string $resource = ItemResource::class;

    /** Tanpa tombol hapus: session_items menunjuk ke butir ini. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
