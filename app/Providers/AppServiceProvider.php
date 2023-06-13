<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
            // Include main array of sites and IDs on multisite installation
            require __DIR__.'/sitesList.php';
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }
}
