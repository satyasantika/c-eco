<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SequenceConflictException;
use App\Exceptions\SessionCompletedException;
use App\Http\Controllers\Controller;
use App\Models\SessionEvent;
use App\Models\TestSession;
use App\Services\CatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Kontrak API sisi siswa, SPEC §7.
 *
 * Tidak ada respons di berkas ini yang boleh memuat is_key atau benar/salah:
 * siswa tidak diberi tahu jawabannya benar, dan tidak ada cara membacanya dari
 * muatan yang dikirim.
 */
class SessionController extends Controller
{
    public function __construct(private readonly CatSession $cat) {}

    public function start(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'device_uuid' => ['nullable', 'string', 'max:64'],
            'screen_w' => ['nullable', 'integer', 'min:100', 'max:10000'],
            'effective_connection' => ['nullable', 'string', 'max:16'],
        ]);

        $session = $this->session($token);

        $state = $this->cat->start($session, $validated + [
            'user_agent' => (string) $request->userAgent(),
            'ip' => (string) $request->ip(),
        ]);

        return response()->json($state->toArray());
    }

    public function answer(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'sequence' => ['required', 'integer', 'min:1'],
            'option' => ['required', 'string', 'size:1'],
            'client_ts' => ['nullable', 'numeric'],
            'latency_ms' => ['nullable', 'integer', 'min:0'],
        ]);

        $session = $this->session($token);

        try {
            $state = $this->cat->answer(
                session: $session,
                sequence: (int) $validated['sequence'],
                displayLabel: strtoupper($validated['option']),
                meta: $validated,
            );
        } catch (SequenceConflictException) {
            return response()->json(['error' => 'sequence_conflict'], Response::HTTP_CONFLICT);
        } catch (SessionCompletedException) {
            return response()->json($this->cat->state($session->fresh())->toArray());
        }

        return response()->json($state->toArray());
    }

    /**
     * Fire and forget: pencatatan peristiwa tidak boleh menghentikan tes,
     * jadi kegagalan apa pun di sini tetap dijawab 204 (SPEC §7).
     */
    public function event(Request $request, string $token): Response
    {
        try {
            $validated = $request->validate([
                'type' => ['required', 'string', 'in:visibility_hidden,visibility_visible,resume,connection_change,retry,duplicate_submit'],
                'payload' => ['nullable', 'array'],
            ]);

            SessionEvent::query()->create([
                'test_session_id' => $this->session($token)->id,
                'type' => $validated['type'],
                'payload_json' => $validated['payload'] ?? null,
                'occurred_at' => Carbon::now(),
            ]);
        } catch (Throwable) {
            // sengaja diabaikan
        }

        return response()->noContent();
    }

    public function state(string $token): JsonResponse
    {
        return response()->json($this->cat->state($this->session($token))->toArray());
    }

    private function session(string $token): TestSession
    {
        return TestSession::query()->where('access_token', $token)->firstOrFail();
    }
}
