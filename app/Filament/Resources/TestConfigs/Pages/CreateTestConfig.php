<?php

declare(strict_types=1);

namespace App\Filament\Resources\TestConfigs\Pages;

use App\Filament\Resources\TestConfigs\TestConfigResource;
use App\Models\TestConfig;
use App\Services\MixedPackageComposer;
use App\Services\TestConfigProvisioner;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;

class CreateTestConfig extends CreateRecord
{
    protected static string $resource = TestConfigResource::class;

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return self::normalizeShares($data) + TestConfigProvisioner::adaptiveAttributes();
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (! $record instanceof TestConfig) {
            return;
        }

        try {
            app(MixedPackageComposer::class)->apply($record);
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('Paket belum tersusun')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeShares(array $data): array
    {
        $sum = (int) ($data['grade_share_x'] ?? 0)
            + (int) ($data['grade_share_xi'] ?? 0)
            + (int) ($data['grade_share_xii'] ?? 0);

        if ($sum === 0) {
            $data['grade_share_x'] = null;
            $data['grade_share_xi'] = null;
            $data['grade_share_xii'] = null;
            $data['pool_size'] = null;
        }

        return $data;
    }
}
