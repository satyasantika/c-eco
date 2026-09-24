<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ApplicationPrefix;
use Illuminate\Support\Facades\Gate;
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
        // Buat/hapus simulasi demo: hanya admin. Dipakai tombol panel.
        Gate::define('manage-simulation', fn (User $user): bool => $user->canManageExamSimulations());

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
