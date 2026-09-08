<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $role = UserRole::tryFrom((string) ($data['role'] ?? ''));

        if ($role === null || $role === UserRole::Admin) {
            throw ValidationException::withMessages([
                'data.role' => 'Admin hanya boleh menambah operator, pengawas, atau peneliti.',
            ]);
        }

        $data['is_active'] = true;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
