<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Aturan R2 — setiap limiter sisi siswa dikunci ke token sesi.
 *
 * 300 siswa satu operator seluler berbagi segelintir IP publik karena CGNAT.
 * Limiter berbasis IP akan memblokir satu kelas penuh beberapa menit setelah
 * tes dimulai, dan gejalanya di lapangan terlihat seperti jaringan mati.
 * Jangan pernah mengganti ->by() di bawah ini dengan alamat IP.
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('cat-start', fn (Request $request): Limit => Limit::perMinute(10)->by($this->token($request)));
        RateLimiter::for('cat-answer', fn (Request $request): Limit => Limit::perMinute(60)->by($this->token($request)));
        RateLimiter::for('cat-event', fn (Request $request): Limit => Limit::perMinute(120)->by($this->token($request)));
        RateLimiter::for('cat-state', fn (Request $request): Limit => Limit::perMinute(60)->by($this->token($request)));
    }

    private function token(Request $request): string
    {
        return (string) $request->route('token');
    }
}
