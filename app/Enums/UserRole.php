<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Pengawas = 'pengawas';
    case Peneliti = 'peneliti';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Operator => 'Operator',
            self::Pengawas => 'Pengawas',
            self::Peneliti => 'Peneliti',
        };
    }

    /** Peran yang boleh dibuat dan dinonaktifkan admin dari panel. */
    public static function staff(): array
    {
        return [self::Operator, self::Pengawas, self::Peneliti];
    }

    /** @return array<string, string> */
    public static function staffOptions(): array
    {
        return collect(self::staff())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
