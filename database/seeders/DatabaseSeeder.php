<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@c-eco.test'],
            [
                'name' => 'Admin C-ECO',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        // Bank soal tidak di-seed: data/ tidak ada di image produksi.
        // Unggah lewat panel (/admin/unggah-soal) atau `php artisan cat:seed-items`.
        $this->call([
            TestConfigSeeder::class,
        ]);
    }
}
