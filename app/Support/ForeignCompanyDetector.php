<?php

namespace App\Support;

/**
 * Erkennt Betriebe ausserhalb Deutschlands in Portalen mit deutschem Bestand (#16).
 *
 * companies hat keine Laenderspalte. Die Places-Suche ohne Regionsvorgabe und
 * die Dumps des Python-Scrapers haben deshalb US-, CH-, FR-, AT- und
 * IT-Betriebe hineingetragen, oft an eine deutsche Stadt mit gleicher PLZ
 * gehaengt. Das Bundesland der Stadt taugt nicht als Merkmal: der Import hat
 * es fuer deutsche Orte mit ueberschrieben (Beverstedt -> "North Carolina").
 *
 * Sicher sind nur zwei Merkmale des Betriebs selbst:
 *
 * - Postleitzahl, die keine deutsche sein kann (nicht fuenfstellig).
 * - Rufnummer in einer Schreibweise, die Google nur fuer fremde Nummern
 *   verwendet: US "(405) 309-6209", FR "06 14 69 03 16", CH "044 718 20 00".
 *   Deutsche Nummern formatiert Google als Vorwahl + Anschluss ("030 1234567").
 *
 * NL- ("088 440 2144") und CZ-Schreibweisen ("605 414 406") kommen auch bei
 * Betrieben mit deutscher Anschrift vor und gelten nur als Hinweis.
 */
final class ForeignCompanyDetector
{
    /** @var array<string, string> Grund => Muster der Rufnummer */
    private const FOREIGN_PHONE_PATTERNS = [
        'phone_us' => '/^(\+1[\s-]?)?\(\d{3}\) \d{3}-\d{4}$/',
        'phone_fr' => '/^0[1-9]( \d{2}){4}$/',
        'phone_ch' => '/^0\d{2} \d{3} \d{2} \d{2}$/',
    ];

    /** @var array<string, string> */
    private const HINT_PHONE_PATTERNS = [
        'phone_nl' => '/^0\d{2} \d{3} \d{4}$/',
        'phone_cz' => '/^[1-9]\d{2} \d{3} \d{3}$/',
    ];

    /** @var array<string, string> */
    public const LABELS = [
        'zipcode' => 'PLZ nicht deutsch',
        'phone_us' => 'Rufnummer US',
        'phone_fr' => 'Rufnummer FR',
        'phone_ch' => 'Rufnummer CH',
        'phone_nl' => 'Rufnummer NL (Hinweis)',
        'phone_cz' => 'Rufnummer CZ (Hinweis)',
    ];

    /**
     * Sicherer Grund, warum der Betrieb nicht in Deutschland liegt, sonst null.
     */
    public static function reason(?string $tel, ?string $zipcode): ?string
    {
        $zipcode = trim((string) $zipcode);

        if ($zipcode !== '' && ! self::isGermanZipcode($zipcode)) {
            return 'zipcode';
        }

        return self::matchPhone($tel, self::FOREIGN_PHONE_PATTERNS);
    }

    /**
     * Unsicherer Hinweis fuer den Trockenlauf — wird nie automatisch angewendet.
     */
    public static function hint(?string $tel): ?string
    {
        return self::matchPhone($tel, self::HINT_PHONE_PATTERNS);
    }

    public static function isGermanZipcode(string $zipcode): bool
    {
        return preg_match('/^\d{5}$/', trim($zipcode)) === 1;
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function matchPhone(?string $tel, array $patterns): ?string
    {
        $tel = PhoneNumber::display($tel);

        if ($tel === null) {
            return null;
        }

        foreach ($patterns as $reason => $pattern) {
            if (preg_match($pattern, $tel) === 1) {
                return $reason;
            }
        }

        return null;
    }
}
