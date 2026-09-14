<?php

namespace App\Providers;

use App\Support\ApplicationPrefix;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $prefix = ApplicationPrefix::forConsole();
        if ($prefix === '') {
            return;
        }

        $root = rtrim((string) config('app.url'), '/');
        URL::useOrigin($root);
        URL::useAssetOrigin($root);
    }
}
