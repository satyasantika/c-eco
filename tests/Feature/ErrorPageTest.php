<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unknown_path_shows_the_c_eco_error_sheet(): void
    {
        $html = $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('C-ECO', false)
            ->assertSee('404', false)
            ->assertSee('Halaman tidak ada', false)
            ->assertSee('GET /halaman-yang-tidak-ada', false)
            ->assertSee('Waktu server', false)
            ->assertSee('WIB', false)
            ->assertSee('Halaman depan', false)
            ->assertDontSee('could not be found', false)
            ->getContent();

        $this->assertStringContainsString('/css/error.css', $html);

        $this->assertStringNotContainsString('/build/assets', $html);
        $this->assertStringNotContainsStringIgnoringCase('cdn.', $html);
        $this->assertStringNotContainsStringIgnoringCase('googleapis', $html);
        $this->assertStringNotContainsStringIgnoringCase('Not Found', $html);
    }

    public function test_an_unknown_token_explains_the_missing_session(): void
    {
        $this->get('/t/NOEXISTX')
            ->assertNotFound()
            ->assertSee('Token tidak ketemu', false)
            ->assertSee('Keterangan', false)
            ->assertSee('GET /t/NOEXISTX', false)
            ->assertSee('Ketik token di halaman depan', false)
            ->assertSee('css/error.css', false)
            ->assertDontSee('No query results', false)
            ->assertDontSee('ModelNotFoundException', false);
    }

    public function test_a_forbidden_staff_page_names_the_role_limit(): void
    {
        $pengawas = User::factory()->pengawas()->create();

        $this->actingAs($pengawas)
            ->get('/admin/users')
            ->assertForbidden()
            ->assertSee('Tidak boleh dibuka', false)
            ->assertSee('pembatasan peran', false)
            ->assertSee('Buka panel', false);
    }

    public function test_an_expired_form_explains_the_419(): void
    {
        $request = Request::create('https://c-eco.tech/masuk', 'POST');
        $this->app->instance('request', $request);

        $response = app(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException,
        );

        $this->assertSame(419, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Sesi formulir kedaluwarsa', $html);
        $this->assertStringContainsString('POST /masuk', $html);
        $this->assertStringContainsString('C-ECO', $html);
    }

    public function test_a_throttled_token_explains_the_429(): void
    {
        $request = Request::create('https://c-eco.tech/t/ABCD2345');
        $this->app->instance('request', $request);

        $response = app(ExceptionHandler::class)->render(
            $request,
            new TooManyRequestsHttpException(60, 'Too Many Attempts.'),
        );

        $this->assertSame(429, $response->getStatusCode());
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Terlalu banyak permintaan', $html);
        $this->assertStringContainsString('dikunci ke token', $html);
        $this->assertStringContainsString('GET /t/ABCD2345', $html);
    }

    public function test_a_server_error_shows_the_app_message_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::get('/__boom', function (): never {
            throw new RuntimeException('Bank habis: tidak ada butir tersisa untuk sesi ini.');
        });

        $this->get('/__boom')
            ->assertStatus(500)
            ->assertSee('Server gagal memproses', false)
            ->assertSee('Keterangan', false)
            ->assertSee('Bank habis: tidak ada butir tersisa untuk sesi ini.', false)
            ->assertDontSee('SQLSTATE', false)
            ->assertDontSee('stack trace', false);
    }
}
