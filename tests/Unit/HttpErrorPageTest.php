<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HttpErrorPage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Models\TestSession;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class HttpErrorPageTest extends TestCase
{
    public function test_a_missing_page_explains_the_unknown_path(): void
    {
        $request = Request::create('https://c-eco.tech/halaman-yang-tidak-ada');
        $page = HttpErrorPage::from(new NotFoundHttpException('Not Found'), $request);

        $this->assertSame(404, $page->code);
        $this->assertSame('Halaman tidak ada', $page->title);
        $this->assertSame('GET', $page->method);
        $this->assertSame('/halaman-yang-tidak-ada', $page->path);
        $this->assertNull($page->detail);
        $this->assertFalse($page->student);
        $this->assertStringContainsString('WIB', $page->happenedAt);

        $routed = HttpErrorPage::from(
            new NotFoundHttpException('The route halaman-yang-tidak-ada could not be found.'),
            $request,
        );
        $this->assertNull($routed->detail);
    }

    public function test_a_missing_student_session_names_the_token_problem(): void
    {
        $inner = (new ModelNotFoundException)->setModel(TestSession::class);
        $exception = new NotFoundHttpException($inner->getMessage(), $inner);
        $request = Request::create('https://c-eco.tech/t/NOEXISTX');

        $page = HttpErrorPage::from($exception, $request);

        $this->assertSame('Token tidak ketemu', $page->title);
        $this->assertTrue($page->student);
        $this->assertNotNull($page->detail);
        $this->assertStringContainsString('token', strtolower((string) $page->detail));
    }

    public function test_an_app_runtime_message_is_shown_on_server_error(): void
    {
        $exception = new HttpException(500, 'Bank habis: tidak ada butir tersisa untuk sesi ini.');
        $request = Request::create('https://c-eco.tech/t/ABCD2345/jawab', 'POST');

        $page = HttpErrorPage::from($exception, $request);

        $this->assertSame(500, $page->code);
        $this->assertSame('Server gagal memproses', $page->title);
        $this->assertSame('Bank habis: tidak ada butir tersisa untuk sesi ini.', $page->detail);
        $this->assertTrue($page->student);
    }

    public function test_sql_and_paths_stay_off_the_public_sheet(): void
    {
        config(['app.debug' => false]);

        $exception = new HttpException(
            500,
            'SQLSTATE[HY000] [2002] Connection refused at /home/satya/code/c-eco/vendor/laravel/framework/src/Illuminate/Database/Connectors/Connector.php:66',
        );

        $page = HttpErrorPage::from($exception, Request::create('https://c-eco.tech/admin'));

        $this->assertNull($page->detail);
        $this->assertSame('Server gagal memproses', $page->title);
    }

    public function test_a_generic_forbidden_message_is_replaced(): void
    {
        $page = HttpErrorPage::from(
            new HttpException(403, 'This action is unauthorized.'),
            Request::create('https://c-eco.tech/admin/users'),
        );

        $this->assertSame('Tidak boleh dibuka', $page->title);
        $this->assertTrue($page->staff);
        $this->assertNull($page->detail);
    }

    public function test_wrapped_app_exceptions_still_surface_their_message(): void
    {
        $inner = new RuntimeException('Token ini sudah dipakai siswa lain. Minta kode QR berikutnya ke pengawas.');
        $exception = new HttpException(500, 'Server Error', $inner);

        $page = HttpErrorPage::from($exception, Request::create('https://c-eco.tech/t/ABCD2345'));

        $this->assertSame($inner->getMessage(), $page->detail);
    }
}
