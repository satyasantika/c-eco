<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga halaman siswa tetap bebas Livewire (KA-8).
 *
 * Filament menyalakan penyuntikan aset Livewire lewat properti statis. Di
 * PHP-FPM setiap permintaan adalah proses baru, jadi flag itu tidak menyeberang
 * ke halaman siswa — tetapi itu jaminan yang kebetulan, bukan yang dinyatakan.
 * Di bawah Octane, atau proses uji yang dipakai bersama, ia menyeberang, dan
 * halaman siswa mendadak membawa aset yang tidak pernah dipakainya.
 */
class WithoutLivewireAssets
{
    public function handle(Request $request, Closure $next): Response
    {
        // Kedua penanda ini statis. Penyuntikan sendiri berjalan pada
        // RequestHandled, setelah middleware selesai — jadi keduanya
        // dinolkan di sini dan tidak ada komponen Livewire yang menyalakannya
        // lagi sepanjang permintaan siswa.
        SupportAutoInjectedAssets::$forceAssetInjection = false;
        SupportAutoInjectedAssets::$hasRenderedAComponentThisRequest = false;

        return $next($request);
    }
}
