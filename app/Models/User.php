<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Akun nonaktif ditolak Filament di sini, bukan di Auth::attempt:
     * kata sandinya masih benar, yang ditutup adalah pintunya.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, UserRole::staff(), true);
    }

    public function canManageStaff(): bool
    {
        return $this->isAdmin();
    }

    public function canManageRoster(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Operator], true);
    }

    public function canViewItems(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Peneliti], true);
    }

    public function canEditItems(): bool
    {
        return $this->isAdmin();
    }

    public function canExport(): bool
    {
        return in_array($this->role, [UserRole::Admin, UserRole::Operator, UserRole::Peneliti], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }
}
