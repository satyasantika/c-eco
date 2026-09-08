<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\SlipController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\StudentTestController;
use App\Http\Middleware\RecordResponseMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WithoutLivewireAssets;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'show'])->name('landing');
Route::post('masuk', [LandingController::class, 'enter'])->name('landing.enter');

// Middleware auth bawaan Laravel mengarahkan tamu ke route('login'); di sini
// satu-satunya halaman masuk adalah milik panel Filament.
Route::get('login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

// Slip memuat token akses setiap peserta — jangan pernah dibuka tanpa login.
Route::get('admin/slip/{config}', SlipController::class)
    ->middleware('auth')
    ->name('admin.slips');

/*
| Alur siswa. Rate limit tetap dikunci ke token, bukan IP (aturan R2).
*/
Route::prefix('t/{token}')
    ->middleware([
        'throttle:cat-web',
        WithoutLivewireAssets::class,
        RecordResponseMetrics::class,
        SecurityHeaders::class,
    ])
    ->group(function (): void {
        Route::get('/', [StudentTestController::class, 'show'])->name('student.show');
        Route::post('mulai', [StudentTestController::class, 'consent'])->name('student.consent');
        Route::get('latihan', [StudentTestController::class, 'practice'])->name('student.practice');
        Route::post('tes', [StudentTestController::class, 'begin'])->name('student.test.begin');
        Route::post('jawab', [StudentTestController::class, 'answer'])->name('student.answer');
    });
