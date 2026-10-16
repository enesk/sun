<?php

declare(strict_types=1);

namespace App\Ads;

use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdSetting;
use App\Ads\Models\AdUnit;
use App\Ads\Observers\AdCacheInvalidator;
use App\Ads\Support\AdHeadRegistry;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Bindet das Werbeplatz-System in den Container (SUN-AD-003, Ticket #4).
 *
 * Absichtlich klein: Registry, Resolver, die Entdoppelung der
 * Head-Abhaengigkeiten (#5) und die Cache-Observer. Der Editor kommt in #6,
 * die Filament-Seiten in #7/#8/#10 — jeweils an ihrer Stelle, nicht hier.
 *
 * Der Resolver ist `scoped` und nicht `singleton`: er haelt die aufgeloeste
 * Karte fuer die Dauer der Anfrage, und in einem Queue- oder Octane-Worker
 * laeuft ein Portal nach dem anderen durch denselben Prozess. Zusaetzlich
 * fliegt die Karte beim Portalwechsel raus.
 */
class AdsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdProviderRegistry::class);
        $this->app->scoped(AdSlotResolver::class);

        // Merkliste der schon ausgegebenen <head>-Skripte: gilt fuer eine
        // Anfrage, sonst faehrt die zweite Seite im Worker ohne ihren Loader.
        $this->app->scoped(AdHeadRegistry::class);
    }

    public function boot(): void
    {
        AdUnit::observe(AdCacheInvalidator::class);
        AdPlacement::observe(AdCacheInvalidator::class);
        AdSetting::observe(AdCacheInvalidator::class);

        $this->app['events']->listen(
            [TenancyInitialized::class, TenancyEnded::class],
            function (): void {
                if ($this->app->resolved(AdSlotResolver::class)) {
                    $this->app->make(AdSlotResolver::class)->flushMemo();
                }

                if ($this->app->resolved(AdHeadRegistry::class)) {
                    $this->app->make(AdHeadRegistry::class)->flush();
                }
            },
        );
    }
}
