<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use Illuminate\Http\Request;

/**
 * Maschineller GET-Verkehr auf den Portalen (#17, docs/bot-traffic.md).
 *
 * Einzige Stelle, an der entschieden wird, ob eine Anfrage als Bot gilt.
 * Vorher gab es zwei Listen mit zwei Staenden: die Konstante
 * TrackingService::BOT_PATTERNS und config/premium.php `stats.bot_user_agents`.
 * Beide kannten die in docs/turnstile.md §1.3 C belegten Scraper nicht
 * (`HIMZO-DataQuality`, `*LeadResearch`, `webapp-mapper-authorized-probe`,
 * nackter `Mozilla/5.0`, die Alibaba-Netze). Jetzt liest beides hier,
 * gepflegt wird config/antispam.php `bot_traffic`.
 *
 * Diese Klasse SPERRT nichts. Sie entscheidet nur, ob ein Aufruf in die
 * Statistik eines Betriebs einfliesst (tracking_events, company_events).
 * Das Abweisen gehoert in die Cloudflare-WAF, weil es Rechenzeit vor dem
 * Ursprung spart und weil eine App-Sperre echte Besucher und
 * Suchmaschinen-Bots treffen kann — die Regeln dafuer stehen in
 * docs/bot-traffic.md §2.
 *
 * Drei Merkmale, in dieser Reihenfolge geprueft:
 *
 *   1. kein User-Agent                  -> Bot
 *   2. User-Agent ist nackt (`Mozilla/5.0` und nichts weiter) -> Bot
 *   3. Teilstring im User-Agent oder /24-Netz auf der Liste   -> Bot
 *
 * Die IP-Pruefung arbeitet auf `$request->ip()`, also der vollen Adresse.
 * In `tracking_events` steht sie nur auf /24 gekuerzt (siehe
 * [[registrierungen-ohne-ip-und-ua]]); nachtraeglich laesst sich damit kein
 * Einzel-IP-Limit bauen, vor dem Schreiben ist die Adresse aber vollstaendig
 * vorhanden.
 */
final class BotTraffic
{
    public static function isBot(?Request $request = null): bool
    {
        return self::reason($request) !== null;
    }

    /**
     * Grund des Treffers, fuer Log und Auswertung:
     * `ua:empty`, `ua:bare`, `ua:<teilstring>` oder `ip:<netz>`.
     * Kein Treffer: null.
     */
    public static function reason(?Request $request = null): ?string
    {
        $request ??= request();

        if ($request === null) {
            return null;
        }

        if (! self::enabled()) {
            return null;
        }

        return self::matchUserAgent($request->userAgent())
            ?? self::matchIp($request->ip());
    }

    public static function enabled(): bool
    {
        return AntiSpamConfig::bool('bot_traffic.enabled', true);
    }

    /**
     * @return string|null `ua:empty`, `ua:bare`, `ua:<teilstring>` oder null
     */
    public static function matchUserAgent(?string $userAgent): ?string
    {
        $userAgent = strtolower(trim((string) $userAgent));

        if ($userAgent === '' || $userAgent === '-') {
            return 'ua:empty';
        }

        foreach (AntiSpamConfig::list('bot_traffic.bare_user_agents') as $bare) {
            if ($userAgent === strtolower($bare)) {
                return 'ua:bare';
            }
        }

        foreach (AntiSpamConfig::list('bot_traffic.user_agents') as $pattern) {
            if (str_contains($userAgent, strtolower($pattern))) {
                return 'ua:'.$pattern;
            }
        }

        return null;
    }

    /**
     * @return string|null `ip:<netz>` oder null
     */
    public static function matchIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        foreach (AntiSpamConfig::list('bot_traffic.ip_prefixes') as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return 'ip:'.$cidr;
            }
        }

        return null;
    }

    /**
     * CIDR-Vergleich fuer IPv4 und IPv6 auf der Bitfolge. Ohne Praefixlaenge
     * gilt die Angabe als einzelne Adresse.
     */
    public static function inCidr(string $ip, string $cidr): bool
    {
        [$netz, $laenge] = array_pad(explode('/', trim($cidr), 2), 2, null);

        $adresse = @inet_pton($ip);
        $netzAdresse = @inet_pton((string) $netz);

        if ($adresse === false || $netzAdresse === false || strlen($adresse) !== strlen($netzAdresse)) {
            return false;
        }

        $bits = $laenge === null ? strlen($adresse) * 8 : (int) $laenge;
        $bits = max(0, min($bits, strlen($adresse) * 8));

        $volleBytes = intdiv($bits, 8);
        $restBits = $bits % 8;

        if ($volleBytes > 0 && strncmp($adresse, $netzAdresse, $volleBytes) !== 0) {
            return false;
        }

        if ($restBits === 0) {
            return true;
        }

        $maske = ~((1 << (8 - $restBits)) - 1) & 0xFF;

        return (ord($adresse[$volleBytes]) & $maske) === (ord($netzAdresse[$volleBytes]) & $maske);
    }
}
