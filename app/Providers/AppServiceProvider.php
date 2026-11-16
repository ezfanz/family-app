<?php

namespace App\Providers;

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
        // Every link, redirect and asset URL is https in production (the PWA, camera and
        // secure cookies all need it), even if the proxy talks to PHP over plain http.
        URL::forceHttps($this->app->isProduction());
    }
}
