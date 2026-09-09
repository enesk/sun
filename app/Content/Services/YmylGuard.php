<?php

declare(strict_types=1);

namespace App\Content\Services;

/**
 * YMYL-Schranken der Themenfindung (#12).
 *
 * YMYL steht fuer "Your Money or Your Life" — Themen, bei denen ein falscher
 * Rat Gesundheit, Rechtsposition oder Vermoegen kostet. Fuer Mandanten mit
 * tenant_content_settings.is_ymyl gilt deshalb:
 *
 *   - Themen zu Diagnose, Therapie oder Rechtsberatung duerfen nur
 *     informierend behandelt werden. Der Kandidat bekommt intent
 *     'informational' und informational_only = true; der Generator (#14)
 *     leitet daraus den Pflichthinweis ab, dass der Text keine Beratung
 *     ersetzt.
 *   - Themen mit Handlungsempfehlung zu Medikamenten oder Dosierungen werden
 *     abgelehnt. Ein automatisch erzeugter Ratgeber darf so etwas nicht
 *     schreiben, auch nicht mit Hinweis.
 *
 * Die Begriffslisten stehen in config('content.topics.ymyl') und werden auf
 * derselben Normalform geprueft wie die Branchen-Keywords (Umlaute
 * ausgeschrieben), damit 'Dosierung' und 'dosierung' gleich behandelt werden
 * und 'Ueberdosis' auch als 'Überdosis' trifft.
 */
class YmylGuard
{
    public const OUTCOME_ALLOWED = 'allowed';

    public const OUTCOME_INFORMATIONAL = 'informational_only';

    public const OUTCOME_BLOCKED = 'blocked';

    /**
     * @return array{outcome: string, term: ?string, reason: ?string}
     */
    public function evaluate(string $text, bool $isYmyl): array
    {
        if (! $isYmyl) {
            return ['outcome' => self::OUTCOME_ALLOWED, 'term' => null, 'reason' => null];
        }

        $haystack = $this->normalize($text);

        $blocked = $this->firstMatch($haystack, (array) config('content.topics.ymyl.blocked', []));

        if ($blocked !== null) {
            return [
                'outcome' => self::OUTCOME_BLOCKED,
                'term' => $blocked,
                'reason' => "YMYL: Handlungsempfehlung zu '{$blocked}' ist fuer automatisch erzeugte Ratgeber gesperrt.",
            ];
        }

        $informational = $this->firstMatch($haystack, (array) config('content.topics.ymyl.informational_only', []));

        if ($informational !== null) {
            return [
                'outcome' => self::OUTCOME_INFORMATIONAL,
                'term' => $informational,
                'reason' => "YMYL: '{$informational}' wird ausschliesslich informierend behandelt, ohne Handlungsempfehlung.",
            ];
        }

        return ['outcome' => self::OUTCOME_ALLOWED, 'term' => null, 'reason' => null];
    }

    /**
     * @param  array<int, mixed>  $terms
     */
    private function firstMatch(string $haystack, array $terms): ?string
    {
        foreach ($terms as $term) {
            if (! is_string($term)) {
                continue;
            }

            $needle = $this->normalize($term);

            // Wortstamm-Treffer, auch im Kompositum: 'dosierung' trifft
            // 'Wirkstoffdosierung', 'diagnose' trifft 'Ferndiagnose'.
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $term;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
