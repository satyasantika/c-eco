<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemBank;
use App\Models\TestConfig;
use Illuminate\Database\Seeder;

/**
 * Satu konfigurasi adaptif per jenjang, memakai angka awal SPEC §4.
 *
 * Angka ini dikunci ulang dari hasil simulasi langkah 09 — di sini ia hanya
 * titik berangkat, dan hidup di basis data supaya bisa diubah tanpa deploy.
 */
class TestConfigSeeder extends Seeder
{
    public function run(): void
    {
        foreach (ItemBank::query()->get() as $bank) {
            TestConfig::query()->firstOrCreate(
                ['item_bank_id' => $bank->id, 'name' => "Adaptif {$bank->grade}"],
                [
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
                ],
            );
        }
    }
}
