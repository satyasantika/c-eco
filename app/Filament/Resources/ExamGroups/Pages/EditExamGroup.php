<?php

declare(strict_types=1);

namespace App\Filament\Resources\ExamGroups\Pages;

use App\Filament\Resources\ExamGroups\ExamGroupResource;
use App\Models\ExamGroup;
use App\Services\ExamGroupPlanner;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditExamGroup extends EditRecord
{
    protected static string $resource = ExamGroupResource::class;

    private ?int $previousConfigId = null;

    protected function beforeSave(): void
    {
        $record = $this->record;

        if (! $record instanceof ExamGroup) {
            return;
        }

        $this->previousConfigId = (int) $record->getOriginal('test_config_id');
        $newConfig = (int) ($this->data['test_config_id'] ?? $this->previousConfigId);
        $reason = app(ExamGroupPlanner::class)->lockReason($record);

        if ($reason !== null && $newConfig !== $this->previousConfigId) {
            Notification::make()->danger()->title('Paket tidak bisa diganti')->body($reason)->send();
            $this->halt();
        }
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (! $record instanceof ExamGroup) {
            return;
        }

        try {
            app(ExamGroupPlanner::class)->sync($record, $this->previousConfigId);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('Kursi tidak diubah')->body($e->getMessage())->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ExamGroupResource::deleteAction(DeleteAction::make())
                ->successRedirectUrl(ExamGroupResource::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
