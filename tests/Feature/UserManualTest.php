<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Support\UserManual;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManualTest extends TestCase
{
    public function test_landing_page_links_to_the_public_manual(): void
    {
        $this->get(route('landing'))
            ->assertOk()
            ->assertSee(route('manual.index'), false)
            ->assertSee('Panduan Pengguna');
    }

    public function test_manual_lists_every_panel_role_and_the_student(): void
    {
        $expected = array_merge(['siswa'], array_map(fn (UserRole $r): string => $r->value, UserRole::cases()));
        $this->assertEqualsCanonicalizing($expected, array_keys(UserManual::roles()));

        $response = $this->get(route('manual.index'))->assertOk();

        foreach (UserManual::roles() as $key => $role) {
            $response->assertSee(route('manual.show', $key), false)->assertSee($role['label']);
        }
    }

    public function test_each_role_page_is_public_and_shows_its_steps(): void
    {
        foreach (UserManual::roles() as $key => $role) {
            $response = $this->get(route('manual.show', $key))->assertOk()->assertSee('Panduan '.$role['label']);

            foreach ($role['sections'] as $section) {
                $response->assertSee($section['title']);
            }
        }

        $this->get('/panduan/superadmin')->assertNotFound();
    }

    public function test_captured_screenshots_are_shown_and_missing_ones_are_explained(): void
    {
        Storage::fake('manual');
        $shot = UserManual::roles()['admin']['sections'][0]['shot']['file'];
        Storage::disk('manual')->putFileAs(
            dirname($shot),
            UploadedFile::fake()->image('x.png'),
            basename($shot),
        );

        $this->get(route('manual.show', 'admin'))
            ->assertOk()
            ->assertSee(asset(UserManual::path($shot)), false)
            ->assertSee('manual:capture-screenshots');
    }

    public function test_every_shot_names_a_known_account_and_unique_file(): void
    {
        $files = array_column(UserManual::shots(), 'file');
        $this->assertSame(count($files), count(array_unique($files)));

        $allowed = ['guest', 'student', ...array_map(fn (UserRole $r): string => $r->value, UserRole::cases())];
        foreach (UserManual::shots() as $shot) {
            $this->assertContains($shot['as'], $allowed, $shot['file']);
        }
    }
}
