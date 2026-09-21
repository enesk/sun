<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\GeoRegion;
use App\Content\Models\TopicCandidate;
use App\Content\Support\TextFold;

/**
 * Erkennt Themen, die in Wahrheit eine Betriebssuche sind (#41).
 *
 * Search Console liefert vor allem Anfragen wie "elektriker hamburg" oder
 * "elektriker in der nähe". Wer so sucht, will einen Betrieb, keinen
 * Ratgeber; das Portal bedient ihn mit Stadt- und Kategorieseiten. Ein
 * Ratgeber dazu waere duenn und wuerde mit diesen Seiten konkurrieren.
 *
 * Regel: Nimmt man Ort, Naehe-Angabe und Suchwoerter ("finden", "notdienst")
 * aus dem Hauptkeyword und bleibt danach hoechstens ein Begriff uebrig, ist es
 * eine Betriebssuche. "wallbox kosten hamburg" bleibt ein Thema, "elektriker
 * hamburg" nicht.
 */
final class NavigationalTopicDetector
{
    public const REASON = 'navigational';

    /**
     * Naehe-Angaben, bereits gefaltet (TextFold::fold).
     */
    private const PROXIMITY = [
        'in meiner naehe', 'in der naehe', 'in der umgebung', 'in meiner umgebung', 'bei mir',
        'near me', 'vor ort', 'naehe', 'umgebung', 'umkreis', 'nahe',
    ];

    /**
     * Woerter, mit denen jemand einen Betrieb sucht, nicht eine Antwort.
     */
    private const SEEKING = [
        'finden', 'suchen', 'gesucht', 'suche', 'notdienst', 'notfall', 'notruf', '24h', '24',
        'stunden', 'sofort', 'heute', 'firma', 'firmen', 'betrieb', 'betriebe', 'fachbetrieb',
        'meisterbetrieb', 'anbieter', 'kontakt', 'telefonnummer', 'telefon', 'adresse',
        'oeffnungszeiten',
    ];

    /**
     * Fuellwoerter, die nach dem Herausnehmen nicht als Begriff zaehlen.
     */
    private const FILLER = [
        'in', 'im', 'der', 'die', 'das', 'den', 'dem', 'bei', 'fuer', 'mit', 'und', 'am', 'an',
        'zu', 'aus', 'von', 'ein', 'eine', 'einen', 'mein', 'meine', 'meiner', 'ihr', 'ihre',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private array $placeCache = [];

    /**
     * Begruendung, wenn das Thema eine Betriebssuche ist, sonst null.
     */
    public function reason(TopicCandidate $topic): ?string
    {
        $keyword = trim((string) ($topic->primary_keyword ?: $topic->title));

        return $keyword === '' ? null : $this->reasonFor($keyword);
    }

    public function reasonFor(string $keyword): ?string
    {
        $text = ' '.TextFold::fold($keyword).' ';
        $removed = false;

        foreach ([...self::PROXIMITY, ...$this->places()] as $phrase) {
            if ($phrase !== '' && str_contains($text, " {$phrase} ")) {
                $text = str_replace(" {$phrase} ", ' ', $text);
                $removed = true;
            }
        }

        $words = array_values(array_filter(explode(' ', trim($text))));
        $remaining = [];

        foreach ($words as $word) {
            if (in_array($word, self::SEEKING, true)) {
                $removed = true;

                continue;
            }

            if (! in_array($word, self::FILLER, true) && mb_strlen($word) >= 3) {
                $remaining[] = $word;
            }
        }

        if (! $removed || count($remaining) > 1) {
            return null;
        }

        return __('Betriebssuche statt Ratgeberthema: „:keyword" bedienen die Stadt- und Kategorieseiten des Portals.', [
            'keyword' => $keyword,
        ]);
    }

    /**
     * Orts- und Bundeslandnamen des Mandanten, gefaltet, laengste zuerst,
     * damit "frankfurt am main" vor "frankfurt" greift.
     *
     * @return array<int, string>
     */
    private function places(): array
    {
        $cacheKey = (string) (tenant()?->getTenantKey() ?? 'central');

        if (isset($this->placeCache[$cacheKey])) {
            return $this->placeCache[$cacheKey];
        }

        // Direkt aus der Tabelle statt ueber GeoRegion::lookup(): dessen
        // Cache haelt nach dem ersten Seeden bis zu einer Stunde die leere
        // Liste, und ohne Orte waere jede Betriebssuche ein Thema.
        $names = [
            'deutschland',
            ...GeoRegion::query()
                ->whereIn('scope', [GeoRegion::SCOPE_CITY, GeoRegion::SCOPE_STATE])
                ->where('is_active', true)
                ->pluck('name')
                ->map(static fn ($name): string => (string) $name)
                ->all(),
        ];

        // Suchende schreiben "freiburg", nicht "Freiburg im Breisgau": neben
        // dem vollen Namen zaehlt auch der Name ohne Zusatz (#42).
        $variants = [];

        foreach ($names as $name) {
            $variants[] = $name;
            $variants[] = self::withoutSuffix($name);
        }

        $folded = array_values(array_unique(array_filter(array_map(
            static fn (string $name): string => TextFold::fold($name),
            $variants,
        ), static fn (string $name): bool => mb_strlen($name) >= 4)));

        usort($folded, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $this->placeCache[$cacheKey] = $folded;
    }

    /**
     * "Esslingen am Neckar" -> "Esslingen", "Verden (Aller)" -> "Verden",
     * "Neustadt an der Weinstraße" -> "Neustadt".
     */
    public static function withoutSuffix(string $name): string
    {
        $name = trim((string) preg_replace('/\s*\(.*\)\s*$/u', '', $name));

        return trim((string) preg_replace('/\s+(am|an der|an|im|in der|in|ob der|bei|vor der)\s+.*$/iu', '', $name));
    }
}
