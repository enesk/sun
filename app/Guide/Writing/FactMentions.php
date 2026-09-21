<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Research\FactNormalizer;

/**
 * Ob ein Text einen geaenderten Fakt nennt (#10). Grundlage fuer die Regel,
 * dass Titel, Meta, Kurzantwort und FAQ im Update-Modus nur neu entstehen,
 * wenn ein darin genannter Fakt betroffen ist.
 *
 * Verglichen wird der alte Wert in der Normalform des FactNormalizer
 * ("1.500 €" = "1500 Euro"). Neu hinzugekommene Fakten haben keinen alten
 * Wert und koennen im bestehenden Text nicht stehen.
 */
class FactMentions
{
    public function __construct(
        private readonly FactNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $changedFacts  Form von {{changed_facts}}
     */
    public function mentionsAny(?string $text, array $changedFacts): bool
    {
        $haystack = $this->normalizer->normalize(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($haystack === '') {
            return false;
        }

        foreach ($changedFacts as $fact) {
            $old = trim((string) ($fact['old_value'] ?? ''));

            if ($old === '') {
                continue;
            }

            $needle = $this->normalizer->normalize($old);

            if (mb_strlen($needle) >= 2 && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
