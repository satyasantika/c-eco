<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ItemBank;
use App\Models\TestConfig;

/**
 * Satu konfigurasi adaptif per paket soal, memakai angka awal SPEC §4.
 */
class TestConfigProvisioner
{
    public function ensureAdaptive(ItemBank $bank): TestConfig
    {
        return TestConfig::query()->firstOrCreate(
            ['item_bank_id' => $bank->id, 'name' => $this->name($bank)],
            self::adaptiveAttributes(),
        );
    }

    /** @return array<string, mixed> */
    public static function adaptiveAttributes(): array
    {
        return [
            'mode' => 'adaptive',
            'min_items' => 14,
            'max_items' => 20,
            'se_target' => 0.30,
            'theta_prior_mean' => 0.0,
            'theta_prior_sd' => 1.0,
            'selection_method' => 'MPWI(1-5)+MFI',
            'exposure_method' => 'randomesque',
            'exposure_k' => 3,
            'content_balancing_json' => ['method' => 'kingsbury-zara', 'target' => 'bank-proportions'],
            'shuffle_options' => true,
            'is_active' => true,
        ];
    }

    public function name(ItemBank $bank): string
    {
        $suffix = $bank->version === ItemBankImporter::BANK_VERSION
            ? ''
            : ' '.$bank->version;

        return "Adaptif {$bank->grade}{$suffix}";
    }
}
