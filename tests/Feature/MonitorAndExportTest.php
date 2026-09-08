<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Widgets\ConnectionMix;
use App\Filament\Widgets\ProgressDistribution;
use App\Filament\Widgets\ResponseHealth;
use App\Filament\Widgets\SessionOverview;
use App\Models\SessionItem;
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

    /** Waktu respons dicatat middleware, lalu dibaca widget tanpa tabel baru. */
    public function test_the_answer_endpoint_feeds_the_response_health_widget(): void
    {
        $session = $this->makeSession();
        $this->cat->start($session);

        $this->postJson("/api/t/{$session->access_token}/answer", [
            'sequence' => 1,
            'option' => 'A',
            'client_ts' => Carbon::now()->getTimestampMs(),
        ])->assertOk();

        $stats = $this->statsOf(new ResponseHealth);

        $this->assertSame('1', $stats['Permintaan']);
        $this->assertSame('0.00%', $stats['Galat 5xx']);
        $this->assertStringEndsWith(' ms', $stats['p95']);
    }

    public function test_the_progress_chart_counts_active_students_by_item(): void
    {
        $this->cat->start($this->makeSession(token: 'DDDD6666'));
        $this->runFullSession();

        $chart = new ProgressDistribution;
        $data = (new \ReflectionMethod($chart, 'getData'))->invoke($chart);

        // Hanya sesi in_progress yang dihitung; yang sudah selesai keluar.
        $this->assertSame([1], $data['datasets'][0]['data']);
        $this->assertSame(['butir 1'], $data['labels']);
    }

    public function test_the_connection_chart_labels_sessions_that_reported_nothing(): void
    {
        $this->cat->start($this->makeSession(token: 'EEEE7777'));

        $chart = new ConnectionMix;
        $data = (new \ReflectionMethod($chart, 'getData'))->invoke($chart);

        $this->assertSame(['tidak dilaporkan'], $data['labels']);
        $this->assertSame([1], $data['datasets'][0]['data']);
    }

    public function test_the_export_writes_four_files_and_refuses_an_empty_selection(): void
    {
        $this->artisan('cat:export')->assertFailed();

        $this->runFullSession();

        $this->artisan('cat:export', ['--dir' => 'test-exports'])->assertSuccessful();

        $files = array_map('basename', Storage::disk('local')->allFiles('test-exports'));

        sort($files);

        $this->assertSame(
            ['events.csv', 'participants.csv', 'session_items.csv', 'sessions.csv'],
            $files,
        );
    }

    /** Penyaring --completed-only tidak boleh menyeret sesi yang masih berjalan. */
    public function test_the_export_honours_the_completed_only_filter(): void
    {
        $running = $this->makeSession(token: 'CCCC5555');
        $this->cat->start($running);
        $done = $this->runFullSession();

        $this->artisan('cat:export', ['--dir' => 'test-exports', '--completed-only' => true])
            ->assertSuccessful();

        $rows = $this->readCsv($this->exportedFile('sessions.csv'));

        $this->assertCount(1, $rows);
        $this->assertSame((string) $done->id, $rows[0]['id']);
    }

    public function test_the_export_includes_the_participants_behind_the_sessions(): void
    {
        $session = $this->runFullSession();

        $this->artisan('cat:export', ['--dir' => 'test-exports'])->assertSuccessful();

        $rows = $this->readCsv($this->exportedFile('participants.csv'));

        $this->assertCount(1, $rows);
        $this->assertSame((string) $session->participant_id, $rows[0]['id']);
        $this->assertNotSame('', $rows[0]['school']);
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

        $rows = $this->readCsv($this->exportedFile('session_items.csv'));

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotSame('', $row['item_parameter_id']);
            $this->assertNotSame('', $row['a']);
            $this->assertSame('1', $row['is_provisional']);

            // Parameter tercatat harus yang dipakai sesi, bukan yang terbaru.
            $stored = SessionItem::query()
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

        $rows = $this->readCsv($this->exportedFile('session_items.csv'));

        foreach ($rows as $row) {
            $this->assertContains($row['response_label'], ['A', 'B', 'C', 'D', 'E']);
            $this->assertContains($row['original_label'], ['A', 'B', 'C', 'D', 'E']);
            $this->assertContains($row['is_correct'], ['0', '1']);
        }
    }

    /** Ekspor terbaru berada di subfolder bercap waktu; ambil yang paling akhir. */
    private function exportedFile(string $name): string
    {
        $matches = array_values(array_filter(
            Storage::disk('local')->allFiles('test-exports'),
            static fn (string $path): bool => basename($path) === $name,
        ));

        sort($matches);

        $this->assertNotEmpty($matches, "Berkas {$name} tidak ada di hasil ekspor.");

        return end($matches);
    }

    /**
     * @return array<string, string>
     */
    private function stats(): array
    {
        return $this->statsOf(new SessionOverview);
    }

    /**
     * @return array<string, string>
     */
    private function statsOf(object $widget): array
    {
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
