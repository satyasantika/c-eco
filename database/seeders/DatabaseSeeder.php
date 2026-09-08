<?php

declare(strict_types=1);

namespace Database\Seeders;

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
            ['name' => 'Admin C-ECO', 'password' => Hash::make('password')],
        );

        $this->call([
            ItemBankSeeder::class,
            TestConfigSeeder::class,
        ]);
    }
}
