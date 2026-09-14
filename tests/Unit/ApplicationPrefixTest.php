<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ApplicationPrefix;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApplicationPrefixTest extends TestCase
{
    public function test_root_paths_have_no_prefix(): void
    {
        foreach (['https://c-eco.tech/', 'https://c-eco.tech/admin/login', 'https://c-eco.tech/t/ABCD2345'] as $uri) {
            $this->assertSame('', ApplicationPrefix::detect(Request::create($uri)), $uri);
        }
    }

    public function test_c_eco_folder_is_detected_from_the_path(): void
    {
        $request = Request::create('https://supportfkip.unsil.ac.id/c-eco/admin/login');

        $this->assertSame('/c-eco', ApplicationPrefix::detect($request));
    }

    public function test_forwarded_prefix_header_is_detected(): void
    {
        $request = Request::create('https://supportfkip.unsil.ac.id/admin/login');
        $request->headers->set('X-Forwarded-Prefix', '/c-eco');

        $this->assertSame('/c-eco', ApplicationPrefix::detect($request));
    }

    public function test_app_url_path_applies_only_on_the_same_host(): void
    {
        config(['app.url' => 'https://supportfkip.unsil.ac.id/c-eco']);

        $same = Request::create('https://supportfkip.unsil.ac.id/');
        $other = Request::create('https://c-eco.tech/');

        $this->assertSame('/c-eco', ApplicationPrefix::detect($same));
        $this->assertSame('', ApplicationPrefix::detect($other));
    }

    public function test_reserved_names_are_not_treated_as_a_folder(): void
    {
        config(['app.subdirectory' => 'admin', 'app.dir' => 't']);

        $this->assertSame('', ApplicationPrefix::detect(Request::create('https://c-eco.tech/admin/login')));
        $this->assertSame('', ApplicationPrefix::forConsole());
    }
}
