<?php

declare(strict_types=1);

namespace App\View\Components\Sun;

use App\Ads\AdSlotResolver;
use App\Ads\Dto\AdHeadDependency;
use App\Ads\Dto\AdResolution;
use App\Ads\Dto\RenderedAd;
use App\Ads\Enums\AdDevice;
use App\Ads\Support\AdConsent;
use App\Ads\Support\AdEditorMode;
use App\Ads\Support\AdHeadRegistry;
use App\Ads\Support\AdImpressionRecorder;
use App\Ads\Support\AdSlotCatalog;
use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyEntitlementService;
use App\View\Components\AdSlot as LegacyAdSlot;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use RuntimeException;

/**
 * Ein Werbeplatz-Anker (SUN-AD-004, Ticket #5).
 *
 *     <x-sun.ad-slot key="home_after_services" />
 *     <x-sun.ad-slot key="company_after_about" :subject="$company" />
 *
 * Heisst `<x-sun.ad-slot>` und nicht `<x-ad-slot>`, weil der kurze Name noch
 * dem Altsystem gehoert ({@see \App\View\Components\AdSlot}, Ansprache ueber
 * `position`); umbenannt wird in SUN-AD-011, wenn das Altsystem faellt
 * (docs/ad-slots.md 7).
 *
 * Reihenfolge der Arbeit, genau wie in docs/ad-slots.md 7:
 *
 *  1. Anker im Katalog nachsehen — unbekannter Schluessel ist in `local` ein
 *     Fehler, in der Produktion ein stummer Anker.
 *  2. Werbefreiheit pruefen: Request-Sperre des Altsystems (werbefreies
 *     Profil) und das Premium-Merkmal AdFree des Betriebs. Im Editor-Modus
 *     gilt beides nicht, dort zaehlt nur die Position.
 *  3. Resolver fragen — je Geraeteklasse getrennt, weil mobil und auf dem
 *     Desktop verschiedene Belegungen gewinnen koennen.
 *  4. Container mit Hoehenreservierung aus dem Katalog rendern, sobald eine
 *     **Belegung** existiert; auch dann, wenn das Werbemittel noch nicht
 *     geladen ist oder (ohne Einwilligung) noch gar nicht gerendert wird.
 *     Ohne Belegung entsteht nur der Anker-Kommentar, kein Container.
 *  5. Fragment in ein <template> legen. Eingesetzt wird es von
 *     resources/js/ads.js, sobald der Container sichtbar in den Viewport
 *     kommt (200 px Vorlauf) — damit laedt die mobile Fassung nie auf dem
 *     Desktop und umgekehrt, und unter dem Falz laedt nichts im Voraus.
 *
 * Zwei Container statt UA-Erkennung: Sichtbarkeit entscheidet der
 * Tailwind-Haltepunkt lg ({@see AdDevice::visibilityClass()}), dieselbe
 * Grenze, an der die Seitenspalten des Themes erscheinen. Loesen mobil und
 * Desktop dieselbe Belegung auf — der Normalfall —, wird daraus **ein**
 * Container ohne Sichtbarkeitsklasse.
 */
class AdSlot extends Component
{
    /** Anker im Markup, auch ohne Belegung — der Editor (#6) findet daran alle Positionen. */
    public const ANCHOR_PREFIX = 'ad-slot: ';

    /**
     * Cookie des Einwilligungs-Hinweises; Marketing deckt Werbung ab (#9).
     * Der Name steht in `config('ad-slots.consent.cookie')`; die Konstante
     * bleibt als Einstiegspunkt fuer Views und Tests.
     */
    public const CONSENT_COOKIE = 'cookie_consent_marketing';

    /**
     * Anker, die oberhalb des Falzes stehen und sofort laden: `sort = 10` und
     * jede Seitenspalte oben (docs/ad-slots.md 7, Punkt 5).
     */
    private const EAGER_SORT = 10;

    private const EAGER_SUFFIX = '_sidebar_top';

    /** @var array<string, mixed>|null */
    public ?array $slot;

    /**
     * Die zu rendernden Container, in Dokumentreihenfolge.
     *
     * @var list<array{device: string, visibility: string, minHeightMobile: int, minHeightDesktop: int, html: string|null, provider: string|null, label: string, placementId: int|null, adUnitName: string|null, needsConsent: bool, collapsed: bool}>
     */
    public array $containers = [];

    /** Skripte, die diese Seite noch nicht im <head> hat. @var list<AdHeadDependency> */
    public array $headScripts = [];

    /** Die gerenderten Werbemittel dieses Ankers. @var list<RenderedAd> */
    private array $ads = [];

    public bool $editorMode;

    public bool $eager;

    /**
     * Eigenes Impression-Zaehlen (#12). Nur dann traegt der Container seine
     * Belegungs-Id und die Messvorgabe; ohne diese Attribute richtet
     * resources/js/ads.js keinen Beobachter ein und es geht kein Beacon los.
     * Im Editor-Modus (#6) wird grundsaetzlich nicht gezaehlt — dort scrollt
     * Enes durch Anker-Rahmen, keine Besucher.
     */
    public bool $trackImpressions = false;

    /**
     * @param  string  $key  Katalog-Schluessel aus config/ad-slots.php
     * @param  mixed  $subject  Betrieb, dessen Premium-Merkmal AdFree gilt (Seitentyp `company`)
     * @param  string  $wrap  Klassen eines aeusseren Rahmens — Abstand und
     *                        Spaltenbreite der Seite. Er entsteht **nur**,
     *                        wenn der Anker belegt ist; ohne Belegung bleibt
     *                        kein leerer Container mit Abstand stehen.
     */
    public function __construct(
        public string $key,
        public mixed $subject = null,
        public string $wrap = '',
    ) {
        $this->slot = AdSlotCatalog::find($key);
        $this->editorMode = AdEditorMode::active();
        $this->trackImpressions = ! $this->editorMode && AdImpressionRecorder::enabled();

        if ($this->slot === null) {
            $this->failOnUnknownKey();

            return;
        }

        $this->eager = ((int) ($this->slot['sort'] ?? 0)) === self::EAGER_SORT
            || str_ends_with($key, self::EAGER_SUFFIX);

        if (! $this->editorMode && self::adFree($this->subject)) {
            return;
        }

        $this->containers = $this->buildContainers();
        $this->headScripts = $this->collectHeadScripts();
    }

    /**
     * Rendert immer: ohne Belegung bleibt nur der Anker-Kommentar stehen,
     * damit der Editor-Modus (#6) die Position findet, ohne dass ein
     * Container das Layout veraendert.
     */
    public function shouldRender(): bool
    {
        return true;
    }

    public function render(): View
    {
        return view('components.sun.ad-slot');
    }

    /**
     * Wird dieser Anker gerade belegt? Fuer Views, die ihre Umgebung nur
     * aufbauen, wenn etwas darin steht — etwa die Seitenspalte der Suche ohne
     * Karte oder der Schnitt des Ratgeber-Textes fuer `in_article`.
     */
    public static function isActive(string $slotKey, mixed $subject = null): bool
    {
        if (AdEditorMode::active()) {
            return AdSlotCatalog::exists($slotKey);
        }

        $slot = AdSlotCatalog::find($slotKey);

        if ($slot === null || self::adFree($subject)) {
            return false;
        }

        // Bewusst nur die Belegung, nicht das gerenderte Werbemittel: eine
        // Probe darf keine Head-Abhaengigkeit als "schon ausgegeben" vormerken
        // und den Loader damit der echten Komponente wegnehmen.
        $resolver = app(AdSlotResolver::class);
        $allowed = AdSlotCatalog::deviceOf($slotKey);

        foreach ([[AdDevice::MOBILE, 'min_height_mobile'], [AdDevice::DESKTOP, 'min_height_desktop']] as [$device, $heightKey]) {
            if (! $device->fitsInto($allowed) || (int) ($slot[$heightKey] ?? 0) <= 0) {
                continue;
            }

            if ($resolver->resolution($slotKey, null, $device) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wird dieses Head-Skript zurueckgehalten (#9)? Dann gibt die View es in
     * einem <template data-ad-head> aus: inert, kein Request. Eingesetzt wird
     * es von resources/js/ads.js, sobald die Einwilligung gemeldet ist.
     */
    public static function deferredUntilConsent(AdHeadDependency $dependency): bool
    {
        return $dependency->requiresConsent && ! AdConsent::granted();
    }

    /**
     * Messvorgabe fuer resources/js/ads.js (#12): Sichtbarkeitsanteil und
     * Verweildauer in Millisekunden, beides aus config/ad-slots.php. Steht im
     * Markup und nicht im JavaScript, damit eine Aenderung der Schwelle keinen
     * Neubau der Assets braucht.
     */
    public static function trackAttribute(): string
    {
        return ((float) config('ad-slots.impressions.threshold', 0.5))
            .':'.((int) config('ad-slots.impressions.dwell_ms', 1000));
    }

    /** Markierung fuer resources/js/ads.js: Politik bei Ablehnung (#9). */
    public static function onDenied(): string
    {
        return AdConsent::onDeniedAttribute();
    }

    /** Das <script>-Tag einer Head-Abhaengigkeit, Attribute in fester Reihenfolge. */
    public static function scriptTag(AdHeadDependency $dependency): string
    {
        $parts = [];

        foreach ($dependency->scriptAttributes() as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $parts[] = $value === true ? $name : $name.'="'.e((string) $value).'"';
        }

        return '<script '.implode(' ', $parts).'></script>';
    }

    /**
     * Ein Container je Geraeteklasse — oder einer fuer beide, wenn mobil und
     * Desktop dieselbe Belegung gewinnen.
     *
     * @return list<array{device: string, visibility: string, minHeightMobile: int, minHeightDesktop: int, html: string|null, provider: string|null, label: string, placementId: int|null, adUnitName: string|null, needsConsent: bool, collapsed: bool}>
     */
    private function buildContainers(): array
    {
        $resolver = app(AdSlotResolver::class);
        $allowed = AdSlotCatalog::deviceOf($this->key);
        $mobileHeight = (int) ($this->slot['min_height_mobile'] ?? 0);
        $desktopHeight = (int) ($this->slot['min_height_desktop'] ?? 0);

        // Hoehe 0 heisst im Katalog: auf diesem Geraet gibt es den Anker nicht.
        // Damit rendern die `*_sidebar_*`-Anker mobil nichts, und
        // `company_after_header_card` erscheint nur mobil.
        $mobile = $allowed !== AdDevice::DESKTOP && $mobileHeight > 0
            ? $this->resolveFor($resolver, AdDevice::MOBILE)
            : null;
        $desktop = $allowed !== AdDevice::MOBILE && $desktopHeight > 0
            ? $this->resolveFor($resolver, AdDevice::DESKTOP)
            : null;

        if ($mobile === null && $desktop === null) {
            return $this->editorMode ? [$this->container($allowed, $mobileHeight, $desktopHeight, null, null)] : [];
        }

        // Derselbe Anzeigenplatz auf beiden Geraeten: ein Container, keine
        // Sichtbarkeitsklasse, beide Mindesthoehen als CSS-Variablen.
        if ($mobile !== null && $desktop !== null && $this->samePlacement($mobile, $desktop)) {
            return [$this->container(AdDevice::ALL, $mobileHeight, $desktopHeight, $mobile['ad'], $mobile['resolution'])];
        }

        $containers = [];

        if ($mobile !== null) {
            $containers[] = $this->container(AdDevice::MOBILE, $mobileHeight, 0, $mobile['ad'], $mobile['resolution']);
        }

        if ($desktop !== null) {
            $containers[] = $this->container(AdDevice::DESKTOP, 0, $desktopHeight, $desktop['ad'], $desktop['resolution']);
        }

        return $containers;
    }

    /**
     * Belegung und — falls der Provider ausspielt — das Fragment.
     *
     * Entscheidend fuer den Container ist die **Belegung**, nicht das
     * Fragment: ohne Einwilligung rendert AdSense nichts, der Platz muss aber
     * schon reserviert sein, sonst springt das Layout nach der Einwilligung
     * (docs/ad-slots.md 7 und 9).
     *
     * @return array{resolution: AdResolution, ad: RenderedAd|null}|null
     */
    private function resolveFor(AdSlotResolver $resolver, AdDevice $device): ?array
    {
        $resolution = $resolver->resolution($this->key, null, $device, $this->editorMode);

        if ($resolution === null) {
            return null;
        }

        $context = $resolver->context(
            slotKey: $this->key,
            tenant: null,
            device: $device,
            consentGranted: $this->consentGranted(),
            editorMode: $this->editorMode,
            lazy: ! $this->eager,
        );

        return [
            'resolution' => $resolution,
            'ad' => $resolver->resolve($this->key, null, $device, $context),
        ];
    }

    /**
     * @param  array{resolution: AdResolution, ad: RenderedAd|null}  $mobile
     * @param  array{resolution: AdResolution, ad: RenderedAd|null}  $desktop
     */
    private function samePlacement(array $mobile, array $desktop): bool
    {
        return $mobile['resolution']->placementId === $desktop['resolution']->placementId;
    }

    /**
     * @return array{device: string, visibility: string, minHeightMobile: int, minHeightDesktop: int, html: string|null, provider: string|null, label: string, placementId: int|null, adUnitName: string|null, needsConsent: bool, collapsed: bool}
     */
    private function container(AdDevice $device, int $mobileHeight, int $desktopHeight, ?RenderedAd $ad, ?AdResolution $resolution): array
    {
        if ($ad instanceof RenderedAd) {
            $this->ads[] = $ad;
        }

        // Zurueckgehalten wird nur, was der Provider als einwilligungs-
        // pflichtig meldet (AdSense). Der Sandbox-Rahmen des HtmlProviders
        // laeuft ohne Cookie- und Speicherzugriff und darf sofort laden (#9).
        $needsConsent = $ad instanceof RenderedAd && $ad->requiresConsent && ! AdConsent::granted();

        return [
            'device' => $device->value,
            'visibility' => $device->visibilityClass(),
            'minHeightMobile' => $mobileHeight,
            'minHeightDesktop' => $desktopHeight,
            'html' => $ad?->html,
            'provider' => $ad?->providerKey === '' ? null : $ad?->providerKey,
            // Fragment liegt im <template> und wird erst nach der
            // Einwilligung eingesetzt (resources/js/ads.js).
            'needsConsent' => $needsConsent,
            // Ausdruecklich abgelehnt und `on_denied = collapse`: der Platz
            // klappt ein, statt dauerhaft leer zu reservieren.
            'collapsed' => $needsConsent && AdConsent::denied() && AdConsent::collapseWhenDenied(),
            // Beschriftung, Belegung und Werbemittel-Name wandern nur fuer den
            // Editor-Modus (#6) mit: der Rahmen beschriftet sich daraus, und
            // im Editor rendert kein Provider ein Werbemittel, aus dem man
            // den Namen sonst ablesen koennte. Das Label gehoert in den
            // Container und nicht in die View, weil `$slot` dort der
            // Blade-ComponentSlot ist und die Katalog-Zeile verdeckt.
            'label' => (string) ($this->slot['label'] ?? $this->key),
            'placementId' => $resolution?->placementId,
            'adUnitName' => $resolution?->adUnitName,
        ];
    }

    /**
     * Head-Abhaengigkeiten aller Container, entdoppelt gegen die ganze Seite:
     * sechs AdSense-Anker laden den Loader genau einmal.
     *
     * Das Fragment steckt im <template> und wird erst beim Heranscrollen
     * eingesetzt — der Loader gehoert trotzdem in den <head>, er laedt
     * asynchron und muss da sein, bevor ads.js push() ruft.
     *
     * @return list<AdHeadDependency>
     */
    private function collectHeadScripts(): array
    {
        $dependencies = [];

        foreach ($this->ads as $ad) {
            foreach ($ad->headDependencies as $dependency) {
                $dependencies[] = $dependency;
            }
        }

        if ($dependencies === []) {
            return [];
        }

        return app(AdHeadRegistry::class)->take($dependencies);
    }

    /**
     * Einwilligung in Marketing-Cookies. Die Politik steht seit #9 an einer
     * Stelle: {@see AdConsent} (Cookie-Name, Zustand, `on_denied`).
     *
     *
     * Das Cookie schreibt der Hinweis im Browser (`document.cookie`) und ist
     * darum nicht verschluesselt. Damit `request()->cookie()` es ueberhaupt
     * sieht, steht es in der Ausnahmeliste von EncryptCookies
     * (bootstrap/app.php, #21) — der frueher noetige Rueckfall auf das
     * Rohfeld der PHP-Umgebung ist damit weg: zwei Lesewege fuer denselben
     * Wert verdecken genau den Fehler, den die Ausnahme behebt.
     */
    private function consentGranted(): bool
    {
        return AdConsent::granted();
    }

    /**
     * Werbefreie Seite: die Request-Sperre des Altsystems (werbefreies
     * Profil) gilt fuer beide Generationen, dazu das Premium-Merkmal AdFree
     * des Betriebs. Letzteres stand bisher als `(at)unlesspremiumFeature` um
     * jeden Block in der View — es gehoert hierher, nicht in jede View
     * (docs/ad-slots.md 10).
     */
    private static function adFree(mixed $subject): bool
    {
        if (LegacyAdSlot::isSuppressed()) {
            return true;
        }

        $company = $subject instanceof Company ? $subject : null;

        if ($company === null) {
            return false;
        }

        return app(CompanyEntitlementService::class)->can($company, PremiumFeature::AdFree);
    }

    private function failOnUnknownKey(): void
    {
        $this->eager = false;

        if (app()->environment('local', 'testing')) {
            throw new RuntimeException("Unbekannter Werbeplatz-Anker: {$this->key}. Zulaessig sind die Schluessel aus config/ad-slots.php.");
        }
    }
}
