<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups;

use App\Filament\Resources\ExamGroups\Pages\CreateExamGroup;
use App\Filament\Resources\ExamGroups\Pages\EditExamGroup;
use App\Filament\Resources\ExamGroups\Pages\ListExamGroups;
use App\Filament\Resources\ExamGroups\Schemas\ExamGroupForm;
use App\Filament\Resources\ExamGroups\Tables\ExamGroupsTable;
use App\Models\ExamGroup;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ExamGroupResource extends Resource
{
    protected static ?string $model = ExamGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Jadwal';

    protected static ?string $modelLabel = 'jadwal ujian';

    protected static ?string $pluralModelLabel = 'Jadwal ujian';

    protected static string|\UnitEnum|null $navigationGroup = 'Jadwal';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return ExamGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamGroupsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['school', 'supervisor', 'testConfig']);
        $user = auth()->user();

        if ($user instanceof User && $user->isPengawas()) {
            return $query->where('supervisor_id', $user->id);
        }

        return $query->whereNull('exam_simulation_id');
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canProctorExamGroups();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageExamGroups();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->canManageExamGroups()
            && $record instanceof ExamGroup
            && ! $record->isExamSimulation();
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
            'index' => ListExamGroups::route('/'),
            'create' => CreateExamGroup::route('/create'),
            'edit' => EditExamGroup::route('/{record}/edit'),
        ];
    }
}
