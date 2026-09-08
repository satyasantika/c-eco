<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TestSession;
use App\Models\User;
use App\Services\CatSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class MonitorAndExportTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    private CatSession $cat;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk palsu supaya berkas ekspor satu uji tidak terhitung oleh uji lain.
        Storage::fake('local');

        $this->seedBankWithParameters();
        $this->cat = new CatSession;
    }

    public function test_the_monitor_page_renders_for_a_signed_in_user(): void
    {
        $this->makeSession();

        $this->actingAs(User::factory()->create())
            ->get('/admin/monitor')
            ->assertOk()
            ->assertSee('Monitor Pelaksanaan');
    }

    public function test_the_monitor_page_is_closed_to_visitors(): void
    {
        $this->get('/admin/monitor')->assertRedirect();
    }

    public function test_the_monitor_counts_sessions_by_status(): void
    {
        $running = $this->makeSession(token: 'AAAA3333');
        $this->cat->start($running);
        $this->makeSession(token: 'BBBB4444');

        $stats = $this->stats();

        $this->assertSame('1', $stats['Sedang mengerjakan']);
        $this->assertSame('1', $stats['Belum mulai']);
        $this->assertSame('0', $stats['Selesai']);
    }

    /** Angka yang membuat pengawas berjalan ke meja siswa. */
    public function test_a_session_with_no_news_is_counted_as_stuck(): void
    {
        $session = $this->makeSession();
        $this->cat->start($session);

        $this->assertSame('0', $this->stats()['Tersendat']);

        TestSession::query()->whereKey($session->id)->update([
            'last_seen_at' => Carbon::now()->subMinutes(9),
        ]);

        $this->assertSame('1', $this->stats()['Tersendat']);
    }

    public function test_the_export_writes_three_files_and_refuses_an_empty_selection(): void
    {
        $this->artisan('cat:export')->assertFailed();

        $this->runFullSession();

        $this->artisan('cat:export', ['--dir' => 'test-exports'])->assertSuccessful();

        $files = Storage::disk('local')->files('test-exports');

        $this->assertCount(3, $files);
        $this->assertNotEmpty(preg_grep('/sessions-/', $files));
        $this->assertNotEmpty(preg_grep('/responses-/', $files));
        $this->assertNotEmpty(preg_grep('/events-/', $files));
    }

    /**
     * R4 di sisi ekspor: baris respons membawa parameter yang menilainya,
     * bukan parameter yang kebetulan aktif saat ekspor dijalankan.
     */
    public function test_the_response_export_carries_the_parameters_used_at_the_time(): void
    {
        $session = $this->runFullSession();

        // Kalibrasi baru masuk setelah tes: parameter aktif berganti.
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();

        $this->artisan('cat:export', ['--dir' => 'test-exports'])->assertSuccessful();

        $rows = $this->readCsv(array_values(preg_grep('/responses-/', Storage::disk('local')->files('test-exports')))[0]);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotSame('', $row['item_parameter_id']);
            $this->assertNotSame('', $row['a']);
            $this->assertSame('1', $row['is_provisional']);

            // Parameter tercatat harus yang dipakai sesi, bukan yang terbaru.
            $stored = \App\Models\SessionItem::query()
                ->where('test_session_id', $session->id)
                ->where('sequence', (int) $row['sequence'])
                ->value('item_parameter_id');

            $this->assertSame((string) $stored, $row['item_parameter_id']);
        }
    }

    public function test_the_response_export_records_both_the_shown_and_original_label(): void
    {
        $this->runFullSession();
        $this->artisan('cat:export', ['--dir' => 'test-exports'])->assertSuccessful();

        $rows = $this->readCsv(array_values(preg_grep('/responses-/', Storage::disk('local')->files('test-exports')))[0]);

        foreach ($rows as $row) {
            $this->assertContains($row['response_label'], ['A', 'B', 'C', 'D', 'E']);
            $this->assertContains($row['original_label'], ['A', 'B', 'C', 'D', 'E']);
            $this->assertContains($row['is_correct'], ['0', '1']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function stats(): array
    {
        $widget = new \App\Filament\Widgets\SessionOverview;
        $method = new \ReflectionMethod($widget, 'getStats');

        $stats = [];

        foreach ($method->invoke($widget) as $stat) {
            $stats[$stat->getLabel()] = $stat->getValue();
        }

        return $stats;
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $lines = array_filter(explode("\n", Storage::disk('local')->get($path)));
        $header = str_getcsv(array_shift($lines));

        return array_map(static fn (string $line): array => array_combine($header, str_getcsv($line)), $lines);
    }

    private function runFullSession(): TestSession
    {
        $session = $this->makeSession();
        $state = $this->cat->start($session);

        $guard = 0;

        while (! $state->isFinished() && $guard++ < 60) {
            $state = $this->cat->answer(
                $session->fresh(),
                $state->item->sequence,
                $state->item->options[0]['label'],
            );
        }

        return $session;
    }
}
