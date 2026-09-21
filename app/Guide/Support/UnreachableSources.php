<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Models\Topic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * Nicht erreichbare Quellen im Dashboard (#28, design/guide-dashboard.md §5.7).
 *
 * Angezeigt wird eine Quelle nur, solange `broken_at` gesetzt ist und sie
 * noch einen aktuellen Fakt belegt — hat die Recherche Ersatz gefunden, haengt
 * kein aktueller Fakt mehr an ihr und der Hinweis verschwindet von selbst.
 * Wortlaut immer "nicht erreichbar" (§5.7.4). Erwartet einen initialisierten
 * Tenant-Kontext (TopicDirectory/ViewTopic lesen ueber $tenant->run()).
 *
 * Fehlen die Link-Check-Spalten (Tenant-Migration aus #11 noch nicht
 * gelaufen), gilt keine Quelle als nicht erreichbar — die Themenliste des
 * Portals soll daran nicht scheitern.
 */
class UnreachableSources
{
    /**
     * Quellen mit broken_at, die einen aktuellen Fakt belegen.
     *
     * @return Builder<Source>
     */
    public static function query(): Builder
    {
        return Source::query()
            ->whereNotNull('broken_at')
            ->whereHas('facts', fn (Builder $query) => $query->where('is_current', true));
    }

    /**
     * Anzahl je Thema (Themenliste §5.7.2): guide_topic_id => Anzahl.
     *
     * @return array<int, int>
     */
    public static function countsByTopic(): array
    {
        try {
            return self::countQuery();
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return array<int, int>
     */
    private static function countQuery(): array
    {
        return static::query()
            ->selectRaw('guide_topic_id, count(*) as aggregate')
            ->groupBy('guide_topic_id')
            ->toBase()
            ->pluck('aggregate', 'guide_topic_id')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * Anzeigezeilen eines Themas: Herausgeber, URL (als Text), Statuscode,
     * seit, betroffene Fakten.
     *
     * @return list<array{id: int, url: string, label: string, publisher: string|null, code: int|null, since: string|null, checked_at: string|null, facts: list<string>}>
     */
    public static function forTopic(Topic $topic): array
    {
        try {
            return self::topicQuery($topic);
        } catch (QueryException) {
            return [];
        }
    }

    /**
     * @return list<array{id: int, url: string, label: string, publisher: string|null, code: int|null, since: string|null, checked_at: string|null, facts: list<string>}>
     */
    private static function topicQuery(Topic $topic): array
    {
        $timezone = (string) config('guide.timezone');

        return static::query()
            ->where('guide_topic_id', $topic->getKey())
            ->with(['facts' => fn ($query) => $query->where('is_current', true)->orderBy('label')])
            ->orderBy('broken_at')
            ->get()
            ->map(fn (Source $source): array => [
                'id' => (int) $source->getKey(),
                'url' => (string) $source->url,
                'label' => (string) ($source->title ?? $source->publisher ?? $source->url),
                'publisher' => $source->publisher,
                'code' => $source->link_status_code,
                'since' => $source->broken_at?->timezone($timezone)->format('d.m.'),
                'checked_at' => $source->link_checked_at?->timezone($timezone)->format('d.m., H:i'),
                'facts' => $source->facts->map(fn (Fact $fact): string => (string) ($fact->label ?: $fact->key))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Zeile unter der Kategorie in der Themenliste (§5.7.2).
     */
    public static function countLine(int $count): string
    {
        return trans_choice('{1} 1 Quelle nicht erreichbar|[2,*] :count Quellen nicht erreichbar', $count, ['count' => $count]);
    }

    /**
     * Grund in der Fortschrittszeile unter der Laufpille (§5.7.5 C), nur bei
     * wartendem oder laufendem Lauf eines Themas mit nicht erreichbarer Quelle.
     */
    public static function progressReason(): string
    {
        return __('Quelle ersetzen');
    }

    /**
     * Band im Thema-Detail (§5.7.3), erster Satz.
     *
     * @param  list<array{code: int|null, since: string|null}>  $sources
     */
    public static function headline(array $sources): string
    {
        if (count($sources) !== 1) {
            return __(':count Quellen sind nicht erreichbar.', ['count' => count($sources)]);
        }

        $source = $sources[0];
        $detail = collect([$source['code'], $source['since'] !== null ? __('seit :date', ['date' => $source['since']]) : null])
            ->filter()
            ->implode(' ');

        return $detail !== ''
            ? __('1 Quelle ist nicht erreichbar (:detail).', ['detail' => $detail])
            : __('1 Quelle ist nicht erreichbar.');
    }

    /**
     * Anlass im Pruefblatt (§5.7.3): Klartext, Statuscode nur in Klammern.
     *
     * @param  list<array{label: string, code: int|null, facts: list<string>}>  $sources
     */
    public static function reviewReason(array $sources): ?string
    {
        if ($sources === []) {
            return null;
        }

        $facts = collect($sources)->pluck('facts')->flatten()->unique()->implode(', ');

        if (count($sources) === 1) {
            $source = $sources[0];
            $reason = __('Das Qualitätsgate hat nicht freigegeben: Die Quelle ‚:label‘ ist nicht erreichbar:code, und die Recherche hat keinen Ersatz gefunden.', [
                'label' => $source['label'],
                'code' => $source['code'] !== null ? " ({$source['code']})" : '',
            ]);
        } else {
            $names = collect($sources)
                ->map(fn (array $source): string => "‚{$source['label']}‘".($source['code'] !== null ? " ({$source['code']})" : ''))
                ->implode(', ');
            $reason = __('Das Qualitätsgate hat nicht freigegeben: Die Quellen :names sind nicht erreichbar, und die Recherche hat keinen Ersatz gefunden.', ['names' => $names]);
        }

        return $facts !== '' ? $reason.' '.__('Betroffen: :facts.', ['facts' => $facts]) : $reason;
    }

    /**
     * Zeilen unter einer im Lauf ersetzenden Quelle (§5.7.5 A.2): hoechstens
     * drei „ersetzt ‚…‘ (nicht erreichbar)“, danach „und n weitere“.
     *
     * @param  list<string>  $labels  Titel, sonst Herausgeber, sonst URL der alten Quellen
     * @return list<string>
     */
    public static function replacedLines(array $labels): array
    {
        $lines = array_map(
            fn (string $label): string => __('ersetzt ‚:label‘ (nicht erreichbar)', ['label' => $label]),
            array_slice($labels, 0, 3),
        );

        if (count($labels) > 3) {
            $lines[] = __('und :count weitere', ['count' => count($labels) - 3]);
        }

        return $lines;
    }

    /**
     * Bestaetigung beim Freigeben ohne Ersatz (§5.7.3).
     */
    public static function approveConfirmation(): string
    {
        return __('Die Quelle bleibt unverlinkt stehen, bis eine Recherche Ersatz findet.');
    }

    /**
     * Naechster Tageslauf (guide.run_window_start): heute, solange das
     * Laufzeitfenster noch nicht begonnen hat, sonst morgen.
     */
    public static function nextDailyRun(?Carbon $now = null): Carbon
    {
        $timezone = (string) config('guide.timezone');
        $now = ($now ?? Carbon::now())->copy()->setTimezone($timezone);
        [$hour, $minute] = array_map('intval', explode(':', (string) config('guide.run_window_start', '02:00')) + [1 => 0]);
        $start = $now->copy()->setTime($hour, $minute);

        return $now->lt($start) ? $start : $start->addDay();
    }
}
