<?php

declare(strict_types=1);

namespace App\Ads\Providers;

use App\Ads\Dto\AdHeadDependency;
use App\Ads\Dto\AdRenderContext;
use App\Ads\Dto\RenderedAd;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;

/**
 * Google AdSense (docs/ad-slots.md 5.1, Ticket #4).
 *
 * Rendert den <ins class="adsbygoogle">-Block und meldet den Loader
 * `adsbygoogle.js` als Head-Abhaengigkeit an — einmal pro Seite, nicht pro
 * Anker; die Komponente (#5) wirft Doppelte anhand des `handle` weg.
 *
 * Harte Regeln:
 *
 * * `data-ad-client` kommt **immer** aus den Einstellungen des Portals
 *   (ad_settings.adsense_publisher_id, sonst die Netz-Vorgabe aus
 *   config('ad-slots.adsense.publisher_id')) — nie aus dem Werbemittel. Ohne
 *   Publisher-ID wird nicht ausgespielt ({@see supports()} => false), der
 *   Anker bleibt leer.
 * * Im Editor-Modus wird nichts gerendert: eine Impression auf einer
 *   Vorschauseite ist bei Google ein ungueltiger Vorfall (docs 6).
 * * Ohne Einwilligung wird das Fragment gerendert, aber als
 *   `requiresConsent` gekennzeichnet: die Komponente laesst es im <template>
 *   liegen und haelt auch den Loader zurueck, resources/js/ads.js setzt beides
 *   erst nach der Einwilligung ein. Vorher geht kein Request an Google — ein
 *   <template> ist inert (SUN-AD-009, Ticket #9, docs/ad-slots.md 9). Den
 *   Platzhalter in Katalog-Hoehe setzt die Komponente.
 * * Kein Inline-Skript: `adsbygoogle.push({})` ruft das Modul aus #5 an den
 *   data-Attributen auf — `script-src` hat kein 'unsafe-inline' (docs 9).
 */
class AdSenseProvider extends AbstractAdProvider
{
    /** Loader-Skript; `handle` ist der Schluessel der Entdoppelung. */
    public const LOADER_HANDLE = 'adsbygoogle';

    private const LOADER_URL = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js';

    /**
     * Formate, deren Hoehe AdSense bestimmen darf: `fluid`-Varianten fuer
     * Listen und Textfluss. Alle anderen bekommen eine feste Hoehe im
     * `style` — entweder aus dem Format oder aus der Reservierung des
     * Ankers (#32).
     */
    private const FLUID_FORMATS = ['in_feed', 'in_article'];

    public function key(): string
    {
        return AdProviderType::ADSENSE->value;
    }

    public function label(): string
    {
        return __('Google AdSense');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function configSchema(): array
    {
        return [
            [
                'name' => 'slot_id',
                'label' => __('Anzeigenblock-ID (data-ad-slot)'),
                'type' => 'text',
                'required' => true,
                'help' => __('Nur die Ziffern aus dem AdSense-Code, ohne ca-pub-Teil.'),
                'rules' => ['required', 'string', 'regex:/^\d{6,20}$/'],
                'messages' => [
                    'required' => __('Die AdSense-Anzeigenblock-ID (data-ad-slot) fehlt.'),
                    'regex' => __('Die AdSense-Anzeigenblock-ID besteht nur aus Ziffern.'),
                ],
            ],
            [
                'name' => 'layout_key',
                'label' => __('Layout-Schluessel (data-ad-layout-key)'),
                'type' => 'text',
                'required' => false,
                'help' => __('Nur bei In-Feed-Bloecken; steht im AdSense-Code.'),
                'rules' => ['sometimes', 'nullable', 'string', 'max:255'],
            ],
            $this->formatField(),
        ];
    }

    /** AdSense setzt Cookies und spricht fremde Hosts an. */
    public function requiresConsent(AdUnit $unit): bool
    {
        return true;
    }

    public function supports(AdUnit $unit, AdRenderContext $context): bool
    {
        if ($context->editorMode) {
            return false;
        }

        if ($this->publisherId($context) === null) {
            return false;
        }

        return $unit->adsenseSlotId() !== null && parent::supports($unit, $context);
    }

    /**
     * Der Einwilligungsstand aus dem Kontext steht hier bewusst NICHT mehr
     * als Abbruchbedingung (#9): das Gate ist in die Komponente und nach
     * resources/js/ads.js gewandert, damit eine Einwilligung im laufenden
     * Besuch ohne Reload greift. Gerendert wird also immer — ausgespielt
     * erst, wenn `requiresConsent` erfuellt ist.
     */
    public function render(AdUnit $unit, AdRenderContext $context): ?RenderedAd
    {
        if (! $this->supports($unit, $context)) {
            return null;
        }

        $publisherId = $this->publisherId($context);
        $format = $unit->format();

        $attributes = [
            'class' => 'adsbygoogle block w-full',
            'style' => $this->style($format, $context),
            'data-ad-client' => $publisherId,
            'data-ad-slot' => $unit->adsenseSlotId(),
            // Das Modul aus #5 findet die Bloecke hierueber und ruft push().
            'data-ads-push' => 'true',
        ] + $this->formatAttributes($format, $unit);

        $html = '<ins '.$this->attributes($attributes).'></ins>';

        return new RenderedAd(
            html: $html,
            headDependencies: [$this->loader($publisherId)],
            providerKey: $this->key(),
            adUnitId: $unit->getKey() === null ? null : (int) $unit->getKey(),
            meta: ['format' => $format, 'publisher_id' => $publisherId],
            requiresConsent: $this->requiresConsent($unit),
        );
    }

    /**
     * Format => AdSense-Attribute (docs/ad-slots.md 5.1).
     *
     * `responsive` bekommt **kein** `data-ad-format="auto"` mehr (#32): mit
     * `auto` bestimmt AdSense die Hoehe selbst (gemessen 250 px) und sprengt
     * die Reservierung eines Banner-Ankers — CLS 0,136 auf
     * `home_after_hero` (docs/messungen/ads-einwilligung-2026-10-16.md 4).
     * Ohne Format-Angabe gilt die CSS-Groesse des `<ins>`: die Breite der
     * Spalte und die Hoehe aus dem Katalog ({@see style()}) — der Anker gibt
     * die Hoehe vor, das Werbemittel fuellt sie. `data-full-width-responsive`
     * gehoert dann nicht dazu, es betrifft nur `auto`.
     *
     * @return array<string, string|null>
     */
    private function formatAttributes(string $format, AdUnit $unit): array
    {
        return match ($format) {
            'in_feed' => [
                'data-ad-format' => 'fluid',
                'data-ad-layout-key' => $unit->adsenseLayoutKey(),
            ],
            'in_article' => [
                'data-ad-format' => 'fluid',
                'data-ad-layout' => 'in-article',
            ],
            // Feste Groessen und `responsive` bekommen keine Format-Angabe,
            // nur die Groesse im style-Attribut.
            default => [],
        };
    }

    /**
     * Feste Groessen stehen **innerhalb** des reservierten Containers; die
     * Reservierung selbst setzt die Komponente aus dem Katalog.
     *
     * `responsive` hat keine Groesse im Katalog und bekommt darum die Hoehe
     * der Reservierung (#32): voll breit, Hoehe fest. Fluide Formate
     * (`in_feed`, `in_article`) behalten ihre freie Hoehe — sie laufen im
     * Textfluss bzw. in Listen und stehen nur auf Ankern mit 250/280 px
     * Reservierung.
     */
    private function style(string $format, AdRenderContext $context): string
    {
        $sizes = (array) config("ad-slots.formats.{$format}", []);
        $width = $sizes['width'] ?? null;
        $height = $sizes['height'] ?? null;

        if ($width !== null && $height !== null) {
            return "display:block;width:{$width}px;height:{$height}px;margin-inline:auto";
        }

        if (in_array($format, self::FLUID_FORMATS, true)) {
            return 'display:block';
        }

        $reserved = $this->reservedHeight($context);

        return $reserved === null
            ? 'display:block'
            : "display:block;width:100%;height:{$reserved}px";
    }

    /**
     * Hoehe, die der Anker laut Katalog reserviert — die kleinere der beiden
     * Geraetehoehen, wenn der Container fuer mobil **und** Desktop gilt
     * (gleiche Belegung auf beiden Geraeten, {@see \App\View\Components\Sun\AdSlot}):
     * das Werbemittel wird einmal gerendert und darf keine der beiden
     * Reservierungen ueberschreiten. Hoehe 0 heisst im Katalog "auf diesem
     * Geraet gibt es den Anker nicht" und zaehlt darum nicht mit.
     */
    private function reservedHeight(AdRenderContext $context): ?int
    {
        $heights = array_filter(
            [$context->minHeightMobile, $context->minHeightDesktop],
            static fn (int $height): bool => $height > 0,
        );

        return $heights === [] ? null : min($heights);
    }

    private function loader(string $publisherId): AdHeadDependency
    {
        return new AdHeadDependency(
            handle: self::LOADER_HANDLE,
            src: self::LOADER_URL.'?client='.urlencode($publisherId),
            attributes: ['async' => true, 'crossorigin' => 'anonymous'],
            requiresConsent: true,
        );
    }

    /**
     * Publisher-ID des Portals. Der Resolver legt sie in den Kontext
     * (AdSetting::adsensePublisherId() mit Netz-Vorgabe als Rueckfall), der
     * Provider fragt den Tenant nie selbst.
     */
    private function publisherId(AdRenderContext $context): ?string
    {
        $id = trim((string) $context->adsensePublisherId);

        return $id === '' ? null : $id;
    }
}
