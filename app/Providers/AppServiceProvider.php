<?php

namespace App\Providers;

use App\Models\Deal;
use App\Observers\DealObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // super-admin and admin bypass all permission checks
        Gate::before(function ($user, $ability) {
            $roleNames = $user->roles->pluck('name');
            if ($roleNames->contains('super-admin') || $roleNames->contains('admin')) {
                return true;
            }
        });

        Deal::observe(DealObserver::class);
    }
}
