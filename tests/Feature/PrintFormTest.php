<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ItemBank;
use App\Models\TestConfig;
use App\Services\CatSession;
use App\Services\LinearFormBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

/**
 * Lapis mundur L3 di docs/RUNBOOK.md: bentuk kertas.
 */
class PrintFormTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seedBankWithParameters();
    }

    /**
     * Inti dari lapis L3: lembar kertas harus berisi butir yang sama, dalam
     * urutan yang sama, dengan mode linear di layar. Kalau berbeda, dua
     * kelompok siswa mengerjakan tes yang berbeda.
     */
    public function test_the_printed_form_matches_the_linear_form_on_screen(): void
    {
        config(['cat.mode' => 'linear']);

        $session = $this->makeSession();
        $cat = new CatSession;
        $state = $cat->start($session);

        $guard = 0;

        while (! $state->isFinished() && $guard++ < 40) {
            $state = $cat->answer($session->fresh(), $state->item->sequence, $state->item->options[0]['label']);
        }

        $onScreen = $session->fresh()->sessionItems()->orderBy('sequence')->pluck('item_id')->all();

        $printed = app(LinearFormBuilder::class)
            ->build($session->testConfig)
            ->pluck('id')
            ->all();

        $this->assertSame($onScreen, $printed);
    }

    public function test_the_form_fills_the_configured_length_and_touches_every_dimension(): void
    {
        $config = $this->config();
        $items = app(LinearFormBuilder::class)->build($config);

        $this->assertCount($config->max_items, $items);
        $this->assertSame($items->pluck('id')->unique()->count(), $items->count());
        $this->assertCount(7, $items->pluck('dimension.code')->unique());
    }

    public function test_the_command_writes_a_sheet_of_questions_and_an_answer_sheet(): void
    {
        $config = $this->config();

        $this->artisan('cat:print-form', ['--config' => $config->id])->assertSuccessful();

        $html = Storage::disk('local')->get("fallback/bentuk-cetak-{$config->id}.html");

        $this->assertStringContainsString('Lembar Jawaban', $html);
        $this->assertStringContainsString($config->name, $html);

        // R1 juga berlaku di kertas: berkas ini dibuka saat jaringan mungkin
        // sudah tidak ada, jadi ia tidak boleh merujuk host mana pun.
        $this->assertDoesNotMatchRegularExpression('#(src|href)=["\']https?://#', $html);

        foreach (app(LinearFormBuilder::class)->build($config) as $item) {
            $this->assertStringContainsString($item->code, $html);
        }
    }

    /** Kunci jawaban tidak boleh ikut tercetak di lembar yang dibagikan. */
    public function test_the_printed_sheet_reveals_no_key(): void
    {
        $config = $this->config();

        $this->artisan('cat:print-form', ['--config' => $config->id])->assertSuccessful();

        $html = Storage::disk('local')->get("fallback/bentuk-cetak-{$config->id}.html");

        $this->assertStringNotContainsString('is_key', $html);
        $this->assertStringNotContainsString('kunci', mb_strtolower($html));
    }

    public function test_the_command_refuses_an_unknown_config(): void
    {
        $this->artisan('cat:print-form', ['--config' => 9999])->assertFailed();
    }

    private function config(): TestConfig
    {
        $bank = ItemBank::query()->where('grade', 'XI')->firstOrFail();

        return TestConfig::query()->where('item_bank_id', $bank->id)->firstOrFail();
    }
}
