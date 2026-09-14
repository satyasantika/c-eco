<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class ApplicationPrefixTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    public function test_the_landing_under_c_eco_uses_prefixed_assets(): void
    {
        $html = $this->get('/c-eco/')
            ->assertOk()
            ->assertSee('C-ECO', false)
            ->assertSee('Buka tes', false)
            ->getContent();

        $this->assertStringContainsString('/c-eco/build/assets/site-', $html);
        $this->assertStringContainsString('/c-eco/masuk', $html);
        $this->assertStringContainsString('/c-eco/admin/login', $html);
    }

    public function test_the_root_landing_stays_unprefixed(): void
    {
        $html = $this->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/build/assets/site-', $html);
        $this->assertStringNotContainsString('/c-eco/build/assets/site-', $html);
    }

    public function test_a_token_entered_under_c_eco_stays_under_c_eco(): void
    {
        $this->seedBankWithParameters();
        $session = $this->makeSession();

        $this->from('/c-eco/')
            ->post('/c-eco/masuk', ['token' => $session->access_token])
            ->assertRedirectToRoute('student.show', ['token' => $session->access_token]);

        $this->get(route('student.show', ['token' => $session->access_token]))
            ->assertOk()
            ->assertSee('C-ECO', false);
    }

    public function test_a_forwarded_prefix_rewrites_generated_urls(): void
    {
        $html = $this->withHeaders(['X-Forwarded-Prefix' => '/c-eco'])
            ->get('/')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/c-eco/build/assets/site-', $html);
        $this->assertStringContainsString('/c-eco/admin/login', $html);
    }

    public function test_staff_login_is_reachable_under_c_eco(): void
    {
        $this->get('/c-eco/admin/login')
            ->assertOk()
            ->assertSee('Masuk panel', false);
    }
}
