<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\TopicRun;

/**
 * Quote "geprueft / faellig" (#38 G7, design/guide-dashboard.md §11.2), eine
 * Regel fuer Heute, Tagesbericht und Mail.
 *
 * Faellig (due) = Themen, die die Faelligkeitsauswahl am Tag ermittelt hat:
 * alle Themen mit Lauf an diesem Tag plus die wegen Budget oder
 * Neuanlage-Grenze verschobenen (Alarm topics_deferred), jedes Thema einmal.
 * Geprueft = neu + aktualisiert + unveraendert + zur Pruefung (§3.2).
 * Die Quote wird abgerundet, damit 94,6 % nie als 95 % erscheint.
 */
final class CheckedQuote
{
    public const TARGET_PERCENT = 95;

    /**
     * Faellige Themen eines Tages. Laeuft im Tenant-Kontext.
     */
    public static function due(string $date): int
    {
        $topicIds = TopicRun::query()
            ->whereDate('run_date', $date)
            ->distinct()
            ->pluck('guide_topic_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $deferred = (array) (GuideAlert::query()
            ->where('key', GuideAlert::KEY_TOPICS_DEFERRED)
            ->where('tenant_id', tenant()?->getKey())
            ->whereDate('for_date', $date)
            ->value('context_json')['topics'] ?? []);

        foreach ($deferred as $row) {
            if (is_array($row) && isset($row['guide_topic_id'])) {
                $topicIds[] = (int) $row['guide_topic_id'];
            }
        }

        return count(array_unique($topicIds));
    }

    /**
     * Ganze Prozent, abgerundet; null bei due = 0.
     */
    public static function percent(int $checked, int $due): ?int
    {
        return $due > 0 ? intdiv(max(0, $checked) * 100, $due) : null;
    }

    /**
     * Anteil fuer den gespeicherten Bericht; null bei due = 0.
     */
    public static function ratio(int $checked, int $due): ?float
    {
        return $due > 0 ? round($checked / $due, 4) : null;
    }

    public static function belowTarget(?int $percent): bool
    {
        return $percent !== null && $percent < self::TARGET_PERCENT;
    }

    /**
     * "96 %" bzw. "–" bei due = 0.
     */
    public static function label(?int $percent): string
    {
        return $percent !== null ? "{$percent} %" : '–';
    }
}
