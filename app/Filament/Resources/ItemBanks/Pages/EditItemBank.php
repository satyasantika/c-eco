<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemBanks\Pages;

use App\Filament\Resources\ItemBanks\ItemBankResource;
use Filament\Resources\Pages\EditRecord;

class EditItemBank extends EditRecord
{
    protected static string $resource = ItemBankResource::class;

    /** Tanpa tombol hapus: sesi dan parameter menunjuk ke paket ini. */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
