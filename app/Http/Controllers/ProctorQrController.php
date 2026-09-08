<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ExamGroup;
use App\Models\User;
use App\Services\QrCodeRenderer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kartu QR di HP pengawas: satu token tampil setelah jam server mencapai starts_at,
 * lalu berganti begitu halaman siswa terbuka.
 */
class ProctorQrController extends Controller
{
    public function __construct(
        private readonly QrCodeRenderer $qr,
    ) {}

    public function show(Request $request, ExamGroup $examGroup): View
    {
        $this->authorizeGroup($request, $examGroup);

        return view('proctor.qr', $this->snapshot($examGroup) + [
            'group' => $examGroup->load(['school', 'supervisor', 'testConfig']),
        ]);
    }

    public function current(Request $request, ExamGroup $examGroup): JsonResponse
    {
        $this->authorizeGroup($request, $examGroup);

        $since = (string) $request->query('since', '');
        $wait = app()->environment('testing') ? 0.0 : 8.0;
        $until = microtime(true) + $wait;
        $payload = $this->snapshot($examGroup);

        while ($payload['started'] && microtime(true) < $until) {
            $payload = $this->snapshot($examGroup->fresh() ?? $examGroup);
            $token = $payload['token'];
            if (! $payload['started'] || $token === null || $token !== $since) {
                break;
            }
            usleep(150_000);
        }

        unset($payload['session']);

        return response()->json($payload);
    }

    /**
     * @return array{
     *     started: bool,
     *     session: mixed,
     *     opened: int,
     *     claimed: int,
     *     total: int,
     *     qr: ?string,
     *     url: ?string,
     *     spaced: ?string,
     *     token: ?string,
     *     done: bool,
     *     opens_at: ?string,
     *     opens_label: ?string,
     *     now_label: string
     * }
     */
    private function snapshot(ExamGroup $examGroup): array
    {
        $tz = (string) config('app.timezone');
        $now = Carbon::now();
        $clock = [
            'opens_at' => $examGroup->starts_at?->toIso8601String(),
            'opens_label' => $examGroup->starts_at?->timezone($tz)->format('d M Y H:i'),
            'now_label' => $now->timezone($tz)->format('H:i:s'),
        ];

        if (! $examGroup->hasStarted($now)) {
            return [
                'started' => false,
                'session' => null,
                'opened' => 0,
                'claimed' => 0,
                'total' => $examGroup->testSessions()->count(),
                'qr' => null,
                'url' => null,
                'spaced' => null,
                'token' => null,
                'done' => false,
            ] + $clock;
        }

        $sessions = $examGroup->testSessions()->orderBy('id')->get();
        $current = $sessions->first(fn ($s): bool => $s->opened_at === null);
        $url = $current === null ? null : route('student.show', $current->access_token);
        $token = $current?->access_token;

        return [
            'started' => true,
            'session' => $current,
            'opened' => $sessions->whereNotNull('opened_at')->count(),
            'claimed' => $sessions->whereNotNull('claimed_at')->count(),
            'total' => $sessions->count(),
            'qr' => $url === null ? null : $this->qr->svg($url, 240),
            'url' => $url,
            'spaced' => $token === null ? null : implode(' ', str_split($token, 4)),
            'token' => $token,
            'done' => $current === null,
        ] + $clock;
    }

    private function authorizeGroup(Request $request, ExamGroup $examGroup): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canProctorExamGroups(), 403);

        if ($user->isPengawas()) {
            abort_unless($examGroup->supervisor_id === $user->id, 403);
        }
    }
}
