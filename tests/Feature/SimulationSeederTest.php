<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TestSession;
use App\Models\User;
use Database\Seeders\SimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class SimulationSeederTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
        $this->seed(SimulationSeeder::class);
    }

    public function test_every_panel_role_can_sign_in(): void
    {
        foreach (SimulationSeeder::PANEL_USERS as $user) {
            $this->assertTrue(
                Auth::attempt([
                    'email' => $user['email'],
                    'password' => SimulationSeeder::PASSWORD,
                ]),
                "Gagal masuk sebagai {$user['email']}",
            );
            Auth::logout();
        }

        $this->assertSame(count(SimulationSeeder::PANEL_USERS), User::query()->count());
    }

    public function test_twenty_students_have_unique_tokens(): void
    {
        $sessions = TestSession::query()
            ->whereHas('participant', fn ($q) => $q->where('class_name', 'XI-SIM'))
            ->get();

        $this->assertCount(20, $sessions);
        $this->assertSame(20, $sessions->pluck('access_token')->unique()->count());

        $this->get('/t/'.$sessions->first()->access_token)
            ->assertOk()
            ->assertSee('Siswa Simulasi 01');
    }

    public function test_a_pengawas_can_open_the_monitor(): void
    {
        $this->actingAs(User::query()->where('email', 'pengawas@c-eco.test')->firstOrFail())
            ->get('/admin/monitor')
            ->assertOk();
    }
}
