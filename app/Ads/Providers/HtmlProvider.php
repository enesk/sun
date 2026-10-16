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
 * 6. Die Sandbox haelt das Werbemittel vom Portal-Origin fern, **nicht vom
 *    Netz**: `allow-scripts` ist erlaubt, ein Skript im Rahmen darf also
 *    beliebige Hosts ansprechen und dabei IP-Adresse, Browser- und
 *    Geraetedaten uebertragen. Ob das passiert, weiss nur, wer das Markup
 *    eintraegt — darum entscheidet es das Feld `third_party` je Werbemittel
 *    ({@see requiresConsent()}, Ticket #36).
 */
class HtmlProvider extends AbstractAdProvider
{
    /** Obergrenze fuer eigenes Markup: 20 kB (Ticket #3). */
    public const MAX_BYTES = 20480;

    /**
     * Erlaubte Sandbox-Rechte — bewusst ohne allow-same-origin.
     *
     * `allow-scripts` steht drin: Werbemittel sind ohne Skripte kaum zu
     * gebrauchen. Die Sandbox trennt damit Origins, nicht Netzwerkziele —
     * ein Skript hier darf jeden Host ansprechen (Ticket #36).
     */
    public const SANDBOX = 'allow-scripts allow-popups allow-popups-to-escape-sandbox';

    /** Schluessel des Einwilligungs-Schalters in `config_json` (#36). */
    public const THIRD_PARTY = 'third_party';

    /** „Laedt Inhalte Dritter" — Einwilligung noetig. Auch die Vorgabe. */
    public const THIRD_PARTY_YES = 'yes';

    /** Nur eigene Inhalte — keine Einwilligung noetig. */
    public const THIRD_PARTY_NO = 'no';

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
            [
                'name' => self::THIRD_PARTY,
                'label' => __('Laedt dieses Werbemittel Inhalte Dritter?'),
                'type' => 'select',
                'required' => false,
                'options' => [
                    self::THIRD_PARTY_YES => __('Ja — Skripte, Bilder oder Zaehlpixel von fremden Hosts (Einwilligung noetig)'),
                    self::THIRD_PARTY_NO => __('Nein — nur eigene Inhalte von diesem Portal (laedt sofort)'),
                ],
                'help' => __('Entscheidet, ob der Rahmen erst nach der Einwilligung in Marketing-Cookies laedt. Ohne Angabe gilt „Ja" — ein Rahmen, der fremde Hosts anspricht, uebertraegt IP-Adresse und Geraetedaten an den Anbieter (§ 25 Abs. 1 TDDDG).'),
                'rules' => ['sometimes', 'nullable', 'string', 'in:'.self::THIRD_PARTY_YES.','.self::THIRD_PARTY_NO],
                'messages' => ['in' => __('Bitte angeben, ob das Werbemittel Inhalte Dritter laedt.')],
            ],
            $this->formatField(),
        ];
    }

    /**
     * Eigenes HTML braucht eine Einwilligung, sobald es Inhalte Dritter
     * nachlaedt (Ticket #36).
     *
     * Die Sandbox schuetzt den Portal-Origin — kein Cookie-, kein
     * Speicher-, kein DOM-Zugriff —, sie schneidet den Rahmen aber **nicht**
     * vom Netz ab: `allow-scripts` ist gesetzt, ein Skript darf jeden Host
     * ansprechen und dabei IP-Adresse, Browser-/Geraetedaten und Zeitpunkt
     * uebertragen. Das ist genau der Zugriff, fuer den § 25 Abs. 1 TDDDG
     * bzw. Art. 6 Abs. 1 lit. a DSGVO eine Einwilligung verlangt.
     *
     * Ob ein Werbemittel das tut, steht nicht im Markup zu erkennen (ein
     * `document.write` einer fremden URL reicht) — es weiss nur, wer es
     * eintraegt. Darum ein Schalter je Werbemittel statt pauschal `true`:
     * ein Eigenbanner ohne Drittbezug soll weiter sofort laufen.
     *
     * **Fehlt die Angabe, gilt „Einwilligung noetig".** Altbestand und ein
     * halb ausgefuelltes Formular landen damit auf der sicheren Seite;
     * {@see normalizeConfig()} schreibt beim Speichern den Wert fest.
     */
    public function requiresConsent(AdUnit $unit): bool
    {
        // Exakter Vergleich: alles, was nicht genau `no` ist, gilt als
        // einwilligungspflichtig. Die Auswahl laesst nur `yes`/`no` zu
        // (validateConfig), ein krummer Wert aus einem Import landet damit
        // auf der sicheren Seite statt auf einer Normalisierung, die hier
        // und in der Pruefung auseinanderlaufen koennte.
        return (((array) $unit->config_json)[self::THIRD_PARTY] ?? null) !== self::THIRD_PARTY_NO;
    }

    /**
     * Wie beim `format`: ohne Angabe wird der sichere Wert festgeschrieben,
     * damit `config_json` die Entscheidung traegt und nicht der Vorgabewert
     * im Code.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array
    {
        $clean = parent::normalizeConfig($config);

        $clean[self::THIRD_PARTY] = ($clean[self::THIRD_PARTY] ?? null) === self::THIRD_PARTY_NO
            ? self::THIRD_PARTY_NO
            : self::THIRD_PARTY_YES;

        return $clean;
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
            meta: ['width' => $width, 'height' => $height, 'sandbox' => self::SANDBOX, self::THIRD_PARTY => $this->requiresConsent($unit)],
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
