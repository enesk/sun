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
 * ohne allow-same-origin — nie direkt in die Seite geschrieben. Die Sandbox
 * trennt Origins, nicht Netzwerkziele: ob so ein Werbemittel eine
 * Einwilligung braucht, haengt am Schalter `config_json->third_party` und
 * beantwortet nur der Provider (#36).
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
     * Braucht diese Art Werbemittel eine Einwilligung, bevor sie laedt?
     *
     * Antwortet seit #36 fuer **beide** Arten `true`. Die alte Begruendung
     * („eigenes HTML laeuft im Sandbox-iframe ohne Netzzugriff auf Dritte")
     * war falsch: `HtmlProvider::SANDBOX` traegt `allow-scripts`, der Rahmen
     * ist nur vom Portal-Origin getrennt, nicht vom Netz. Ein Skript darin
     * darf fremde Hosts ansprechen und dabei IP-Adresse und Geraetedaten
     * uebertragen (§ 25 Abs. 1 TDDDG).
     *
     * Die Art allein kann die Frage deshalb nicht beantworten — sie haengt
     * am einzelnen Werbemittel (`config_json->third_party`). Diese Methode
     * kennt kein Werbemittel und gibt darum die sichere Antwort;
     * wer die richtige braucht, fragt
     * {@see \App\Ads\Models\AdUnit::requiresConsent()} (SUN-AD-009).
     */
    public function requiresConsent(): bool
    {
        return true;
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
