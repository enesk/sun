<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\Models\AdSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Inhalt der `ads.txt` eines Portals (Ticket #10).
 *
 * Aufbau — in dieser Reihenfolge:
 *
 *   1. die Google-Zeile aus der **wirksamen** Publisher-ID des Portals
 *      (eigene ID, sonst die Netz-Vorgabe aus
 *      `config('ad-slots.adsense.publisher_id')`),
 *   2. die Zusatzzeilen aus `ad_settings.ads_txt_extra` (ohne Leerzeilen und
 *      Kommentare, {@see AdSetting::adsTxtLines()}).
 *
 * Die Google-Zeile entsteht nur, wenn das Portal Werbung freigegeben hat —
 * `ads.txt` sagt Google, wer Inventar dieser Domain verkaufen darf, und das
 * ist nach dem Abschalten niemand mehr. Die Zusatzzeilen bleiben auch dann
 * stehen: dort stehen fremde Vermarkter, die das Portal weiterbetreibt.
 * Bleibt am Ende nichts uebrig, liefert der Controller 404 — eine leere
 * `ads.txt` liest Google als "kein autorisierter Verkaeufer".
 *
 * Doppelte Zeilen fallen weg: der Altbestand bringt die Google-Zeile oft in
 * `ads_txt_extra` mit (Uebernahme in #15), und zweimal dieselbe Zeile ist in
 * Googles Pruefbericht eine Warnung. Verglichen wird dafuer nicht die
 * Zeichenkette, sondern die Autorisierung
 * ({@see AdSensePublisherId::isGoogleLineFor()}) — `GOOGLE.COM`, `ca-pub-…`
 * und ein Kommentar dahinter sind dieselbe Zeile.
 *
 * Ticket #41: ist das Portal **nicht** freigegeben, fallen aus den
 * Zusatzzeilen auch die Autorisierungen der **eigenen** Publisher-ID weg.
 * `ads:legacy-takeover` hat den kompletten `ads_txt_content` der Tenant-DB
 * nach `ads_txt_extra` uebernommen, und darin steht bei den meisten Portalen
 * genau die eigene Google-Zeile — ohne diesen Filter blieb die Autorisierung
 * fuer Google stehen, obwohl der Hauptschalter aus ist (Notbremse §5 von
 * docs/ad-slots-golive.md). Fremde Vermarkter und fremde Google-Kennungen
 * bleiben unberuehrt.
 *
 * Gecacht wird eine Stunde je Portal; den Schluessel vergisst
 * {@see AdCache::forgetTenant()} zusammen mit den aufgeloesten Belegungen,
 * sodass eine Aenderung im Panel sofort greift.
 */
final class AdsTxtBuilder
{
    /**
     * Fertiger Dateiinhalt mit Zeilenumbruch am Ende; leerer String = 404.
     */
    public function forTenant(Tenant|int|string $tenant): string
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return Cache::remember(
            AdCache::adsTxtKey($tenantId),
            AdCache::ttl(),
            fn (): string => $this->build($tenantId),
        );
    }

    /**
     * Ohne Cache — fuer die Vorschau im Panel und zum Pruefen auf der Konsole.
     */
    public function build(int|string $tenantId): string
    {
        $lines = $this->lines($tenantId);

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    /**
     * @return list<string>
     */
    public function lines(int|string $tenantId): array
    {
        $setting = AdSetting::query()->forTenant($tenantId)->first();

        if ($setting === null) {
            return [];
        }

        $lines = [];
        $publisher = $setting->adsensePublisherId();

        if ($setting->ads_enabled) {
            $google = AdSensePublisherId::adsTxtLine($publisher);

            if ($google !== null) {
                $lines[] = $google;
            }
        }

        foreach ($setting->adsTxtLines() as $line) {
            // Autorisierungen der eigenen Kennung kommen aus Schritt 1 oder
            // gar nicht: ist der Schalter aus, fallen sie weg; ist er an,
            // steht die kanonische Zeile schon oben. array_unique() allein
            // reicht dafuer nicht — der Altbestand schreibt dieselbe
            // Autorisierung in abweichender Schreibweise.
            if (AdSensePublisherId::isGoogleLineFor($line, $publisher)) {
                continue;
            }

            $lines[] = $line;
        }

        return array_values(array_unique($lines));
    }
}
