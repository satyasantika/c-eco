<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ExamGroupSlipController;
use App\Http\Controllers\Admin\SlipController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\ProctorQrController;
use App\Http\Controllers\StudentTestController;
use App\Http\Middleware\BindStudentSeat;
use App\Http\Middleware\RecordResponseMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\WithoutLivewireAssets;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'show'])->name('landing');
Route::post('masuk', [LandingController::class, 'enter'])->name('landing.enter');
Route::get('panduan', [ManualController::class, 'index'])->name('manual.index');
Route::get('panduan/{role}', [ManualController::class, 'show'])
    ->where('role', '[a-z]+')
    ->name('manual.show');

// Middleware auth bawaan Laravel mengarahkan tamu ke route('login'); di sini
// satu-satunya halaman masuk adalah milik panel Filament.
Route::get('login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

// Slip memuat token akses setiap peserta — jangan pernah dibuka tanpa login.
Route::get('admin/slip/{config}', SlipController::class)
    ->middleware('auth')
    ->name('admin.slips');

// Slip QR rombongan: boleh dicetak sebelum jadwal; siswa tetap tertahan sampai starts_at.
// Tanpa SecurityHeaders: CSP style-src 'self' memblokir gaya cetak inline (sama dengan slip lama).
Route::get('admin/rombongan/{examGroup}/slip', ExamGroupSlipController::class)
    ->middleware('auth')
    ->name('admin.group-slips');

Route::middleware(['auth', WithoutLivewireAssets::class, SecurityHeaders::class])->group(function (): void {
    Route::get('awas/{examGroup}', [ProctorQrController::class, 'show'])->name('proctor.qr');
    Route::get('awas/{examGroup}/berikut', [ProctorQrController::class, 'current'])
        ->middleware('throttle:proctor-qr')
        ->name('proctor.qr.current');
    Route::post('awas/{examGroup}/lanjut', [ProctorQrController::class, 'advance'])
        ->middleware('throttle:proctor-qr')
        ->name('proctor.qr.advance');
});

/*
| Alur siswa. Rate limit tetap dikunci ke token, bukan IP (aturan R2).
*/
Route::prefix('t/{token}')
    ->middleware([
        'throttle:cat-web',
        WithoutLivewireAssets::class,
        RecordResponseMetrics::class,
        SecurityHeaders::class,
        BindStudentSeat::class,
    ])
    ->group(function (): void {
        Route::get('/', [StudentTestController::class, 'show'])->name('student.show');
        Route::post('identitas', [StudentTestController::class, 'identify'])->name('student.identify');
        Route::post('mulai', [StudentTestController::class, 'consent'])->name('student.consent');
        Route::get('latihan', [StudentTestController::class, 'practice'])->name('student.practice');
        Route::post('tes', [StudentTestController::class, 'begin'])->name('student.test.begin');
        Route::post('jawab', [StudentTestController::class, 'answer'])->name('student.answer');
    });
