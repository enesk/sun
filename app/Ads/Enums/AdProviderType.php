<?php

declare(strict_types=1);

namespace App\Ads\Enums;

/**
 * Art des Werbemittels und damit der Provider, der es rendert
 * (docs/ad-slots.md, Abschnitt 4).
 *
 * ADSENSE: Google AdSense, Ausspielung nur mit Publisher-ID des Tenants,
 * nur nach Einwilligung und nie im Editor-Modus.
 * HTML: eigenes Markup aus der Verwaltung, immer in einem Sandbox-iframe
 * ohne allow-same-origin — nie direkt in die Seite geschrieben.
 *
 * **Nicht die Liste der Provider.** Seit Ticket #4 steht die in
 * `config('ad-slots.providers')`; `ad_units.provider` ist ein String, und ein
 * dritter Provider braucht hier keinen Fall. Dieses Enum bleibt die
 * Konstantenliste der beiden eingebauten Arten, damit Code sie beim Namen
 * nennen kann (AdProviderType::ADSENSE->value).
 */
enum AdProviderType: string
{
    case ADSENSE = 'adsense';
    case HTML = 'html';

    public function label(): string
    {
        return match ($this) {
            self::ADSENSE => __('Google AdSense'),
            self::HTML => __('Eigenes HTML'),
        };
    }

    /**
     * @deprecated Fragt den Provider: AdProvider::requiresConsent($unit).
     *
     * Braucht dieses Werbemittel eine Einwilligung, bevor es laedt?
     * AdSense setzt Cookies und spricht fremde Hosts an; eigenes HTML laeuft
     * im Sandbox-iframe ohne Netzzugriff auf Dritte, solange die CSP des
     * iframes nichts anderes erlaubt (SUN-AD-009).
     */
    public function requiresConsent(): bool
    {
        return $this === self::ADSENSE;
    }

    /**
     * @deprecated Nutze AdProviderRegistry::options() — die kennt auch
     * nachtraeglich registrierte Provider.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
