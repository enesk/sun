<?php

declare(strict_types=1);

namespace App\Content\Sources\Support;

/**
 * Uebersetzt zwischen den Bundesland-Schreibweisen der externen Quellen (#11).
 *
 * Fuehrend ist der ISO-3166-2-Code aus config('content.regions.states')
 * ('DE-BY'); genau der landet in source_items.region_code und
 * fact_snippets.region_code. Die Quellen sprechen aber jeweils eigene
 * Dialekte: die Foerderdatenbank nennt "Bayern", ferien-api.de will "BY",
 * die Regionalstatistik arbeitet mit dem amtlichen Regionalschluessel ("09"),
 * und Google News braucht den ausgeschriebenen Namen in der Suchanfrage.
 *
 * Alles hier ist reine Uebersetzung ohne Datenhaltung — die Liste der Laender
 * selbst steht in der Konfiguration und wird nicht dupliziert.
 */
final class StateCatalog
{
    /**
     * ISO-Code => amtlicher Regionalschluessel (erste zwei Stellen des AGS).
     * Wird fuer Regionalstatistik-Tabellen gebraucht.
     *
     * @var array<string, string>
     */
    private const REGIONAL_KEYS = [
        'DE-SH' => '01', 'DE-HH' => '02', 'DE-NI' => '03', 'DE-HB' => '04',
        'DE-NW' => '05', 'DE-HE' => '06', 'DE-RP' => '07', 'DE-BW' => '08',
        'DE-BY' => '09', 'DE-SL' => '10', 'DE-BE' => '11', 'DE-BB' => '12',
        'DE-MV' => '13', 'DE-SN' => '14', 'DE-ST' => '15', 'DE-TH' => '16',
    ];

    /**
     * Zusaetzliche Schreibweisen, unter denen ein Land in freiem Text
     * auftaucht. Der Name aus der Konfiguration wird automatisch ergaenzt.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        'DE-BW' => ['Baden-Württemberg', 'Baden Württemberg'],
        'DE-BY' => ['Freistaat Bayern'],
        'DE-MV' => ['Mecklenburg Vorpommern'],
        'DE-NW' => ['NRW', 'Nordrhein Westfalen'],
        'DE-RP' => ['Rheinland Pfalz'],
        'DE-SN' => ['Freistaat Sachsen'],
        'DE-ST' => ['Sachsen Anhalt'],
        'DE-SH' => ['Schleswig Holstein'],
        'DE-TH' => ['Thüringen', 'Freistaat Thüringen'],
    ];

    /**
     * Alle Bundeslaender als ISO-Code => Anzeigename.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $states = [];

        foreach ((array) config('content.regions.states', []) as $iso => $state) {
            $states[(string) $iso] = (string) ($state['name'] ?? $iso);
        }

        return $states;
    }

    /**
     * @return array<int, string>
     */
    public static function isoCodes(): array
    {
        return array_keys(self::all());
    }

    public static function name(string $iso): ?string
    {
        return self::all()[$iso] ?? null;
    }

    public static function exists(string $iso): bool
    {
        return isset(self::all()[$iso]);
    }

    /**
     * Kuerzel fuer ferien-api.de ('DE-BY' => 'BY').
     */
    public static function shortCode(string $iso): string
    {
        return str_starts_with($iso, 'DE-') ? substr($iso, 3) : $iso;
    }

    public static function regionalKey(string $iso): ?string
    {
        return self::REGIONAL_KEYS[$iso] ?? null;
    }

    /**
     * ISO-Code zu einem Land, das irgendwo in einem freien Text steht.
     * Laengere Namen gewinnen, damit "Sachsen-Anhalt" nicht als "Sachsen"
     * durchgeht.
     */
    public static function fromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $haystack = self::normalize($text);
        $best = null;
        $bestLength = 0;

        foreach (self::needles() as $iso => $needles) {
            foreach ($needles as $needle) {
                if (mb_strlen($needle) > $bestLength && str_contains($haystack, $needle)) {
                    $best = $iso;
                    $bestLength = mb_strlen($needle);
                }
            }
        }

        return $best;
    }

    /**
     * ISO-Code => normalisierte Suchbegriffe. Oeffentlich, damit der
     * RegionResolver dieselben Erkennungsmuster mit seiner eigenen
     * Wortgrenzen-Pruefung verwenden kann, ohne die Laenderliste zu
     * duplizieren (#33).
     *
     * @return array<string, array<int, string>>
     */
    public static function needles(): array
    {
        $needles = [];

        foreach (self::all() as $iso => $name) {
            $variants = array_merge([$name, self::shortCode($iso)], self::ALIASES[$iso] ?? []);

            $needles[$iso] = array_values(array_unique(array_filter(
                array_map(static fn (string $variant): string => self::normalize($variant), $variants),
                // Zweibuchstabige Kuerzel treffen zu oft; sie zaehlen nur,
                // wenn sie als Alias ausdruecklich gepflegt sind.
                static fn (string $variant): bool => mb_strlen($variant) > 2,
            )));
        }

        return $needles;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
