<?php

namespace App\Providers;

use App\Services\VaultCrypto;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VaultCrypto::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a TLS-terminating proxy in production, force generated URLs to
        // https so assets/links aren't emitted as http (mixed content).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
