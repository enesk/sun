<?php

declare(strict_types=1);

namespace App\Content\Support;

/**
 * Umlaute, die das Modell als ae/oe/ue umschreibt (#41).
 *
 * Die Prompts der Pipeline sind in ASCII geschrieben ("fuer", "Naehe"), und
 * das Modell uebernimmt diese Schreibung teilweise in den Artikel. Die
 * Sprachregel im Prompt (GenerationStep) behebt das an der Ursache; diese
 * Klasse ist das Sicherheitsnetz dahinter.
 *
 * repair() ersetzt nur Wortstaemme aus einer geprueften Liste. Eine
 * allgemeine Regel "ae -> ä" waere falsch fuer "Israel", "Poet", "aktuell",
 * "zuerst", "Koexistenz" und Link-Adressen wie "/staedte/hamburg". Was die
 * Liste nicht kennt, findet suspects() und meldet der SEO-Lint.
 *
 * ß wird nie erzeugt: "Masse" und "Maße" lassen sich ohne Woerterbuch nicht
 * unterscheiden.
 */
final class UmlautSpelling
{
    /**
     * Umschriebener Wortstamm => richtige Schreibung, in Kleinschreibung.
     * Der Stamm greift auch mitten im Wort ("gegenueber", "Annaeherung");
     * jeder Eintrag ist deshalb so gewaehlt, dass er in keinem richtig
     * geschriebenen deutschen Wort vorkommt.
     */
    private const STEMS = [
        // ä
        'aehnlich' => 'ähnlich', 'aelter' => 'älter', 'aender' => 'änder', 'aerger' => 'ärger',
        'aerzt' => 'ärzt', 'aeusser' => 'äußer', 'baeder' => 'bäder', 'blaett' => 'blätt',
        'daemm' => 'dämm', 'erklaer' => 'erklär', 'faehig' => 'fähig', 'faell' => 'fäll',
        'gaert' => 'gärt', 'gebaeud' => 'gebäud', 'gefaehr' => 'gefähr', 'geraet' => 'gerät',
        'geraeusch' => 'geräusch', 'gewaehr' => 'gewähr', 'haelt' => 'hält', 'haett' => 'hätt',
        'haeufig' => 'häufig', 'haeus' => 'häus', 'jaehr' => 'jähr', 'kaelt' => 'kält',
        'kaeuf' => 'käuf', 'laend' => 'länd', 'laeng' => 'läng', 'laerm' => 'lärm',
        'maengel' => 'mängel', 'maerz' => 'märz', 'naech' => 'näch', 'naeh' => 'näh',
        'plaen' => 'plän', 'qualitaet' => 'qualität', 'raeum' => 'räum', 'saeule' => 'säule',
        'schaed' => 'schäd', 'schaetz' => 'schätz', 'spaet' => 'spät', 'staedt' => 'städt',
        'staerk' => 'stärk', 'taeglich' => 'täglich', 'taetig' => 'tätig', 'tatsaech' => 'tatsäch',
        'traeg' => 'träg', 'waehl' => 'wähl', 'waehr' => 'währ', 'waend' => 'wänd',
        'waerm' => 'wärm', 'waere' => 'wäre', 'waesch' => 'wäsch', 'zaehl' => 'zähl',
        'zusaetz' => 'zusätz', 'zustaend' => 'zuständ', 'kapazitaet' => 'kapazität',
        'elektrizitaet' => 'elektrizität', 'sicherheitsluecke' => 'sicherheitslücke',
        // ö
        'behoerd' => 'behörd', 'benoetig' => 'benötig', 'erhoeh' => 'erhöh', 'foerder' => 'förder',
        'gehoer' => 'gehör', 'hoech' => 'höch', 'hoeh' => 'höh', 'koenn' => 'könn',
        'koerper' => 'körper', 'loesch' => 'lösch', 'loesung' => 'lösung', 'loesen' => 'lösen',
        'geloest' => 'gelöst', 'moebel' => 'möbel', 'moeglich' => 'möglich', 'noetig' => 'nötig',
        'oeffn' => 'öffn', 'oefen' => 'öfen', 'oertlich' => 'örtlich', 'schoen' => 'schön',
        'stoer' => 'stör', 'zerstoer' => 'zerstör', 'groesser' => 'größer', 'groesst' => 'größt',
        // ü
        'buendel' => 'bündel', 'buerger' => 'bürger', 'buero' => 'büro', 'duerf' => 'dürf',
        'frueh' => 'früh', 'fuehr' => 'führ', 'fuell' => 'füll', 'fuenf' => 'fünf',
        'glueh' => 'glüh', 'kuech' => 'küch', 'kuehl' => 'kühl', 'kuemmer' => 'kümmer',
        'kuend' => 'künd', 'kuerz' => 'kürz', 'lueft' => 'lüft', 'muell' => 'müll',
        'muess' => 'müss', 'pruef' => 'prüf', 'rueck' => 'rück', 'schluess' => 'schlüss',
        'schuetz' => 'schütz', 'stueck' => 'stück', 'tuer' => 'tür', 'ueber' => 'über',
        'unterstuetz' => 'unterstütz', 'verfueg' => 'verfüg', 'wuensch' => 'wünsch',
        'wuerd' => 'würd', 'zurueck' => 'zurück', 'bruecke' => 'brücke', 'gruend' => 'gründ',
        'guenstig' => 'günstig', 'natuerlich' => 'natürlich', 'kuenftig' => 'künftig',
        'zuverlaessig' => 'zuverlässig', 'luecke' => 'lücke', 'fluessig' => 'flüssig',
    ];

    /**
     * Ganze Woerter, deren Stamm zu kurz oder zu mehrdeutig fuer die
     * Stammliste ist.
     */
    private const WORDS = [
        'fuer' => 'für', 'ueblich' => 'üblich', 'uebrig' => 'übrig', 'uebrigen' => 'übrigen',
        'ueblicherweise' => 'üblicherweise', 'muessen' => 'müssen', 'waehrend' => 'während',
        'koennte' => 'könnte', 'koennten' => 'könnten', 'wuerde' => 'würde', 'wuerden' => 'würden',
        'oel' => 'öl', 'oelheizung' => 'ölheizung', 'hoehe' => 'höhe', 'groesse' => 'größe',
        'fuesse' => 'füße', 'suess' => 'süß', 'aeusserst' => 'äußerst',
    ];

    /**
     * Echte Woerter mit ae/oe/ue, die suspects() nicht melden darf.
     * Verglichen wird kleingeschrieben mit dem Wortanfang.
     */
    private const ALLOWED_PREFIXES = [
        'zue', 'israel', 'michael', 'raphael', 'aero', 'poet', 'poesie', 'oboe', 'koex',
        'koeff', 'koedu', 'duett', 'menuett', 'silhouett', 'manuel', 'samuel', 'emanuel',
        'guerill', 'suez', 'joel', 'zoe', 'blues', 'true', 'value', 'queue', 'fuel',
    ];

    /**
     * Echte Wortendungen/-teile mit ue: aktuell, eventuell, Frequenz, Influencer.
     */
    private const ALLOWED_INFIXES = ['uell', 'uenz', 'uent', 'uett'];

    /**
     * Ersetzt umschriebene Umlaute im Fliesstext. HTML-Tags und alles, was
     * wie eine Adresse aussieht ("/staedte/hamburg", "reparatur-stoerung"),
     * bleiben unberuehrt.
     */
    public static function repair(string $text): string
    {
        if ($text === '' || preg_match('/ae|oe|ue/i', $text) !== 1) {
            return $text;
        }

        $parts = preg_split('/(<[^>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        foreach ($parts as $index => $part) {
            if ($part === '' || str_starts_with($part, '<')) {
                continue;
            }

            $parts[$index] = (string) preg_replace_callback(
                '/[^\s"\'<>()\[\],;!?]+/u',
                static fn (array $token): string => self::repairToken($token[0]),
                $part,
            );
        }

        return implode('', $parts);
    }

    /**
     * Wendet repair() auf alle Zeichenketten einer Modellantwort an. Schluessel
     * bleiben unveraendert, ebenso Werte, die Adressen oder Kennungen sind.
     */
    public static function repairPayload(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $repaired = [];

            foreach ($value as $childKey => $child) {
                $repaired[$childKey] = self::repairPayload($child, is_string($childKey) ? $childKey : $key);
            }

            return $repaired;
        }

        if (! is_string($value) || ($key !== null && preg_match('/(^|_)(url|href|slug|id|key|code|path|anchor_target)$/', $key) === 1)) {
            return $value;
        }

        return self::repair($value);
    }

    /**
     * Woerter, die nach wie vor nach umschriebenem Umlaut aussehen. Fuer den
     * SEO-Lint; die Liste ist bewusst vorsichtig und keine Korrektur.
     *
     * @return array<int, string>
     */
    public static function suspects(string $text): array
    {
        $plain = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = [];

        preg_match_all('/[^\s"\'<>()\[\],;!?]+/u', $plain, $tokens);

        foreach ($tokens[0] as $token) {
            if (self::looksLikeAddress($token)) {
                continue;
            }

            foreach (preg_split('/[^\p{L}]+/u', $token) ?: [] as $word) {
                if ($word !== '' && self::isSuspect($word)) {
                    $found[] = $word;
                }
            }
        }

        return array_values(array_unique($found));
    }

    private static function repairToken(string $token): string
    {
        if (self::looksLikeAddress($token)) {
            return $token;
        }

        return (string) preg_replace_callback(
            '/\p{L}+/u',
            static fn (array $word): string => self::repairWord($word[0]),
            $token,
        );
    }

    private static function repairWord(string $word): string
    {
        $lower = mb_strtolower($word);

        if (preg_match('/ae|oe|ue/', $lower) !== 1) {
            return $word;
        }

        if (isset(self::WORDS[$lower])) {
            return self::matchCase($word, self::WORDS[$lower]);
        }

        $result = $lower;

        foreach (self::sortedStems() as $stem => $replacement) {
            if (str_contains($result, $stem)) {
                $result = str_replace($stem, $replacement, $result);
            }
        }

        return $result === $lower ? $word : self::matchCase($word, $result);
    }

    /**
     * Uebertraegt die Grossschreibung des Originals: "NAEHE" bleibt
     * Grossbuchstaben, "Naehe" bekommt einen grossen Anfangsbuchstaben.
     */
    private static function matchCase(string $original, string $replacement): string
    {
        if ($original === mb_strtoupper($original) && mb_strlen($original) > 1) {
            return mb_strtoupper($replacement);
        }

        $first = mb_substr($original, 0, 1);

        if ($first === mb_strtoupper($first)) {
            return mb_strtoupper(mb_substr($replacement, 0, 1)).mb_substr($replacement, 1);
        }

        return $replacement;
    }

    private static function isSuspect(string $word): bool
    {
        $lower = mb_strtolower($word);

        // ae/oe/ue nach einem Vokal oder q ist echt: neue, Feuer, Frauen, Quelle.
        if (preg_match('/(?<![aeiouyq])(ae|oe|ue)/', $lower) !== 1) {
            return false;
        }

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return false;
            }
        }

        return preg_match('/'.implode('|', self::ALLOWED_INFIXES).'/', $lower) !== 1;
    }

    /**
     * Pfad, Adresse, Dateiname oder Slug: alles kleingeschrieben mit
     * Schraegstrich, Punkt-Endung oder Bindestrich-Kette ohne Grossbuchstaben.
     */
    private static function looksLikeAddress(string $token): bool
    {
        if (str_contains($token, '/') || str_contains($token, '@') || str_contains($token, '://')) {
            return true;
        }

        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)+$/', $token) === 1;
    }

    /**
     * Laengste Staemme zuerst, damit "zerstoer" vor "stoer" greift.
     *
     * @return array<string, string>
     */
    private static function sortedStems(): array
    {
        static $sorted = null;

        if ($sorted === null) {
            $sorted = self::STEMS;
            uksort($sorted, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
        }

        return $sorted;
    }
}
