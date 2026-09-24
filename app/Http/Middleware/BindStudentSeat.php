<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TestSession;
use App\Services\SeatGuard;
use App\Services\SeatReleaser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak HP lain yang membuka token yang sudah terikat.
 *
 * Kursi QR baru terikat setelah identitas terkirim (atau sesi slip/berjalan
 * pada GET pertama). Halaman "tes belum dimulai" tidak mengikat, supaya
 * siswa boleh menunggu di tautan yang sama lalu lanjut di HP itu juga.
 */
class BindStudentSeat
{
    public function __construct(
        private readonly SeatGuard $guard,
        private readonly SeatReleaser $releaser,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $session = TestSession::query()
            ->where('access_token', $token)
            ->with('examGroup')
            ->first();

        if ($session === null) {
            return $next($request);
        }

        if (! $session->examWindowOpen()) {
            return $next($request);
        }

        $session = $this->releaser->settle($session);

        if (! $this->guard->admits($session, $request)) {
            return $this->taken($request, $session);
        }

        $response = $next($request);
        $session = $session->fresh(['examGroup']) ?? $session;
        $secret = $this->guard->secretFor($session, $request);

        // Izin pindah HP berlaku: kursi terikat ke HP baru ini begitu dibuka.
        $moving = $this->releaser->activeRelease($session) !== null;

        if ($session->resume_token === null && ($moving || $this->shouldPersist($session))) {
            $secret = $this->guard->persist($session, $request);
        }

        if ($session->resume_token !== null || $session->isUnclaimed()) {
            $this->guard->attachCookie($response, $request, $session, $secret);
        }

        return $response;
    }

    private function shouldPersist(TestSession $session): bool
    {
        return ! $session->isUnclaimed()
            || $session->status !== 'pending';
    }

    private function taken(Request $request, TestSession $session): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'seat_taken'], Response::HTTP_FORBIDDEN);
        }

        return response()->view('student.occupied', [
            'session' => $session,
            'occupied' => true,
        ], Response::HTTP_FORBIDDEN);
    }
}
