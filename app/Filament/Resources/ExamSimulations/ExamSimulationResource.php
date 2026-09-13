<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamSimulations;

use App\Filament\Resources\ExamSimulations\Pages\CreateExamSimulation;
use App\Filament\Resources\ExamSimulations\Pages\ListExamSimulations;
use App\Filament\Resources\ExamSimulations\Pages\ViewExamSimulation;
use App\Filament\Resources\ExamSimulations\Schemas\ExamSimulationForm;
use App\Filament\Resources\ExamSimulations\Tables\ExamSimulationsTable;
use App\Models\ExamSimulation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ExamSimulationResource extends Resource
{
    protected static ?string $model = ExamSimulation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Simulasi';

    protected static ?string $modelLabel = 'simulasi';

    protected static ?string $pluralModelLabel = 'Simulasi';

    protected static string|\UnitEnum|null $navigationGroup = 'Simulasi';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return ExamSimulationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamSimulationsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageExamSimulations();
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
        return false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExamSimulations::route('/'),
            'create' => CreateExamSimulation::route('/create'),
            'view' => ViewExamSimulation::route('/{record}'),
        ];
    }
}
