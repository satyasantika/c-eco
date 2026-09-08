<?php

declare(strict_types=1);

use App\Http\Controllers\Api\SessionController;
use Illuminate\Support\Facades\Route;

/*
| Kontrak API sisi siswa, SPEC §7.
|
| Rate limit dikunci ke {token}, bukan ke alamat IP: 300 siswa pada satu
| operator seluler keluar lewat segelintir IP publik karena CGNAT, sehingga
| limiter berbasis IP akan memblokir satu kelas penuh (aturan R2).
*/

Route::prefix('t/{token}')->group(function (): void {
    Route::post('start', [SessionController::class, 'start'])->middleware('throttle:cat-start');
    Route::post('answer', [SessionController::class, 'answer'])->middleware('throttle:cat-answer');
    Route::post('event', [SessionController::class, 'event'])->middleware('throttle:cat-event');
    Route::get('state', [SessionController::class, 'state'])->middleware('throttle:cat-state');
});
