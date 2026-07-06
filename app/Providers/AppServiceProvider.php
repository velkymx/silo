<?php

namespace App\Providers;

use App\Automation\Actions\ActionRegistry;
use App\Automation\AutomationDispatcher;
use App\Automation\EventDispatcher;
use App\Automation\Events\AutomationEventRegistry;
use App\Automation\Resolvers\EventContextResolver;
use App\Automation\Resolvers\RssEventContextResolver;
use App\Automation\Subscribers\SubscriberRegistry;
use App\Services\VaultCrypto;
use App\Workflow\Expression\ConditionEvaluator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The engine itself is engine-only wiring. Module registrations
     * (event types, subscribers) live in their own providers
     * (RssServiceProvider today, Files/Calendar/Photos later).
     */
    public function register(): void
    {
        $this->app->singleton(VaultCrypto::class);

        $this->app->singleton(AutomationEventRegistry::class);
        $this->app->singleton(SubscriberRegistry::class);
        $this->app->singleton(ActionRegistry::class);
        $this->app->singleton(ConditionEvaluator::class);

        $this->app->bind(EventContextResolver::class, RssEventContextResolver::class);

        $this->app->singleton(EventDispatcher::class, AutomationDispatcher::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
