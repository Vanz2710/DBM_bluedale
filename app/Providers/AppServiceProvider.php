<?php

namespace App\Providers;

use App\Models\Deal;
use App\Observers\DealObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // super-admin bypasses all permission checks
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('super-admin')) {
                return true;
            }
        });

        Deal::observe(DealObserver::class);
    }
}
