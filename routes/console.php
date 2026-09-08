<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Digerbangi flag supaya server pengembangan tidak menumpuk dump, dan supaya
// operator bisa menyalakannya persis pada hari pengambilan data.
if (config('cat.backup.enabled')) {
    Schedule::command('cat:backup', ['--keep' => config('cat.backup.keep')])
        ->everyFifteenMinutes()
        ->withoutOverlapping()
        ->onOneServer()
        ->runInBackground();
}
