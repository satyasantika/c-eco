<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    /**
     * CSP di halaman siswa menegakkan aturan R1 di peramban: skrip dan gaya
     * dari host lain ditolak, jadi CDN yang tak sengaja masuk akan patah di
     * sini alih-alih memakan kuota siswa pada hari-H.
     */
    public function test_the_student_page_forbids_anything_from_another_host(): void
    {
        $session = $this->makeSession();
        $policy = $this->policy($this->get("/t/{$session->access_token}"));

        $this->assertSame("'self'", $policy['script-src']);
        $this->assertSame("'self'", $policy['style-src']);
        $this->assertSame("'self' data:", $policy['img-src']);
        $this->assertSame("'self'", $policy['connect-src']);
        $this->assertSame("'none'", $policy['frame-ancestors']);

        // 'unsafe-inline' dan 'unsafe-eval' mengosongkan arti kebijakan ini.
        $this->assertStringNotContainsString('unsafe-', implode(' ', $policy));
    }

    public function test_the_json_endpoints_carry_the_same_headers(): void
    {
        $session = $this->makeSession();

        $response = $this->postJson("/api/t/{$session->access_token}/start")->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('same-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    /** Halaman berisi token; jangan pernah disimpan cache atau disajikan tombol kembali (R9). */
    public function test_the_student_page_is_never_cached(): void
    {
        $session = $this->makeSession();

        $cacheControl = $this->get("/t/{$session->access_token}")->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    /**
     * Panel admin sengaja TIDAK memakai kebijakan ketat ini: Alpine di Filament
     * menuntut 'unsafe-eval', dan melonggarkannya di satu tempat berarti
     * melonggarkannya untuk halaman siswa juga.
     */
    public function test_the_admin_panel_is_left_out_of_the_strict_policy(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/admin/monitor')->assertOk();

        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    /**
     * @return array<string, string>
     */
    private function policy(TestResponse $response): array
    {
        $directives = [];

        foreach (explode(';', (string) $response->headers->get('Content-Security-Policy')) as $directive) {
            $parts = preg_split('/\s+/', trim($directive), 2);

            if ($parts[0] !== '') {
                $directives[$parts[0]] = $parts[1] ?? '';
            }
        }

        return $directives;
    }
}
