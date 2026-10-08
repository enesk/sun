<?php

declare(strict_types=1);

namespace App\Turnstile;

use App\AntiSpam\Support\AntiSpamConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Services\TurnstileVerifier;
use App\Turnstile\Support\CircuitBreaker;
use App\Turnstile\Support\TurnstileLogger;
use App\Turnstile\Support\TurnstileRegisterValidator;
use App\Validator\RegisterValidator;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Bindet das Turnstile-Modul in den Container (#4).
 *
 * Absichtlich klein: hier stehen nur Bindungen und der Cache-Reset beim
 * Portalwechsel. Die Blade-Komponente kommt in #5, der Admin in #9, das
 * Pruning in #12 — alles jeweils an seiner Stelle, nicht hier.
 *
 * TurnstileVerifier ist als `scoped` gebunden, nicht als `singleton`: in einem
 * Queue- oder Octane-Worker laeuft ein Prozess durch viele Anfragen, und der
 * Circuit Breaker darf seinen Cache-Store nicht ueber einen Portalwechsel
 * hinweg festhalten.
 */
class TurnstileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CircuitBreaker::class, fn (): CircuitBreaker => new CircuitBreaker);
        $this->app->scoped(TurnstileLogger::class);
        $this->app->scoped(TurnstileVerifier::class);

        // Registrierung (#6): jede Kontoanlage laeuft bei SaaSykit durch den
        // RegisterValidator. Die Unterklasse haengt dort die TurnstileRule an,
        // damit kein Aufrufer sie vergessen kann.
        $this->app->bind(RegisterValidator::class, TurnstileRegisterValidator::class);
    }

    public function boot(): void
    {
        // Der Resolver cacht je Request in einer statischen Map. In einem
        // langlebigen Prozess (Horizon, tenants:run) laeuft ein Portal nach dem
        // anderen durch denselben Prozess — dann muss die Map fallen.
        $this->app['events']->listen([TenancyInitialized::class, TenancyEnded::class], function (): void {
            TurnstileConfigResolver::flush();
            // Dieselbe Begruendung fuer die Tiefenverteidigung (#8): sie liest
            // ihre Grenzen und Sperrlisten je Portal aus tenants.data.
            AntiSpamConfig::flush();
        });
    }
}
