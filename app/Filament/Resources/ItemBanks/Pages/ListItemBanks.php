<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemBanks\Pages;

use App\Filament\Actions\ImportItemPackageAction;
use App\Filament\Resources\ItemBanks\ItemBankResource;
use Filament\Resources\Pages\ListRecords;

class ListItemBanks extends ListRecords
{
    protected static string $resource = ItemBankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportItemPackageAction::make(),
        ];
    }
}
