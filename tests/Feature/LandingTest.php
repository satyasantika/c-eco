<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    public function test_the_front_door_is_c_eco_not_the_laravel_welcome(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->assertSee('C-ECO', false)
            ->assertSee('Tes berpikir kreatif ekonomi', false)
            ->assertSee('Siswa', false)
            ->assertSee('Pengawas dan staf', false)
            ->assertSee('Buka tes', false)
            ->assertSee('Masuk panel', false)
            ->assertDontSee('Let\'s get started', false)
            ->assertDontSee('Laracasts', false)
            ->getContent();

        $this->assertStringContainsString('/build/assets/site-', $html);
        $this->assertStringNotContainsStringIgnoringCase('filament', $html);
        $this->assertStringNotContainsStringIgnoringCase('livewire', $html);
    }

    public function test_a_token_from_the_slip_opens_the_student_session(): void
    {
        $this->seedBankWithParameters();
        $session = $this->makeSession();

        $this->from('/')
            ->post('/masuk', ['token' => ' abcd-2345 '])
            ->assertRedirect(route('student.show', $session->access_token));
    }

    public function test_a_token_that_is_the_wrong_length_stays_on_the_landing(): void
    {
        $this->from('/')
            ->post('/masuk', ['token' => 'ABC'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('token');
    }

    public function test_an_unknown_token_stays_on_the_landing(): void
    {
        $this->seedBankWithParameters();

        $this->from('/')
            ->post('/masuk', ['token' => 'NOTOKENX'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('token');
    }

    public function test_the_staff_login_is_light_and_named_for_this_system(): void
    {
        $html = $this->get('/admin/login')
            ->assertOk()
            ->assertSee('C-ECO', false)
            ->assertSee('Masuk panel', false)
            ->assertSee('Siswa memakai token', false)
            ->assertSee('Kembali ke halaman depan', false)
            ->getContent();

        $this->assertStringContainsString("localStorage.setItem('theme', 'light')", $html);
        $this->assertStringContainsString('/build/assets/admin-brand-', $html);
        $this->assertStringContainsString('id="form.password"', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('isPasswordRevealed', $html);
    }

    public function test_a_panel_user_can_sign_in(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@c-eco.test',
            'password' => 'ekonomi1234',
        ]);

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'ekonomi1234')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
