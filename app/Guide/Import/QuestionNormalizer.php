<?php

declare(strict_types=1);

namespace App\Guide\Import;

use Illuminate\Support\Str;

/**
 * Normalform einer Frage fuer die Duplikaterkennung und Slug-Bildung.
 *
 * Duplikate: klein geschrieben, ohne Satzzeichen, ohne Stoppwoerter, ohne
 * Jahreszahlen. Fragewoerter bleiben erhalten, damit "Was kostet …" und
 * "Warum kostet …" nicht zusammenfallen.
 *
 * Slug: zusaetzlich ohne Fragewoerter, hoechstens 70 Zeichen, an einer
 * Wortgrenze gekuerzt.
 */
class QuestionNormalizer
{
    public const SLUG_MAX_LENGTH = 70;

    /**
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'der', 'die', 'das', 'den', 'dem', 'des',
        'ein', 'eine', 'einer', 'eines', 'einem', 'einen',
        'und', 'oder', 'aber', 'sowie', 'bzw',
        'fuer', 'für', 'mit', 'von', 'vom', 'zu', 'zum', 'zur', 'im', 'in', 'ins',
        'am', 'an', 'ans', 'auf', 'bei', 'beim', 'aus', 'nach', 'ueber', 'über', 'um', 'durch',
        'ist', 'sind', 'wird', 'werden', 'hat', 'haben', 'kann', 'darf',
        'ich', 'man', 'sich', 'es', 'mein', 'meine', 'meinen', 'meinem', 'meiner', 'meines',
        'sie', 'ihr', 'ihre', 'ihren', 'ihrem', 'ihrer', 'wir', 'uns', 'unser', 'unsere',
        'auch', 'noch', 'schon', 'nur', 'eigentlich', 'genau', 'wirklich', 'denn', 'so',
    ];

    /**
     * Nur fuer den Slug zusaetzlich entfernte Fuellwoerter.
     *
     * @var array<int, string>
     */
    private const SLUG_FILLERS = [
        'was', 'wie', 'wo', 'wann', 'warum', 'wieso', 'weshalb', 'wer', 'wen', 'wem',
        'welche', 'welcher', 'welches', 'welchen', 'welchem', 'woran', 'worauf', 'wozu',
        'muss', 'muessen', 'müssen', 'soll', 'sollte', 'sollten', 'lohnt', 'gibt', 'jahr', 'jahre', 'jahren',
    ];

    public function __construct(private readonly PlaceholderResolver $placeholders) {}

    public function normalize(string $question): string
    {
        return implode(' ', $this->words($question, self::STOPWORDS));
    }

    public function slug(string $question): string
    {
        $words = $this->words($question, [...self::STOPWORDS, ...self::SLUG_FILLERS]);

        // Besteht die Frage nur aus Fuellwoertern, lieber ein langer als ein leerer Slug.
        if ($words === []) {
            $words = $this->words($question, []);
        }

        $slug = Str::slug(implode(' ', $words), '-', 'de');

        return $this->truncate($slug, self::SLUG_MAX_LENGTH);
    }

    /**
     * Haengt einen Zaehler an und bleibt dabei innerhalb der Hoechstlaenge.
     */
    public function withSuffix(string $slug, int $counter): string
    {
        $suffix = "-{$counter}";

        return $this->truncate($slug, self::SLUG_MAX_LENGTH - strlen($suffix)).$suffix;
    }

    /**
     * @param  array<int, string>  $skip
     * @return array<int, string>
     */
    private function words(string $question, array $skip): array
    {
        $text = mb_strtolower($this->placeholders->withoutYear($question));
        // Jahreszahlen gehoeren weder in den Slug noch in den Vergleich.
        $text = (string) preg_replace('/\b(19|20)\d{2}\b/u', ' ', $text);
        // Platzhalter bleiben als ein Wort erhalten ({{branch}} -> branch).
        $text = (string) preg_replace('/\{\{\s*([a-z_]+)\s*\}\}/u', ' $1 ', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($words, static fn (string $word): bool => ! in_array($word, $skip, true)));
    }

    private function truncate(string $slug, int $length): string
    {
        if (strlen($slug) <= $length) {
            return $slug;
        }

        $cut = substr($slug, 0, $length + 1);
        $boundary = strrpos($cut, '-');

        $cut = $boundary !== false && $boundary > 0
            ? substr($cut, 0, $boundary)
            : substr($slug, 0, $length);

        return trim($cut, '-');
    }
}
