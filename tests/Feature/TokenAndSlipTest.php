<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\Participant;
use App\Models\TestConfig;
use App\Models\TestSession;
use App\Models\User;
use App\Services\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class TokenAndSlipTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    /** Token diketik ulang dari kertas; huruf dan angka yang mirip harus absen. */
    public function test_tokens_avoid_characters_that_are_easy_to_misread(): void
    {
        foreach (['0', 'O', '1', 'I', 'L', '2', 'Z', '5', 'S', '8', 'B'] as $character) {
            $this->assertStringNotContainsString($character, TokenIssuer::ALPHABET);
        }

        $issuer = new TokenIssuer;

        for ($i = 0; $i < 50; $i++) {
            $token = $issuer->uniqueToken();

            $this->assertSame(8, strlen($token));
            $this->assertMatchesRegularExpression('/^['.TokenIssuer::ALPHABET.']{8}$/', $token);
        }
    }

    public function test_issuing_tokens_twice_does_not_create_a_second_session(): void
    {
        $this->importParticipants();
        $config = TestConfig::query()->whereRelation('itemBank', 'grade', 'XI')->firstOrFail();

        $issuer = new TokenIssuer;
        $first = $issuer->issue(Participant::query()->get(), $config);
        $tokens = $first->pluck('access_token')->sort()->values()->all();

        $second = $issuer->issue(Participant::query()->get(), $config);

        $this->assertSame($tokens, $second->pluck('access_token')->sort()->values()->all());
        $this->assertSame(3, TestSession::query()->count());
    }

    public function test_every_issued_token_is_unique(): void
    {
        $this->importParticipants();
        $config = TestConfig::query()->firstOrFail();

        $tokens = (new TokenIssuer)->issue(Participant::query()->get(), $config)->pluck('access_token');

        $this->assertSame($tokens->count(), $tokens->unique()->count());
    }

    public function test_importing_the_same_roster_twice_adds_nobody(): void
    {
        $this->importParticipants();
        $this->assertSame(3, Participant::query()->count());

        $this->importParticipants();
        $this->assertSame(3, Participant::query()->count());
    }

    public function test_a_roster_with_a_blank_field_is_rejected_whole(): void
    {
        $path = $this->csv([
            ['SMA Uji', 'XI IPS 1', '001', 'Nama Ada'],
            ['SMA Uji', 'XI IPS 1', '', 'Kode Kosong'],
        ]);

        $this->artisan('cat:import-participants', ['file' => $path])->assertFailed();

        $this->assertSame(0, Participant::query()->count());
    }

    public function test_the_slip_page_is_closed_to_visitors(): void
    {
        $config = TestConfig::query()->firstOrFail();

        $this->get(route('admin.slips', $config))->assertRedirect('/login');
    }

    public function test_the_slip_page_prints_a_self_contained_qr_for_each_token(): void
    {
        $this->importParticipants();
        $config = TestConfig::query()->whereRelation('itemBank', 'grade', 'XI')->firstOrFail();
        $sessions = (new TokenIssuer)->issue(Participant::query()->get(), $config);

        $html = (string) $this->actingAs(User::factory()->create())
            ->get(route('admin.slips', $config))
            ->assertOk()
            ->getContent();

        foreach ($sessions as $session) {
            $this->assertStringContainsString($session->access_token, $html);
            $this->assertStringContainsString(route('student.show', $session->access_token), $html);
        }

        // QR dibuat di server sebagai SVG sebaris: tidak ada permintaan ke
        // layanan luar yang akan membawa token peserta keluar dari mesin ini.
        $this->assertSame($sessions->count(), substr_count($html, '<svg'));

        foreach (['api.qrserver', 'chart.googleapis', 'http://qr', 'cdn.'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
    }

    public function test_the_slip_sheet_holds_eight_slips(): void
    {
        $this->importParticipants(12);
        $config = TestConfig::query()->whereRelation('itemBank', 'grade', 'XI')->firstOrFail();
        (new TokenIssuer)->issue(Participant::query()->get(), $config);

        $html = (string) $this->actingAs(User::factory()->create())
            ->get(route('admin.slips', $config))
            ->assertOk()
            ->getContent();

        // 12 slip = 2 lembar A4.
        $this->assertSame(2, substr_count($html, 'class="sheet"'));
        $this->assertStringContainsString('2 halaman A4', $html);
    }

    /**
     * Kelas resource yang ada belum berarti halamannya bisa dirender: skema
     * Filament baru dievaluasi saat halaman dibuka.
     */
    public function test_the_admin_pages_actually_render(): void
    {
        $this->importParticipants();
        $user = User::factory()->create();

        foreach (['/admin/items', '/admin/item-banks', '/admin/participants', '/admin/schools', '/admin/exam-groups'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }

        $this->actingAs($user)->get('/admin/test-configs')->assertForbidden();
        $this->actingAs(User::factory()->operator()->create())->get('/admin/test-configs')->assertOk();
    }

    public function test_the_item_edit_page_renders_with_its_options(): void
    {
        $item = Item::query()->where('code', 'XI-01')->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get("/admin/items/{$item->id}/edit")
            ->assertOk()
            ->assertSee('Opsi jawaban');
    }

    /** Butir tidak diketik atau dihapus lewat panel; paket baru masuk lewat impor. */
    public function test_the_item_resource_offers_no_create_or_delete(): void
    {
        $this->assertFalse(ItemResource::canCreate());
        $this->assertFalse(ItemResource::canDeleteAny());
        $this->assertArrayNotHasKey('create', ItemResource::getPages());
    }

    private function importParticipants(int $count = 3): void
    {
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['SMA Uji', 'XI IPS 1', str_pad((string) $i, 4, '0', STR_PAD_LEFT), "Peserta {$i}"];
        }

        $this->artisan('cat:import-participants', ['file' => $this->csv($rows)])->assertSuccessful();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ceco-roster').'.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, ['school', 'class_name', 'student_code', 'display_name']);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
