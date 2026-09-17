<?php

namespace App\Providers;

use App\Listeners\Mail\SetPortalSender;
use App\Services\PaymentProviders\LemonSqueezy\LemonSqueezyProvider;
use App\Services\PaymentProviders\Offline\OfflineProvider;
use App\Services\PaymentProviders\Paddle\PaddleProvider;
use App\Services\PaymentProviders\PaymentService;
use App\Services\PaymentProviders\Stripe\StripeProvider;
use App\Services\UserVerificationService;
use App\Services\VerificationProviders\TwilioProvider;
use App\Support\Translation\TenantOverrideLoader;
use App\Support\Translation\TenantTranslator;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator;
use Jeffgreco13\FilamentBreezy\Livewire\PersonalInfo;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        // Tenant-Overrides aus tenant_texts ueber die Sprachdateien legen (#4).
        // extend statt singleton: der deferred TranslationServiceProvider
        // wuerde eine eigene Bindung beim ersten Aufloesen ueberschreiben.
        // lang/ zusaetzlich zu resources/lang laden: Laravel nimmt nur einen
        // Sprachpfad, und resources/lang existiert — lang/de/portal.php (#7)
        // waere sonst nie sichtbar.
        $this->app->extend('translation.loader', function (Loader $loader) {
            if ($loader instanceof FileLoader && ! in_array(base_path('lang'), $loader->paths(), true)) {
                $loader->addPath(base_path('lang'));
            }

            return new TenantOverrideLoader($loader);
        });

        // Branchenbegriffe automatisch in alle Texte einsetzen (#5).
        $this->app->extend('translator', function (Translator $translator) {
            $tenantTranslator = new TenantTranslator($translator->getLoader(), $translator->getLocale());
            $tenantTranslator->setFallback($translator->getFallback());

            return $tenantTranslator;
        });

        // Robots/Canonical je Anfrage (#7)
        $this->app->scoped(\App\Services\Seo\SeoService::class);

        // payment providers
        $this->app->tag([
            StripeProvider::class,
            PaddleProvider::class,
            LemonSqueezyProvider::class,
            OfflineProvider::class,
        ], 'payment-providers');

        $this->app->bind(PaymentService::class, function () {
            return new PaymentService(...$this->app->tagged('payment-providers'));
        });

        // verification providers
        $this->app->tag([
            TwilioProvider::class,
        ], 'verification-providers');

        $this->app->afterResolving(UserVerificationService::class, function (UserVerificationService $service) {
            $service->setVerificationProviders(...$this->app->tagged('verification-providers'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Absendername jeder Mail ist der Portalname, nicht MAIL_FROM_NAME
        Event::listen(MessageSending::class, SetPortalSender::class);

        FilamentAsset::register([
            Js::make('components-script', __DIR__.'/../../resources/js/components.js'),
        ]);

        // temporarily fix until this is fixed in filament breezy: https://github.com/Jacobtims/filament-breezy/issues/496
        Livewire::component('personal_info', PersonalInfo::class);
        Livewire::component('update_password', UpdatePassword::class);

        // Register Livewire update endpoint with tenancy + theme middleware
        // so that Livewire AJAX requests resolve the correct tenant DB and theme views
        Livewire::setUpdateRoute(function ($handle) {
            return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                ->middleware([
                    'web',
                    'universal',
                    TenancyServiceProvider::TENANCY_INITIALIZER,
                    \App\Http\Middleware\ResolveTheme::class,
                ]);
        });
    }
}
