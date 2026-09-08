<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Pages;

use App\Filament\Resources\ExamGroups\ExamGroupResource;
use App\Models\ExamGroup;
use App\Services\ExamGroupSeater;
use Filament\Resources\Pages\EditRecord;

class EditExamGroup extends EditRecord
{
    protected static string $resource = ExamGroupResource::class;

    protected function afterSave(): void
    {
        $record = $this->record;

        if ($record instanceof ExamGroup) {
            app(ExamGroupSeater::class)->fill($record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
