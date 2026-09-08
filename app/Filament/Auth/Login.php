<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class Login extends FilamentLogin
{
    public function getTitle(): string|Htmlable
    {
        return 'Masuk panel';
    }

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return 'Masuk panel';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getSubheading();
        }

        return 'Pengawas, operator, dan peneliti. Siswa memakai token di slip, bukan halaman ini.';
    }

    /**
     * Revealable Filament mengosongkan atribut type. Kita isi type=password di
     * HTML supaya sandi tetap tersembunyi sebelum Alpine hidup, sambil
     * mempertahankan ikon mata.
     */
    protected function getPasswordFormComponent(): Component
    {
        $field = parent::getPasswordFormComponent();

        if (! $field instanceof TextInput) {
            return $field;
        }

        return $field->extraInputAttributes(['type' => 'password']);
    }
}
