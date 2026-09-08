<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ItemBank;
use App\Services\TestConfigProvisioner;
use Illuminate\Database\Seeder;

/**
 * Satu konfigurasi adaptif per paket soal, memakai angka awal SPEC §4.
 *
 * Angka ini dikunci ulang dari hasil simulasi langkah 09 — di sini ia hanya
 * titik berangkat, dan hidup di basis data supaya bisa diubah tanpa deploy.
 */
class TestConfigSeeder extends Seeder
{
    public function run(): void
    {
        $provisioner = app(TestConfigProvisioner::class);

        foreach (ItemBank::query()->get() as $bank) {
            $provisioner->ensureAdaptive($bank);
        }
    }
}
