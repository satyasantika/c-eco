<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Pages;

use App\Filament\Resources\TestConfigs\TestConfigResource;
use App\Models\TestConfig;
use App\Services\MixedPackageComposer;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditTestConfig extends EditRecord
{
    protected static string $resource = TestConfigResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CreateTestConfig::normalizeShares($data);
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if (! $record instanceof TestConfig) {
            return;
        }

        try {
            app(MixedPackageComposer::class)->apply($record->fresh() ?? $record);
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('Paket tidak diubah')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
