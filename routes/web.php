<?php

declare(strict_types=1);

use App\Http\Controllers\StudentTestController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));

/*
| Alur siswa. Rate limit tetap dikunci ke token, bukan IP (aturan R2).
*/
Route::prefix('t/{token}')->middleware('throttle:cat-web')->group(function (): void {
    Route::get('/', [StudentTestController::class, 'show'])->name('student.show');
    Route::post('mulai', [StudentTestController::class, 'consent'])->name('student.consent');
    Route::get('latihan', [StudentTestController::class, 'practice'])->name('student.practice');
    Route::post('tes', [StudentTestController::class, 'begin'])->name('student.test.begin');
    Route::post('jawab', [StudentTestController::class, 'answer'])->name('student.answer');
});
