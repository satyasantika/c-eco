<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SessionItem;
use App\Models\TestSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsTestSessions;
use Tests\TestCase;

class StudentInterfaceTest extends TestCase
{
    use BuildsTestSessions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedBankWithParameters();
    }

    public function test_a_new_session_opens_on_the_consent_page(): void
    {
        $session = $this->makeSession();

        $this->get("/t/{$session->access_token}")
            ->assertOk()
            ->assertSee('Saya bersedia mengikuti tes ini.')
            // Angka kuota diukur, bukan dikira-kira (docs/LOADTEST-FINDINGS.md).
            ->assertSee('200 KB', false)
            ->assertSee('tidak bisa kembali', false);
    }

    public function test_consent_leads_to_an_ungraded_practice_item(): void
    {
        $session = $this->makeSession();

        $this->post("/t/{$session->access_token}/mulai", ['consent' => '1'])
            ->assertRedirect(route('student.practice', $session->access_token));

        $this->get("/t/{$session->access_token}/latihan")
            ->assertOk()
            ->assertSee('tidak dinilai');

        // Latihan tidak boleh menyentuh data sesi.
        $this->assertSame(0, SessionItem::query()->count());
        $this->assertSame('pending', TestSession::query()->find($session->id)->status);
    }

    public function test_starting_the_test_renders_the_first_item(): void
    {
        $session = $this->consented();

        $this->post("/t/{$session->access_token}/tes")
            ->assertRedirect(route('student.show', $session->access_token));

        $this->get("/t/{$session->access_token}")
            ->assertOk()
            ->assertSee('Butir ke-1')
            ->assertSee('data-sequence="1"', false)
            ->assertDontSee('dari 20');
    }

    public function test_answering_swaps_in_the_next_item_fragment(): void
    {
        $session = $this->startedSession();
        $label = $this->currentOption($session);

        $response = $this->post("/t/{$session->access_token}/jawab", [
            'sequence' => 1,
            'option' => $label,
        ]);

        $response->assertOk()
            ->assertSee('data-sequence="2"', false)
            ->assertSee('Butir ke-2');

        // Fragmen, bukan halaman penuh: htmx menukar isi #butir saja.
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    /** Aturan R3 dari sisi UI: kiriman ulang tidak menggandakan apa pun. */
    public function test_resending_the_same_answer_leaves_one_row(): void
    {
        $session = $this->startedSession();
        $label = $this->currentOption($session);

        $this->post("/t/{$session->access_token}/jawab", ['sequence' => 1, 'option' => $label])->assertOk();
        $this->post("/t/{$session->access_token}/jawab", ['sequence' => 1, 'option' => $label])
            ->assertOk()
            ->assertSee('data-sequence="2"', false);

        $this->assertSame(1, SessionItem::query()->where('sequence', 1)->count());
        $this->assertSame(1, TestSession::query()->find($session->id)->items_administered);
    }

    /**
     * Klien tertinggal di butir lama (outbox terkirim dua kali dengan opsi
     * berbeda). Server tetap menentukan butir yang menunggu, siswa tidak
     * terjebak di layar yang salah.
     */
    public function test_a_stale_client_is_pulled_forward_to_the_waiting_item(): void
    {
        $session = $this->startedSession();
        $options = $this->displayLabels($session);

        $this->post("/t/{$session->access_token}/jawab", ['sequence' => 1, 'option' => $options[0]])->assertOk();

        $this->post("/t/{$session->access_token}/jawab", ['sequence' => 1, 'option' => $options[1]])
            ->assertOk()
            ->assertSee('data-sequence="2"', false);

        $this->assertSame(1, SessionItem::query()->whereNotNull('response_label')->count());
    }

    /** SPEC §10 uji #10: batas Filament. */
    public function test_the_student_pages_load_no_filament_or_livewire_asset(): void
    {
        $session = $this->startedSession();

        foreach (["/t/{$session->access_token}", "/t/{$session->access_token}/latihan"] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsStringIgnoringCase('filament', (string) $html);
            $this->assertStringNotContainsStringIgnoringCase('livewire', (string) $html);
        }
    }

    /** Aturan R1: seluruh aset disajikan sendiri, tidak ada host pihak ketiga. */
    public function test_the_student_pages_reference_no_external_host(): void
    {
        $session = $this->startedSession();
        $html = (string) $this->get("/t/{$session->access_token}")->getContent();

        foreach (['cdn.', 'googleapis', 'gstatic', 'jsdelivr', 'unpkg', 'fonts.bunny'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
    }

    public function test_a_finished_session_shows_thanks_without_a_score(): void
    {
        $session = $this->startedSession();

        $guard = 0;

        while (TestSession::query()->find($session->id)->status !== 'completed' && $guard++ < 60) {
            $pending = SessionItem::query()
                ->where('test_session_id', $session->id)
                ->whereNull('response_label')
                ->orderBy('sequence')
                ->first();

            if ($pending === null) {
                break;
            }

            $this->post("/t/{$session->access_token}/jawab", [
                'sequence' => $pending->sequence,
                'option' => array_key_first($pending->option_permutation_json),
            ])->assertOk();
        }

        $html = (string) $this->get("/t/{$session->access_token}")->assertOk()->getContent();

        $this->assertStringContainsString('Terima kasih', $html);
        $this->assertStringNotContainsString('t_score', $html);
        $this->assertStringNotContainsString('Skor', $html);
        $this->assertStringNotContainsString('theta', $html);
    }

    private function consented(): TestSession
    {
        $session = $this->makeSession();
        $session->participant->forceFill(['consent_at' => Carbon::now()])->save();

        return $session;
    }

    private function startedSession(): TestSession
    {
        $session = $this->consented();
        $this->post("/t/{$session->access_token}/tes")->assertRedirect();

        return $session->fresh();
    }

    /**
     * @return list<string>
     */
    private function displayLabels(TestSession $session): array
    {
        $row = SessionItem::query()
            ->where('test_session_id', $session->id)
            ->whereNull('response_label')
            ->orderBy('sequence')
            ->firstOrFail();

        return array_keys($row->option_permutation_json);
    }

    private function currentOption(TestSession $session): string
    {
        return $this->displayLabels($session)[0];
    }
}
