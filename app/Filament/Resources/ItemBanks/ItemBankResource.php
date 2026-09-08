<?php

declare(strict_types=1);

namespace App\Filament\Resources\ItemBanks;

use App\Filament\Resources\ItemBanks\Pages\EditItemBank;
use App\Filament\Resources\ItemBanks\Pages\ListItemBanks;
use App\Filament\Resources\ItemBanks\Schemas\ItemBankForm;
use App\Filament\Resources\ItemBanks\Tables\ItemBanksTable;
use App\Models\ItemBank;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Paket soal = satu baris item_banks (jenjang + versi).
 *
 * Butir tidak diketik di sini. Paket baru masuk lewat impor JSON.
 * Paket tidak dihapus: sesi dan parameter menunjuk kepadanya.
 */
class ItemBankResource extends Resource
{
    protected static ?string $model = ItemBank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Paket Soal';

    protected static ?string $modelLabel = 'Paket soal';

    protected static ?string $pluralModelLabel = 'Paket soal';

    protected static string|\UnitEnum|null $navigationGroup = 'Bank soal';

    protected static ?int $navigationSort = 29;

    public static function form(Schema $schema): Schema
    {
        return ItemBankForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemBanksTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canViewItems();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canEditItems();
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItemBanks::route('/'),
            'edit' => EditItemBank::route('/{record}/edit'),
        ];
    }
}
