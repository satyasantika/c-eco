<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        if ($record->isAdmin()) {
            $data['role'] = UserRole::Admin->value;
            $data['is_active'] = true;
        }

        $role = UserRole::tryFrom((string) ($data['role'] ?? $record->role->value));

        if ($role === UserRole::Admin && ! $record->isAdmin()) {
            throw ValidationException::withMessages([
                'data.role' => 'Tidak bisa menaikkan akun menjadi admin dari panel.',
            ]);
        }

        if ($record->is(auth()->user())) {
            $data['is_active'] = true;
        }

        return $data;
    }
}
