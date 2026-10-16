<?php

declare(strict_types=1);

namespace App\View\Components\Sun;

use App\Ads\AdSlotResolver;
use App\Ads\Dto\AdHeadDependency;
use App\Ads\Enums\AdPageType;
use App\Ads\LegacyAdTakeover;
use App\Ads\Providers\AdSenseProvider;
use App\Ads\Support\AdConsent;
use App\Ads\Support\AdEditorMode;
use App\Ads\Support\AdHeadRegistry;
use App\View\Components\AdSlot as LegacyAdSlot;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * AdSense Auto Ads eines Portals (Ticket #18).
 *
 *     <x-sun.auto-ads />   {{-- im <head>, nach @stack('head') --}}
 *
 * Auto Ads sind **kein** Anker im Katalog: das Skript platziert selbst,
 * ausserhalb jeder Reservierung (docs/ad-slots.md 1). Eingeschaltet werden
 * sie je Portal in `ad_settings.adsense_auto_ads` — zusammen mit dem
 * Hauptschalter `ads_enabled` und einer wirksamen Publisher-ID.
 *
 * Ausgegeben wird genau das, was das Altsystem ausgegeben hat:
 *
 *  1. der Loader `adsbygoogle.js?client=ca-pub-…` — derselbe, den der
 *     {@see AdSenseProvider} als Head-Abhaengigkeit anmeldet. Entdoppelt wird
 *     ueber den {@see AdHeadRegistry} am Schluessel
 *     {@see AdSenseProvider::LOADER_HANDLE}: steht der Loader wegen eines
 *     Anzeigenblocks schon im `head`-Stack, schreibt diese Komponente ihn
 *     nicht noch einmal.
 *  2. `<meta name="adsense-auto-client">` — daran erkennt
 *     resources/js/ads.js die Publisher-ID und ruft `enable_page_level_ads`
 *     mit `overlays: { bottom: false }`. Der Opt-out des unteren Banners
 *     bleibt Pflicht: er setzt ein padding-bottom auf das <body>
 *     (docs/messungen/auto-ads-cls-messung-2026-09-09.md).
 *
 * Die Komponente steht im Layout **nach** `@stack('head')`, weil der Stack
 * beim Rendern des Layouts bereits ausgegeben ist: der Seiten-View laeuft
 * vorher, seine Anker haben ihre Skripte also schon vorgemerkt. Darum gibt
 * diese Komponente das <script> an ihrer Stelle aus und nicht per `@push`.
 *
 * Nichts wird ausgespielt, wenn
 * * die Seite werbefrei ist (Request-Sperre des Altsystems, werbefreies Profil),
 * * die Route in `config('ad-slots.adsense.auto_ads.blocked_routes')` steht,
 * * der Editor-Modus laeuft — eine Impression auf einer Vorschauseite ist bei
 *   Google ein ungueltiger Vorfall (docs/ad-slots.md 6),
 * * noch keine Einwilligung in Marketing-Cookies vorliegt (#9) — und zwar
 *   schon beim Seitenaufbau; nachgezogen wird hier bewusst nicht,
 * * oder das Altsystem die Position `auto_ads` in diesem Portal noch selbst
 *   ausliefert. Letzteres haelt die Parallelphase bis #11 sauber: solange der
 *   alte Platz aktiv ist, bleibt es bei einem Loader. Mit dem Abschalten des
 *   Altsystems uebernimmt diese Komponente ohne weiteren Eingriff.
 */
class AutoAds extends Component
{
    /** Publisher-ID fuer das <meta>-Tag; null heisst: nichts rendern. */
    public ?string $publisherId = null;

    /** Der Loader, falls er auf dieser Seite noch nicht ausgegeben wurde. */
    public ?AdHeadDependency $loader = null;

    public function __construct()
    {
        if (! $this->allowedHere()) {
            return;
        }

        $resolver = app(AdSlotResolver::class);

        if (! $resolver->autoAdsEnabled()) {
            return;
        }

        $publisherId = trim((string) ($resolver->forPageType(AdPageType::HOME)['publisher_id'] ?? ''));

        if ($publisherId === '') {
            return;
        }

        $this->publisherId = $publisherId;
        $this->loader = app(AdHeadRegistry::class)->take([
            new AdHeadDependency(
                handle: AdSenseProvider::LOADER_HANDLE,
                src: 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client='.urlencode($publisherId),
                attributes: ['async' => true, 'crossorigin' => 'anonymous'],
                requiresConsent: true,
            ),
        ])[0] ?? null;
    }

    public function shouldRender(): bool
    {
        return $this->publisherId !== null;
    }

    public function render(): View
    {
        return view('components.sun.auto-ads');
    }

    /** Das <script>-Tag des Loaders; dieselbe Ausgabe wie bei den Ankern. */
    public function scriptTag(): string
    {
        return $this->loader === null ? '' : AdSlot::scriptTag($this->loader);
    }

    /**
     * Darf diese Anfrage Auto Ads ausliefern? Werbefreiheit, gesperrte
     * Routen, Editor-Modus, Einwilligung — und das Altsystem.
     */
    private function allowedHere(): bool
    {
        if (AdEditorMode::active() || LegacyAdSlot::isSuppressed()) {
            return false;
        }

        if (! $this->consentGranted()) {
            return false;
        }

        if (LegacyAdSlot::hasSlotsForPosition(LegacyAdTakeover::AUTO_ADS_POSITION)) {
            return false;
        }

        $routeName = request()->route()?->getName();

        if ($routeName === null) {
            return true;
        }

        return ! in_array($routeName, self::blockedRoutes(), true);
    }

    /**
     * Routen ohne Auto Ads (Vorgabe #100).
     *
     * @return list<string>
     */
    public static function blockedRoutes(): array
    {
        return array_values(array_map(
            strval(...),
            (array) config('ad-slots.adsense.auto_ads.blocked_routes', []),
        ));
    }

    /**
     * Einwilligung in Marketing-Cookies; dieselbe Politik wie die Anker
     * ({@see AdConsent}).
     *
     * Anders als bei den Ankern wird hier NICHT im laufenden Besuch
     * nachgezogen: Auto Ads platzieren ausserhalb jeder Reservierung, eine
     * Einblendung nach dem Klick auf "Alle akzeptieren" wuerde das Layout
     * verschieben (CLS). Liegt die Einwilligung beim Seitenaufbau nicht vor,
     * laufen Auto Ads erst beim naechsten Seitenaufruf (#9).
     */
    private function consentGranted(): bool
    {
        return AdConsent::granted();
    }
}
