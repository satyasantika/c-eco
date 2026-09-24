<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TestSession;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengikat kursi token ke peramban yang pertama kali memakainya.
 *
 * Identitas utama adalah sesi Laravel di HP itu (cookie sesi sudah ada
 * untuk CSRF). Cookie cadangan `ceco_seat_*` dipakai jika sesi terbit ulang
 * di tab yang sama. HP lain membuka tautan yang sama setelah kursi terikat
 * ditolak, supaya data tidak tertukar nama.
 */
class SeatGuard
{
    public const COOKIE_PREFIX = 'ceco_seat_';

    public const LIFETIME_MINUTES = 12 * 60;

    public function cookieName(string $accessToken): string
    {
        return self::COOKIE_PREFIX.strtolower($accessToken);
    }

    public function sessionKey(string $accessToken): string
    {
        return 'ceco.seat.'.strtolower($accessToken);
    }

    public function incomingSecret(Request $request, string $accessToken): ?string
    {
        if ($request->hasSession()) {
            $fromSession = $request->session()->get($this->sessionKey($accessToken));
            if ($this->isSecret($fromSession)) {
                return $fromSession;
            }
        }

        $fromCookie = $request->cookie($this->cookieName($accessToken));

        return $this->isSecret($fromCookie) ? $fromCookie : null;
    }

    public function admits(TestSession $session, Request $request): bool
    {
        $secret = $this->incomingSecret($request, $session->access_token);

        if ($session->resume_token === null) {
            // Selama izin pindah HP berlaku, HP lama tidak boleh merebut kursi kembali.
            $release = app(SeatReleaser::class)->activeRelease($session);

            return $release === null || $secret === null || ! hash_equals($release->previous_token, $secret);
        }

        return $secret !== null && hash_equals($session->resume_token, $secret);
    }

    public function secretFor(TestSession $session, Request $request): string
    {
        if (is_string($session->resume_token) && $session->resume_token !== '') {
            return $session->resume_token;
        }

        return $this->incomingSecret($request, $session->access_token)
            ?? bin2hex(random_bytes(16));
    }

    public function persist(TestSession $session, Request $request): string
    {
        if (is_string($session->resume_token) && $session->resume_token !== '') {
            $this->remember($request, $session->access_token, $session->resume_token);

            return $session->resume_token;
        }

        $secret = $this->secretFor($session, $request);
        $releaser = app(SeatReleaser::class);
        $release = $releaser->activeRelease($session);

        // HP baru setelah izin pindah: selalu kunci baru, jangan pakai ulang kunci HP lama.
        if ($release !== null && hash_equals($release->previous_token, $secret)) {
            $secret = bin2hex(random_bytes(16));
        }

        $bound = TestSession::query()
            ->whereKey($session->id)
            ->whereNull('resume_token')
            ->update(['resume_token' => $secret]);

        if ($bound > 0 && $release !== null) {
            $releaser->markUsed($session);
        }

        $session->resume_token = $session->fresh()?->resume_token ?? $secret;
        $this->remember($request, $session->access_token, $session->resume_token);

        return $session->resume_token;
    }

    public function remember(Request $request, string $accessToken, string $secret): void
    {
        if ($request->hasSession()) {
            $request->session()->put($this->sessionKey($accessToken), $secret);
        }
    }

    public function attachCookie(Response $response, Request $request, TestSession $session, string $secret): void
    {
        $this->remember($request, $session->access_token, $secret);

        $response->headers->setCookie(cookie(
            $this->cookieName($session->access_token),
            $secret,
            self::LIFETIME_MINUTES,
            $this->cookiePath($request),
            null,
            $request->secure(),
            true,
            false,
            'lax',
        ));
    }

    public function cookiePath(Request $request): string
    {
        $prefix = rtrim((string) config('session.path', ''), '/');

        return $prefix === '' ? '/' : $prefix;
    }

    private function isSecret(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) === 1;
    }
}
