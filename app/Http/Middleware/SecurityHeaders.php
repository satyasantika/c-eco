<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header pengerasan untuk halaman siswa.
 *
 * CSP di sini bukan sekadar praktik baik: ia menegakkan aturan R1 di sisi
 * peramban. Kalau suatu saat ada yang menambahkan skrip atau font dari CDN,
 * halaman siswa akan patah di lingkungan pengembangan alih-alih diam-diam
 * memakan kuota siswa pada 21 September.
 *
 * Sengaja tidak dipasang global: panel Filament memakai Alpine yang menuntut
 * 'unsafe-eval', dan melonggarkan CSP di satu tempat berarti melonggarkannya
 * untuk halaman siswa juga.
 */
class SecurityHeaders
{
    private const POLICY = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        // data: untuk gambar butir yang tersemat; blob: tidak dipakai.
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "frame-ancestors 'none'",
        "object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('Content-Security-Policy', implode('; ', self::POLICY));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        // Token ada di URL slip; jangan sampai bocor ke situs lain lewat Referer.
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // Halaman siswa memuat token dan butir; jangan pernah disinggahi cache
        // bersama, dan jangan disajikan ulang oleh tombol kembali (aturan R9).
        $headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $response;
    }
}
