<?php

declare(strict_types=1);

namespace App\Filament\Resources\Items;

use App\Filament\Resources\Items\Pages\EditItem;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Filament\Resources\Items\Schemas\ItemForm;
use App\Filament\Resources\Items\Tables\ItemsTable;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Bank soal: hanya baca dan sunting.
 *
 * Butir dibuat oleh seeder dari data/items-all.json, bukan diketik di sini,
 * dan tidak pernah dihapus — session_items menunjuk kepadanya. Butir yang
 * tidak layak pakai dipensiunkan lewat kolom status.
 */
class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Bank Soal';

    protected static ?string $modelLabel = 'Butir';

    protected static ?string $pluralModelLabel = 'Butir';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return ItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
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
            'index' => ListItems::route('/'),
            'edit' => EditItem::route('/{record}/edit'),
        ];
    }
}
