<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Pages;

use App\Filament\Resources\ExamGroups\ExamGroupResource;
use App\Models\ExamGroup;
use App\Services\ExamGroupSeater;
use Filament\Resources\Pages\CreateRecord;

class CreateExamGroup extends CreateRecord
{
    protected static string $resource = ExamGroupResource::class;

    public function getTitle(): string
    {
        return 'Jadwal baru';
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if ($record instanceof ExamGroup) {
            app(ExamGroupSeater::class)->fill($record);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
