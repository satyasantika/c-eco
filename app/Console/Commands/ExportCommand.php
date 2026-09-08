<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SessionEvent;
use App\Models\SessionItem;
use App\Models\TestSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Ekspor data mentah untuk dianalisis di R.
 *
 * Kolom parameter (a, b, c) ikut dari item_parameters yang benar-benar dipakai
 * menilai baris itu, bukan dari parameter yang aktif saat ekspor dijalankan
 * (aturan R4). Sesudah kalibrasi ulang, keduanya berbeda, dan yang menjelaskan
 * jawaban peserta adalah yang pertama.
 */
class ExportCommand extends Command
{
    protected $signature = 'cat:export
        {--config= : Batasi ke satu test_config}
        {--completed-only : Hanya sesi yang sudah selesai}
        {--dir=exports : Folder tujuan di disk local}';

    protected $description = 'Mengekspor sesi, respons, dan peristiwa ke CSV untuk analisis di R';

    public function handle(): int
    {
        $stamp = now()->format('Ymd-His');
        $directory = trim((string) $this->option('dir'), '/');

        $sessions = TestSession::query()
            ->when($this->option('config'), fn ($q, $id) => $q->where('test_config_id', (int) $id))
            ->when($this->option('completed-only'), fn ($q) => $q->where('status', 'completed'))
            ->pluck('id');

        if ($sessions->isEmpty()) {
            $this->error('Tidak ada sesi yang cocok dengan penyaring itu.');

            return self::FAILURE;
        }

        $written = [
            $this->writeSessions($directory, $stamp, $sessions->all()),
            $this->writeResponses($directory, $stamp, $sessions->all()),
            $this->writeEvents($directory, $stamp, $sessions->all()),
        ];

        $this->newLine();
        $this->info($sessions->count().' sesi diekspor:');

        foreach ($written as [$path, $rows]) {
            $this->line(sprintf('  %-12s %s', $rows.' baris', Storage::disk('local')->path($path)));
        }

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array{0: string, 1: int}
     */
    private function writeSessions(string $directory, string $stamp, array $sessionIds): array
    {
        return $this->write(
            "{$directory}/sessions-{$stamp}.csv",
            [
                'session_id', 'access_token', 'school', 'class_name', 'student_code', 'display_name',
                'test_config', 'mode', 'grade', 'status', 'theta', 'se', 't_score', 'items_administered',
                'rng_seed', 'started_at', 'finished_at', 'duration_seconds',
            ],
            function () use ($sessionIds): iterable {
                $query = TestSession::query()
                    ->whereIn('id', $sessionIds)
                    ->with(['participant.school', 'testConfig.itemBank']);

                foreach ($query->lazyById(500) as $session) {
                    yield [
                        $session->id,
                        $session->access_token,
                        $session->participant->school->name,
                        $session->participant->class_name,
                        $session->participant->student_code,
                        $session->participant->display_name,
                        $session->testConfig->name,
                        $session->testConfig->mode,
                        $session->testConfig->itemBank->grade,
                        $session->status,
                        $session->theta,
                        $session->se,
                        $session->theta === null ? null : round(min(90.0, max(10.0, 50 + 10 * (float) $session->theta)), 2),
                        $session->items_administered,
                        $session->rng_seed,
                        $session->started_at?->toIso8601String(),
                        $session->finished_at?->toIso8601String(),
                        $session->started_at && $session->finished_at
                            ? $session->finished_at->diffInSeconds($session->started_at, absolute: true)
                            : null,
                    ];
                }
            },
        );
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array{0: string, 1: int}
     */
    private function writeResponses(string $directory, string $stamp, array $sessionIds): array
    {
        return $this->write(
            "{$directory}/responses-{$stamp}.csv",
            [
                'session_id', 'access_token', 'sequence', 'item_code', 'dimension', 'grade',
                'item_parameter_id', 'model', 'a', 'b', 'c', 'is_provisional',
                'response_label', 'original_label', 'is_correct',
                'theta_before', 'se_before', 'theta_after', 'se_after',
                'information_at_selection', 'selection_rule',
                'shown_at', 'answered_at', 'latency_ms', 'retry_count',
            ],
            function () use ($sessionIds): iterable {
                $query = SessionItem::query()
                    ->whereIn('test_session_id', $sessionIds)
                    ->with([
                        'testSession:id,access_token',
                        'item:id,code,dimension_id,item_bank_id',
                        'item.dimension:id,code',
                        'item.itemBank:id,grade',
                        'itemParameter.calibrationRun:id,is_provisional',
                    ])
                    ->orderBy('test_session_id')
                    ->orderBy('sequence');

                foreach ($query->lazyById(500) as $row) {
                    $parameter = $row->itemParameter;
                    $permutation = $row->option_permutation_json;

                    yield [
                        $row->test_session_id,
                        $row->testSession->access_token,
                        $row->sequence,
                        $row->item->code,
                        $row->item->dimension->code,
                        $row->item->itemBank->grade,
                        $row->item_parameter_id,
                        $parameter->model,
                        $parameter->a,
                        $parameter->b,
                        $parameter->c,
                        $parameter->calibrationRun->is_provisional ? 1 : 0,
                        $row->response_label,
                        // Label asli di bank soal; response_label adalah label
                        // yang dilihat peserta setelah opsi diacak.
                        $row->response_label === null ? null : ($permutation[$row->response_label] ?? null),
                        $row->is_correct === null ? null : (int) $row->is_correct,
                        $row->theta_before,
                        $row->se_before,
                        $row->theta_after,
                        $row->se_after,
                        $row->information_at_selection,
                        $row->selection_rule,
                        $row->shown_at?->toIso8601String(),
                        $row->answered_at?->toIso8601String(),
                        $row->latency_ms,
                        $row->retry_count,
                    ];
                }
            },
        );
    }

    /**
     * @param  list<int>  $sessionIds
     * @return array{0: string, 1: int}
     */
    private function writeEvents(string $directory, string $stamp, array $sessionIds): array
    {
        return $this->write(
            "{$directory}/events-{$stamp}.csv",
            ['session_id', 'type', 'payload', 'occurred_at'],
            function () use ($sessionIds): iterable {
                $query = SessionEvent::query()
                    ->whereIn('test_session_id', $sessionIds)
                    ->orderBy('test_session_id')
                    ->orderBy('occurred_at');

                foreach ($query->lazyById(1000) as $event) {
                    yield [
                        $event->test_session_id,
                        $event->type,
                        $event->payload_json === null ? null : json_encode($event->payload_json),
                        $event->occurred_at?->toIso8601String(),
                    ];
                }
            },
        );
    }

    /**
     * @param  list<string>  $header
     * @param  callable(): iterable<list<mixed>>  $rows
     * @return array{0: string, 1: int}
     */
    private function write(string $path, array $header, callable $rows): array
    {
        // php://temp menampung di memori lalu tumpah ke disk sendiri, jadi
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

        return [$path, $count];
    }
}
