<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\Candidate;
use App\CAT\ContentBalancer;
use App\CAT\EapEstimator;
use App\CAT\ItemSelector;
use App\CAT\LinearSelector;
use App\CAT\NoCandidateException;
use App\CAT\Response;
use App\CAT\StoppingRule;
use App\Exceptions\SequenceConflictException;
use App\Exceptions\SessionCompletedException;
use App\Models\Item;
use App\Models\SessionEvent;
use App\Models\SessionItem;
use App\Models\TestSession;
use App\Support\PresentedItem;
use App\Support\SessionState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Satu-satunya lapisan yang menghubungkan mesin murni App\CAT dengan basis data.
 *
 * Semua penulisan sesi lewat sini supaya aturan idempotensi (R3), jejak
 * parameter (R4), dan waktu server (R6) hanya perlu benar di satu tempat.
 */
class CatSession
{
    /**
     * Aliran acak pengacakan opsi dipisahkan dari aliran pemilihan butir:
     * kalau berbagi aliran, mengubah salah satunya akan menggeser yang lain
     * dan sesi lama tidak bisa direkonstruksi lagi.
     */
    private const PERMUTATION_STREAM_OFFSET = 7919;

    /**
     * Percobaan ulang bila InnoDB melaporkan deadlock. Deadlock bukan galat
     * logika: dua sesi kebetulan menyentuh baris yang sama dengan urutan
     * berbeda, dan yang kalah tinggal mengulang. Tanpa ini, siswa melihat 500
     * pada jawaban yang sebenarnya sah. Pengecualian lain — konflik urutan,
     * sesi selesai — tidak diulang, hanya deadlock.
     */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly EapEstimator $estimator = new EapEstimator,
        private readonly SessionReporter $reporter = new SessionReporter,
    ) {}

    /**
     * Idempoten: pada sesi berjalan mengembalikan keadaan sekarang, bukan sesi baru.
     *
     * @param  array<string, mixed>  $meta
     */
    public function start(TestSession $session, array $meta = []): SessionState
    {
        return DB::transaction(function () use ($session, $meta): SessionState {
            $session = $this->lock($session);

            if ($session->status === 'completed') {
                return $this->completedState($session);
            }

            if ($session->status === 'pending') {
                $session->forceFill([
                    'status' => 'in_progress',
                    'started_at' => Carbon::now(),
                ]);
            }

            $session->forceFill($this->deviceAttributes($meta) + ['last_seen_at' => Carbon::now()])->save();

            $pending = $this->pendingItem($session);

            if ($pending === null) {
                return $this->advance($session);
            }

            return new SessionState($session, $this->present($pending));
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function answer(TestSession $session, int $sequence, string $displayLabel, array $meta = []): SessionState
    {
        return DB::transaction(function () use ($session, $sequence, $displayLabel, $meta): SessionState {
            $session = $this->lock($session);

            if ($session->status === 'completed') {
                throw new SessionCompletedException("Sesi {$session->access_token} sudah selesai.");
            }

            $sessionItem = $session->sessionItems()->where('sequence', $sequence)->first();

            if ($sessionItem === null) {
                throw new SequenceConflictException(
                    "Butir ke-{$sequence} belum pernah disajikan pada sesi {$session->access_token}."
                );
            }

            if ($sessionItem->response_label !== null) {
                return $this->handleResubmission($session, $sessionItem, $displayLabel);
            }

            $this->score($session, $sessionItem, $displayLabel, $meta);

            return $this->advance($session);
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Pemulihan sesi. Membaca session_items dan test_sessions sebagai
     * satu-satunya sumber kebenaran — tidak ada masukan dari klien (SPEC §7).
     */
    public function state(TestSession $session): SessionState
    {
        $session->forceFill(['last_seen_at' => Carbon::now()])->save();

        if ($session->status === 'completed') {
            return $this->completedState($session);
        }

        if ($session->status === 'pending') {
            return new SessionState($session);
        }

        $pending = $this->pendingItem($session);

        return new SessionState($session, $pending === null ? null : $this->present($pending));
    }

    /**
     * Kiriman ulang untuk sequence yang sudah dijawab (aturan R3).
     * Jaringan seluler mengirim ulang; itu normal dan tidak boleh menggandakan data.
     */
    private function handleResubmission(TestSession $session, SessionItem $sessionItem, string $displayLabel): SessionState
    {
        if ($sessionItem->response_label !== $displayLabel) {
            throw new SequenceConflictException(
                "Butir ke-{$sessionItem->sequence} sudah dijawab '{$sessionItem->response_label}', ".
                "kiriman baru '{$displayLabel}' berbeda."
            );
        }

        $sessionItem->increment('retry_count');

        SessionEvent::query()->create([
            'test_session_id' => $session->id,
            'type' => 'duplicate_submit',
            'payload_json' => ['sequence' => $sessionItem->sequence, 'option' => $displayLabel],
            'occurred_at' => Carbon::now(),
        ]);

        // θ tidak dihitung ulang dan exposure_counters tidak dinaikkan:
        // ini permintaan yang sama, bukan jawaban baru.
        $state = $session->status === 'completed'
            ? $this->completedState($session)
            : $this->currentState($session);

        return new SessionState($state->session, $state->item, duplicate: true, result: $state->result);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function score(TestSession $session, SessionItem $sessionItem, string $displayLabel, array $meta): void
    {
        $permutation = $sessionItem->option_permutation_json;

        if (! isset($permutation[$displayLabel])) {
            throw new SequenceConflictException(
                "Opsi '{$displayLabel}' tidak ada pada butir ke-{$sessionItem->sequence}."
            );
        }

        $originalLabel = $permutation[$displayLabel];
        $item = $sessionItem->item()->with('options')->firstOrFail();
        $isCorrect = (bool) $item->options->firstWhere('label', $originalLabel)?->is_key;

        $responses = $this->responses($session);
        $responses[] = new Response($sessionItem->itemParameter->toCat(), $isCorrect);
        $estimate = $this->estimator->estimate($responses);

        $sessionItem->forceFill([
            // Label tampilan, bukan label asli: klien mengirim ulang label ini
            // dan pemeriksaan idempotensi membandingkannya apa adanya.
            'response_label' => $displayLabel,
            'is_correct' => $isCorrect,
            'theta_after' => $estimate->theta,
            'se_after' => $estimate->se,
            // R6: waktu jawaban diambil dari jam server. client_ts hanya data mentah.
            'answered_at' => Carbon::now(),
            'latency_ms' => isset($meta['latency_ms']) ? (int) $meta['latency_ms'] : null,
        ])->save();

        $session->forceFill([
            'theta' => $estimate->theta,
            'se' => $estimate->se,
            'items_administered' => $session->items_administered + 1,
            'last_seen_at' => Carbon::now(),
        ])->save();

        $this->bumpExposure($session, $sessionItem->item_id, 'times_administered');
    }

    /** Menyajikan butir berikutnya, atau menutup sesi bila aturan berhenti terpenuhi. */
    private function advance(TestSession $session): SessionState
    {
        $responses = $this->responses($session);
        $estimate = $this->estimator->estimate($responses);

        $candidates = $this->candidates($session);
        $rule = $this->stoppingRule($session);

        if ($rule->shouldStop($session->items_administered, $estimate->se, count($candidates))) {
            return $this->finish($session);
        }

        try {
            $sessionItem = $this->presentNext($session, $candidates, $responses, $estimate->theta, $estimate->se);
        } catch (NoCandidateException) {
            return $this->finish($session);
        }

        return new SessionState($session, $this->present($sessionItem));
    }

    /**
     * @param  list<Candidate>  $candidates
     * @param  list<Response>  $responses
     */
    private function presentNext(
        TestSession $session,
        array $candidates,
        array $responses,
        float $theta,
        float $se,
    ): SessionItem {
        $sequence = $session->sessionItems()->max('sequence') + 1;
        $dimensions = $this->administeredDimensions($session);
        $balancer = $this->balancer($session);

        $selection = $this->mode($session) === 'linear'
            ? (new LinearSelector($balancer, $session->testConfig->max_items))
                ->select($candidates, $dimensions, $sequence, $theta)
            : (new ItemSelector($balancer, $this->estimator))->select(
                candidates: $candidates,
                responses: $responses,
                administeredDimensions: $dimensions,
                sequence: $sequence,
                rngSeed: $session->rng_seed,
            );

        $item = Item::query()->with(['options', 'activeParameter'])->findOrFail($selection->candidate->itemId);

        // R4: tanpa parameter aktif butir ini tidak boleh disajikan — datanya
        // tidak akan bisa dinilai ulang setelah kalibrasi final.
        if ($item->activeParameter === null) {
            throw new RuntimeException("Butir {$item->code} tidak punya parameter aktif; sesi dihentikan daripada menyimpan baris tanpa jejak parameter.");
        }

        $sessionItem = SessionItem::query()->create([
            'test_session_id' => $session->id,
            'sequence' => $sequence,
            'item_id' => $item->id,
            'item_parameter_id' => $item->activeParameter->id,
            'theta_before' => $theta,
            'se_before' => $se,
            'information_at_selection' => $selection->information,
            'selection_rule' => $selection->selectionRule,
            'candidate_pool_json' => $selection->candidatePool,
            'option_permutation_json' => $this->permutation($session, $item, $sequence),
            'shown_at' => Carbon::now(),
        ]);

        $this->bumpExposure($session, $item->id, 'times_selected');

        return $sessionItem->setRelation('item', $item);
    }

    /**
     * Mode yang berlaku untuk sesi ini, aturan R10.
     *
     * Sesi yang sudah berjalan TIDAK berubah mode di tengah jalan: aturan yang
     * dipakai butir pertama mengikat sampai selesai. Kalau tidak, seorang siswa
     * bisa mengerjakan separuh tes adaptif lalu separuh tes linear, dan datanya
     * tidak menggambarkan kondisi mana pun.
     */
    private function mode(TestSession $session): string
    {
        $firstRule = $session->sessionItems()->orderBy('sequence')->value('selection_rule');

        if ($firstRule !== null) {
            return str_starts_with($firstRule, 'linear') ? 'linear' : 'adaptive';
        }

        // Penimpa global menang atas test_configs, tetapi hanya untuk sesi BARU.
        return config('cat.mode') === 'linear' ? 'linear' : $session->testConfig->mode;
    }

    /** Mode linear selalu menyajikan tepat max_items butir. */
    private function stoppingRule(TestSession $session): StoppingRule
    {
        $config = $session->testConfig;

        if ($this->mode($session) === 'linear') {
            return new StoppingRule($config->max_items, $config->max_items, $config->se_target);
        }

        return new StoppingRule($config->min_items, $config->max_items, $config->se_target);
    }

    private function finish(TestSession $session): SessionState
    {
        $session->forceFill([
            'status' => 'completed',
            'finished_at' => Carbon::now(),
            'last_seen_at' => Carbon::now(),
        ])->save();

        return $this->completedState($session);
    }

    private function completedState(TestSession $session): SessionState
    {
        return new SessionState($session, null, false, $this->reporter->report($session));
    }

    private function currentState(TestSession $session): SessionState
    {
        $pending = $this->pendingItem($session);

        return new SessionState($session, $pending === null ? null : $this->present($pending));
    }

    private function pendingItem(TestSession $session): ?SessionItem
    {
        return $session->sessionItems()
            ->whereNull('response_label')
            ->with('item')
            ->orderBy('sequence')
            ->first();
    }

    /**
     * Opsi dikirim sudah teracak; server memetakan balik saat menilai.
     */
    private function present(SessionItem $sessionItem): PresentedItem
    {
        $item = $sessionItem->item;
        $options = $item->options->keyBy('label');

        $shuffled = [];

        foreach ($sessionItem->option_permutation_json as $displayLabel => $originalLabel) {
            $shuffled[] = [
                'label' => $displayLabel,
                'body_html' => (string) $options[$originalLabel]->body_html,
            ];
        }

        return new PresentedItem(
            sequence: $sessionItem->sequence,
            stemHtml: $item->stem_html,
            mediaPath: $item->media_path,
            options: $shuffled,
        );
    }

    /**
     * @return array<string, string> label tampilan => label asli
     */
    private function permutation(TestSession $session, Item $item, int $sequence): array
    {
        $original = $item->options->pluck('label')->all();

        if (! $session->testConfig->shuffle_options) {
            return array_combine($original, $original);
        }

        $randomizer = new Randomizer(new Mt19937(
            $session->rng_seed + self::PERMUTATION_STREAM_OFFSET * $sequence
        ));

        return array_combine($original, $randomizer->shuffleArray($original));
    }

    /**
     * @return list<Response>
     */
    private function responses(TestSession $session): array
    {
        return $session->sessionItems()
            ->whereNotNull('response_label')
            ->with('itemParameter')
            ->orderBy('sequence')
            ->get()
            ->map(fn (SessionItem $row): Response => new Response(
                $row->itemParameter->toCat(),
                (bool) $row->is_correct,
            ))
            ->all();
    }

    /**
     * Butir yang masih boleh disajikan: aktif, sejenjang, belum keluar di sesi
     * ini, dan punya parameter aktif (R4).
     *
     * @return list<Candidate>
     */
    private function candidates(TestSession $session): array
    {
        $administered = $session->sessionItems()->pluck('item_id')->all();

        return Item::query()
            ->active()
            ->where('item_bank_id', $session->item_bank_id)
            ->whereNotIn('id', $administered)
            ->whereHas('parameters', fn ($q) => $q->where('is_active', true))
            ->with(['activeParameter', 'dimension:id,code'])
            ->orderBy('id')
            ->get()
            ->map(fn (Item $item): Candidate => new Candidate(
                itemId: $item->id,
                dimension: $item->dimension->code,
                parameter: $item->activeParameter->toCat(),
            ))
            ->all();
    }

    /** Target proporsi dihitung dari seluruh bank jenjang, bukan sisa kandidat. */
    private function balancer(TestSession $session): ContentBalancer
    {
        $counts = Item::query()
            ->active()
            ->where('item_bank_id', $session->item_bank_id)
            ->join('dimensions', 'items.dimension_id', '=', 'dimensions.id')
            ->selectRaw('dimensions.code as code, count(*) as total')
            ->groupBy('dimensions.code')
            ->pluck('total', 'code')
            ->map(static fn ($n): int => (int) $n)
            ->all();

        return ContentBalancer::fromCounts($counts);
    }

    /**
     * @return list<string>
     */
    private function administeredDimensions(TestSession $session): array
    {
        return $session->sessionItems()
            ->join('items', 'session_items.item_id', '=', 'items.id')
            ->join('dimensions', 'items.dimension_id', '=', 'dimensions.id')
            ->orderBy('session_items.sequence')
            ->pluck('dimensions.code')
            ->all();
    }

    /**
     * Menaikkan pencacah paparan setelah transaksi sesi commit.
     *
     * Dua hal disengaja di sini, keduanya hasil uji beban 100 peserta serentak
     * (docs/LOADTEST-FINDINGS.md):
     *
     * 1. Satu pernyataan INSERT .. ON DUPLICATE KEY UPDATE, bukan increment
     *    lalu insert kalau nol baris terpengaruh. Pola lama punya celah balapan:
     *    dua sesi sama-sama melihat nol baris dan sama-sama menyisipkan.
     *
     * 2. Dijalankan SETELAH commit, bukan di dalam transaksi sesi. Pemilihan
     *    adaptif memusat pada butir yang sama, jadi banyak sesi menyentuh baris
     *    pencacah yang sama dengan urutan berbeda; ditahan di dalam transaksi
     *    panjang, itu menghasilkan deadlock InnoDB dan jawaban siswa gagal.
     *    Sebagai pernyataan tunggal yang autocommit, kuncinya lepas seketika.
     *
     * Konsekuensinya pencacah bisa tertinggal sepersekian detik dari sesi.
     * Itu tidak apa-apa: angka ini mengendalikan paparan secara statistik,
     * bukan menentukan benar-salah satu jawaban.
     */
    private function bumpExposure(TestSession $session, int $itemId, string $column): void
    {
        $configId = $session->test_config_id;

        DB::afterCommit(static function () use ($configId, $itemId, $column): void {
            DB::table('exposure_counters')->upsert(
                [[
                    'test_config_id' => $configId,
                    'item_id' => $itemId,
                    'times_selected' => $column === 'times_selected' ? 1 : 0,
                    'times_administered' => $column === 'times_administered' ? 1 : 0,
                ]],
                ['test_config_id', 'item_id'],
                [$column => DB::raw("`{$column}` + 1")],
            );
        });
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function deviceAttributes(array $meta): array
    {
        $attributes = [];

        if (isset($meta['device_uuid'])) {
            $attributes['device_uuid'] = (string) $meta['device_uuid'];
        }

        if (isset($meta['user_agent'])) {
            $attributes['user_agent_hash'] = hash('sha256', (string) $meta['user_agent']);
        }

        if (isset($meta['ip'])) {
            $attributes['ip_hash'] = hash('sha256', (string) $meta['ip']);
        }

        if (isset($meta['effective_connection'])) {
            $attributes['effective_connection'] = (string) $meta['effective_connection'];
        }

        return $attributes;
    }

    private function lock(TestSession $session): TestSession
    {
        return TestSession::query()
            ->whereKey($session->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
