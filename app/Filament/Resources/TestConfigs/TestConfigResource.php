<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs;

use App\Filament\Resources\TestConfigs\Pages\CreateTestConfig;
use App\Filament\Resources\TestConfigs\Pages\EditTestConfig;
use App\Filament\Resources\TestConfigs\Pages\ListTestConfigs;
use App\Filament\Resources\TestConfigs\Schemas\TestConfigForm;
use App\Filament\Resources\TestConfigs\Tables\TestConfigsTable;
use App\Models\TestConfig;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Paket ujian: konfigurasi CAT plus kolam butir.
 *
 * Kosongkan persen jenjang untuk memakai seluruh bank acuan. Isi persen
 * X/XI/XII (jumlah 100) untuk merakit paket campuran otomatis.
 */
class TestConfigResource extends Resource
{
    protected static ?string $model = TestConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Paket Ujian';

    protected static ?string $modelLabel = 'Paket ujian';

    protected static ?string $pluralModelLabel = 'Paket ujian';

    protected static string|\UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return TestConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TestConfigsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManagePackages();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
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
            'index' => ListTestConfigs::route('/'),
            'create' => CreateTestConfig::route('/create'),
            'edit' => EditTestConfig::route('/{record}/edit'),
        ];
    }
}
