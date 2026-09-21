<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Models\Fact;
use App\Guide\Models\Topic;

/**
 * facts_hash eines Themas (docs/guide-system.md §4.1): sha1 ueber die
 * sortierten Tupel (key, value, unit, valid_from) aller aktuellen Fakten.
 *
 * Label, Quelle und Zeitstempel gehen bewusst nicht ein; nur eine inhaltliche
 * Aenderung am Fakten-Set aendert den Hash (Grundlage fuer #9).
 */
final class FactsHasher
{
    /**
     * @param  iterable<Fact>  $facts
     */
    public function hash(iterable $facts): string
    {
        $tuples = [];

        foreach ($facts as $fact) {
            $tuples[] = (string) json_encode([
                (string) $fact->key,
                (string) $fact->value,
                (string) ($fact->unit ?? ''),
                $fact->valid_from?->toDateString() ?? '',
            ], JSON_UNESCAPED_UNICODE);
        }

        sort($tuples, SORT_STRING);

        return sha1(implode("\n", $tuples));
    }

    /**
     * Berechnet den Hash neu und speichert ihn am Thema.
     */
    public function refresh(Topic $topic): string
    {
        $hash = $topic->calculateFactsHash();

        if ($topic->facts_hash !== $hash) {
            $topic->forceFill(['facts_hash' => $hash])->save();
        }

        return $hash;
    }
}
