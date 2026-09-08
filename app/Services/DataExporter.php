<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\ThetaEstimate;
use App\Models\Participant;
use App\Models\SessionEvent;
use App\Models\SessionItem;
use App\Models\TestSession;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Ekspor data mentah untuk analisis di R.
 *
 * Satu logika, dua pintu masuk: command cat:export dan tombol di halaman
 * Monitor memanggil kelas yang sama.
 *
 * Seluruh kolom ditulis apa adanya, tanpa agregasi dan tanpa membuang baris.
 * Kolom parameter (a, b, c) ikut dari item_parameters yang benar-benar dipakai
 * menilai baris itu, bukan dari parameter yang aktif saat ekspor dijalankan
 * (aturan R4): sesudah kalibrasi ulang keduanya berbeda, dan yang menjelaskan
 * jawaban peserta adalah yang pertama.
 */
class DataExporter
{
    /**
     * @return array{directory: string, files: array<string, int>}
     */
    public function export(?int $configId = null, bool $completedOnly = false, string $baseDirectory = 'exports'): array
    {
        $sessionIds = TestSession::query()
            ->when($configId !== null, fn ($q) => $q->where('test_config_id', $configId))
            ->when($completedOnly, fn ($q) => $q->where('status', 'completed'))
            ->pluck('id')
            ->all();

        if ($sessionIds === []) {
            throw new RuntimeException('Tidak ada sesi yang cocok dengan penyaring itu.');
        }

        $directory = trim($baseDirectory, '/').'/'.now()->format('Ymd-His');

        return [
            'directory' => $directory,
            'files' => [
                'sessions.csv' => $this->sessions($directory, $sessionIds),
                'session_items.csv' => $this->sessionItems($directory, $sessionIds),
                'participants.csv' => $this->participants($directory, $sessionIds),
                'events.csv' => $this->events($directory, $sessionIds),
            ],
        ];
    }

    /**
     * @param  list<int>  $sessionIds
     */
    private function sessions(string $directory, array $sessionIds): int
    {
        return $this->write("{$directory}/sessions.csv", [
            'id', 'participant_id', 'test_config_id', 'item_bank_id', 'access_token', 'rng_seed',
            'device_uuid', 'status', 'theta', 'se', 't_score', 'items_administered',
            'started_at', 'finished_at', 'last_seen_at', 'effective_connection', 'created_at',
        ], function () use ($sessionIds): iterable {
            foreach (TestSession::query()->whereIn('id', $sessionIds)->lazyById(500) as $s) {
                yield [
                    $s->id, $s->participant_id, $s->test_config_id, $s->item_bank_id,
                    $s->access_token, $s->rng_seed, $s->device_uuid, $s->status,
                    $s->theta, $s->se,
                    $s->theta === null ? null : round((new ThetaEstimate($s->theta, $s->se ?? 1.0))->tScore(), 2),
                    $s->items_administered,
                    $s->started_at?->toIso8601String(),
                    $s->finished_at?->toIso8601String(),
                    $s->last_seen_at?->toIso8601String(),
                    $s->effective_connection,
                    $s->created_at?->toIso8601String(),
                ];
            }
        });
    }

    /**
     * @param  list<int>  $sessionIds
     */
    private function sessionItems(string $directory, array $sessionIds): int
    {
        return $this->write("{$directory}/session_items.csv", [
            'id', 'test_session_id', 'sequence', 'item_id', 'item_code', 'dimension', 'grade',
            'item_parameter_id', 'model', 'a', 'b', 'c', 'is_provisional',
            'response_label', 'original_label', 'is_correct',
            'theta_before', 'se_before', 'theta_after', 'se_after',
            'information_at_selection', 'selection_rule', 'candidate_pool',
            'shown_at', 'answered_at', 'latency_ms', 'retry_count',
        ], function () use ($sessionIds): iterable {
            $query = SessionItem::query()
                ->whereIn('test_session_id', $sessionIds)
                ->with([
                    'item:id,code,dimension_id,item_bank_id',
                    'item.dimension:id,code',
                    'item.itemBank:id,grade',
                    'itemParameter.calibrationRun:id,is_provisional',
                ]);

            foreach ($query->lazyById(500) as $row) {
                $parameter = $row->itemParameter;
                $permutation = $row->option_permutation_json;

                yield [
                    $row->id, $row->test_session_id, $row->sequence, $row->item_id,
                    $row->item->code, $row->item->dimension->code, $row->item->itemBank->grade,
                    $row->item_parameter_id, $parameter->model,
                    $parameter->a, $parameter->b, $parameter->c,
                    $parameter->calibrationRun->is_provisional ? 1 : 0,
                    $row->response_label,
                    // Label asli di bank soal; response_label adalah label yang
                    // dilihat peserta setelah opsi diacak.
                    $row->response_label === null ? null : ($permutation[$row->response_label] ?? null),
                    $row->is_correct === null ? null : (int) $row->is_correct,
                    $row->theta_before, $row->se_before, $row->theta_after, $row->se_after,
                    $row->information_at_selection, $row->selection_rule,
                    json_encode($row->candidate_pool_json),
                    $row->shown_at?->toIso8601String(),
                    $row->answered_at?->toIso8601String(),
                    $row->latency_ms, $row->retry_count,
                ];
            }
        });
    }

    /**
     * @param  list<int>  $sessionIds
     */
    private function participants(string $directory, array $sessionIds): int
    {
        return $this->write("{$directory}/participants.csv", [
            'id', 'school_id', 'school', 'city', 'class_name', 'student_code', 'display_name', 'consent_at',
        ], function () use ($sessionIds): iterable {
            $ids = TestSession::query()->whereIn('id', $sessionIds)->distinct()->pluck('participant_id');

            foreach (Participant::query()->whereIn('id', $ids)->with('school')->lazyById(500) as $p) {
                yield [
                    $p->id, $p->school_id, $p->school->name, $p->school->city,
                    $p->class_name, $p->student_code, $p->display_name,
                    $p->consent_at?->toIso8601String(),
                ];
            }
        });
    }

    /**
     * @param  list<int>  $sessionIds
     */
    private function events(string $directory, array $sessionIds): int
    {
        return $this->write("{$directory}/events.csv", [
            'id', 'test_session_id', 'type', 'payload', 'occurred_at',
        ], function () use ($sessionIds): iterable {
            $query = SessionEvent::query()->whereIn('test_session_id', $sessionIds);

            foreach ($query->lazyById(1000) as $event) {
                yield [
                    $event->id, $event->test_session_id, $event->type,
                    $event->payload_json === null ? null : json_encode($event->payload_json),
                    $event->occurred_at?->toIso8601String(),
                ];
            }
        });
    }

    /**
     * @param  list<string>  $header
     * @param  callable(): iterable<list<mixed>>  $rows
     */
    private function write(string $path, array $header, callable $rows): int
    {
        // php://temp menahan di memori lalu tumpah ke disk sendiri, jadi
        // ekspor 300 sesi tidak menahan seluruh berkas di memori PHP.
        $handle = fopen('php://temp/maxmemory:8388608', 'r+');
        fputcsv($handle, $header);

        $count = 0;

        foreach ($rows() as $row) {
            fputcsv($handle, $row);
            $count++;
        }

        rewind($handle);
        Storage::disk('local')->put($path, $handle);
        fclose($handle);

        return $count;
    }
}
