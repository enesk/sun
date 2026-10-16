<?php

declare(strict_types=1);

namespace App\Ads\Providers;

use App\Ads\Dto\AdRenderContext;
use App\Ads\Dto\RenderedAd;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;

/**
 * Eigenes HTML aus der Verwaltung (docs/ad-slots.md 5.2, Ticket #4).
 *
 * Das Markup ist **nicht vertrauenswuerdig**: wer es eintraegt, soll keine
 * Skripte in den Portal-Origin bekommen — kein Zugriff auf Session-Cookies,
 * kein Lesen des DOM, keine Formular-Uebernahme. Deshalb:
 *
 * 1. Das HTML landet **nie** im Seiten-DOM. Ausgegeben wird ausschliesslich
 *    ein <iframe sandbox>; das unterscheidet diesen Provider vom Altsystem,
 *    das den Code mit CSP-Nonce direkt eingefuegt hat.
 * 2. `sandbox` **ohne** `allow-same-origin` — der Rahmen ist damit ein opaker
 *    Origin. `allow-scripts` und `allow-same-origin` zusammen waeren
 *    gleichbedeutend mit "keine Sandbox" und sind verboten.
 * 3. Zustellung ueber die eigene Route `/_ads/html/{adUnit}`
 *    ({@see \App\Ads\Http\Controllers\AdHtmlController}) statt ueber `srcdoc`:
 *    `srcdoc` erbt die CSP des Elterndokuments und wuerde das Nonce-Modell
 *    aus config/csp.php unterwandern. Die Route antwortet mit eigener CSP
 *    (`sandbox`, `frame-ancestors 'self'`).
 * 4. Feste Groesse aus der Konfiguration, keine Hoehenverhandlung per
 *    postMessage — eine nachtraegliche Hoehenaenderung ist genau der
 *    Layout-Shift, den das System vermeiden soll. `width`/`height` sind
 *    deshalb Pflichtfelder.
 * 5. Keine Filterung des HTML beim Speichern: Sanitizing erzeugt nur das
 *    falsche Gefuehl von Sicherheit und bricht legitime Werbemittel. Die
 *    Sandbox ist die Grenze, das Anlegen von ad_units ist auf die
 *    Admin-Rolle beschraenkt (SUN-AD-010).
 */
class HtmlProvider extends AbstractAdProvider
{
    /** Obergrenze fuer eigenes Markup: 20 kB (Ticket #3). */
    public const MAX_BYTES = 20480;

    /** Erlaubte Sandbox-Rechte — bewusst ohne allow-same-origin. */
    public const SANDBOX = 'allow-scripts allow-popups allow-popups-to-escape-sandbox';

    public const ROUTE = 'portal.ads.html';

    public function key(): string
    {
        return AdProviderType::HTML->value;
    }

    public function label(): string
    {
        return __('Eigenes HTML');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function configSchema(): array
    {
        return [
            [
                'name' => 'code',
                'label' => __('HTML-Code des Werbemittels'),
                'type' => 'textarea',
                'required' => true,
                'help' => __('Laeuft in einem abgeschotteten Rahmen und kann die Portalseite nicht erreichen. Hoechstens 20 kB.'),
                'rules' => ['required', 'string', $this->byteLimit()],
                'messages' => ['required' => __('Das Werbemittel braucht eigenes HTML.')],
            ],
            [
                'name' => 'width',
                'label' => __('Breite in Pixel'),
                'type' => 'number',
                'required' => true,
                'help' => __('Feste Breite des Rahmens; er wird auf schmalen Bildschirmen nur verkleinert, nie umgebrochen.'),
                'rules' => ['required', 'integer', 'min:1', 'max:4000'],
                'messages' => ['required' => __('Eigenes HTML braucht eine feste Breite.')],
            ],
            [
                'name' => 'height',
                'label' => __('Hoehe in Pixel'),
                'type' => 'number',
                'required' => true,
                'help' => __('Feste Hoehe des Rahmens. Sie darf sich spaeter nicht aendern — sonst springt das Layout.'),
                'rules' => ['required', 'integer', 'min:1', 'max:4000'],
                'messages' => ['required' => __('Eigenes HTML braucht eine feste Hoehe.')],
            ],
            $this->formatField(),
        ];
    }

    /**
     * Eigenes HTML laeuft im Sandbox-iframe ohne Cookie- und
     * Speicherzugriff; eine Einwilligung braucht es dafuer nicht.
     */
    public function requiresConsent(AdUnit $unit): bool
    {
        return false;
    }

    public function supports(AdUnit $unit, AdRenderContext $context): bool
    {
        // Ohne Primaerschluessel gibt es keine Zustell-URL — und ohne die
        // wuerde nur der Weg ueber srcdoc bleiben, der ausgeschlossen ist.
        if ($unit->getKey() === null) {
            return false;
        }

        return $unit->html() !== null
            && $this->dimension($unit, 'width') > 0
            && $this->dimension($unit, 'height') > 0
            && parent::supports($unit, $context);
    }

    public function render(AdUnit $unit, AdRenderContext $context): ?RenderedAd
    {
        if (! $this->supports($unit, $context)) {
            return null;
        }

        $width = $this->dimension($unit, 'width');
        $height = $this->dimension($unit, 'height');

        $attributes = [
            'src' => $this->sourceUrl($unit),
            'title' => $unit->name,
            // Zweite Verteidigungslinie: die Route schickt dieselbe Sandbox
            // als Antwort-Header. Faellt eine der beiden aus, haelt die andere.
            'sandbox' => self::SANDBOX,
            'width' => $width,
            'height' => $height,
            'style' => "width:{$width}px;height:{$height}px;max-width:100%;border:0;display:block;margin-inline:auto",
            'loading' => $context->lazy ? 'lazy' : 'eager',
            'referrerpolicy' => 'no-referrer',
            'scrolling' => 'no',
            'frameborder' => '0',
        ];

        return new RenderedAd(
            html: '<iframe '.$this->attributes($attributes).'></iframe>',
            providerKey: $this->key(),
            adUnitId: (int) $unit->getKey(),
            meta: ['width' => $width, 'height' => $height, 'sandbox' => self::SANDBOX],
            requiresConsent: $this->requiresConsent($unit),
        );
    }

    /**
     * Zustell-URL mit kurzem Abdruck des Markups: so zieht eine Aenderung am
     * Werbemittel sofort, auch wenn der Browser den Rahmen gecacht hat.
     */
    private function sourceUrl(AdUnit $unit): string
    {
        $fingerprint = substr(sha1((string) $unit->html()), 0, 8);

        return route(self::ROUTE, ['adUnit' => $unit->getKey(), 'v' => $fingerprint]);
    }

    private function dimension(AdUnit $unit, string $key): int
    {
        return (int) (((array) $unit->config_json)[$key] ?? 0);
    }

    /**
     * Groessengrenze in Bytes statt in Zeichen: `max` zaehlt Zeichen, ein
     * Markup voller Umlaute waere sonst doppelt so gross.
     */
    private function byteLimit(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && strlen($value) > self::MAX_BYTES) {
                $fail(__('Das HTML darf hoechstens 20 kB gross sein.'));
            }
        };
    }
}
