<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\ItemBank;
use App\Models\Participant;
use App\Models\School;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Services\ItemBankImporter;
use Database\Seeders\TestConfigSeeder;

trait BuildsTestSessions
{
    protected function seedBankWithParameters(): void
    {
        ItemBankImporter::fromDataDirectory()->import();
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();
        (new TestConfigSeeder)->run();
    }

    protected function makeSession(string $grade = 'XI', int $rngSeed = 20260921, string $token = 'ABCD2345'): TestSession
    {
        $bank = ItemBank::query()->where('grade', $grade)->firstOrFail();
        $config = TestConfig::query()->where('item_bank_id', $bank->id)->firstOrFail();

        $school = School::query()->firstOrCreate(['name' => 'SMA Uji'], ['city' => 'Tasikmalaya']);

        $participant = Participant::query()->create([
            'school_id' => $school->id,
            'class_name' => $grade.' IPS 1',
            'student_code' => 'S'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'display_name' => 'Peserta Uji',
        ]);

        return TestSession::query()->create([
            'participant_id' => $participant->id,
            'test_config_id' => $config->id,
            'item_bank_id' => $bank->id,
            'access_token' => $token,
            'rng_seed' => $rngSeed,
            'status' => 'pending',
        ]);
    }
}
