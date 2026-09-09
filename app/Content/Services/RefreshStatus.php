<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aktualisierungsstand einer Fassung fuer die Oberflaeche (#99).
 *
 * Der RefreshSelector entscheidet, was aktualisiert wird; diese Klasse
 * erklaert der Redaktion, warum ein Artikel gerade nicht drankommt. Sie
 * schreibt nichts und aendert keine Auswahl (#93 bleibt unberuehrt).
 *
 * Genau ein Zustand je Fassung, in dieser Rangfolge:
 *
 *  1. `marked`    — `needs_refresh` steht, der Collector hat vorgemerkt.
 *  2. `cooldown`  — eine abgeschlossene Kindfassung liegt innerhalb der
 *                   Sperrfrist (`content.refresh.cooldown_days`).
 *  3. `running`   — eine Kindfassung ist unterwegs, aber noch nicht live.
 *  4. `attempted` — der letzte Lauf hat nichts geaendert
 *                   (`refresh_reason_json.last_attempt_result`).
 *  5. sonst null  — der Streifen entfaellt ersatzlos.
 *
 * Zur Rangfolge: die Sperrfrist steht vor der laufenden Aktualisierung, wie
 * im Ticket vorgegeben. Damit beide Zustaende erreichbar bleiben, zaehlt fuer
 * die Sperrfrist nur eine Kindfassung, die nicht mehr laeuft — sonst faende
 * eine gerade erzeugte Fassung immer zuerst die Sperrfrist und der Zustand
 * "in Arbeit" waere tot.
 *
 * Alle Abfragen laufen im Tenant-Kontext des Aufrufers. Fuer Listen gibt es
 * `forDrafts()`: eine Abfrage je Portal statt zwei je Zeile.
 */
class RefreshStatus
{
    public const STATE_MARKED = 'marked';

    public const STATE_COOLDOWN = 'cooldown';

    public const STATE_RUNNING = 'running';

    public const STATE_ATTEMPTED = 'attempted';

    /**
     * Zustand einer einzelnen Fassung.
     *
     * @return array<string, mixed>|null
     */
    public function for(ArticleDraft $draft): ?array
    {
        return $this->forDrafts([$draft])[(int) $draft->getKey()] ?? null;
    }

    /**
     * Zustaende mehrerer Fassungen desselben Portals.
     *
     * @param  iterable<int, ArticleDraft>  $drafts
     * @return array<int, array<string, mixed>|null> Entwurfs-ID => Zustand
     */
    public function forDrafts(iterable $drafts): array
    {
        /** @var Collection<int, ArticleDraft> $list */
        $list = $drafts instanceof Collection ? $drafts : collect($drafts);

        if ($list->isEmpty()) {
            return [];
        }

        $versions = $this->versionIndex($list);
        $out = [];

        foreach ($list as $draft) {
            $out[(int) $draft->getKey()] = $this->resolve($draft, $versions);
        }

        return $out;
    }

    /**
     * Herkunft einer Kindfassung: welche Fassung war die Elternfassung.
     *
     * Titel und Slug sind identisch; ohne diese Angabe sind Eltern- und
     * Kindfassung in einer Liste nicht auseinanderzuhalten.
     *
     * @return array{id: int, title: string, published_at: ?string}|null
     */
    public function origin(ArticleDraft $draft): ?array
    {
        if ($draft->parent_draft_id === null) {
            return null;
        }

        $parent = $draft->relationLoaded('parentDraft')
            ? $draft->parentDraft
            : ArticleDraft::query()->find((int) $draft->parent_draft_id);

        if (! $parent instanceof ArticleDraft) {
            return null;
        }

        return [
            'id' => (int) $parent->getKey(),
            'title' => (string) $parent->title,
            'published_at' => $parent->published_at?->format('d.m.Y'),
        ];
    }

    public function cooldownDays(): int
    {
        return max(0, (int) config('content.refresh.cooldown_days', 30));
    }

    /*
    |--------------------------------------------------------------------------
    | Zustandsbestimmung
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{by_article: array<int, list<ArticleDraft>>, by_parent: array<int, list<ArticleDraft>>}  $versions
     * @return array<string, mixed>|null
     */
    private function resolve(ArticleDraft $draft, array $versions): ?array
    {
        $reason = (array) ($draft->refresh_reason_json ?? []);

        if ((bool) $draft->needs_refresh) {
            return $this->state(
                self::STATE_MARKED,
                'marked',
                __('Für Aktualisierung vorgemerkt: :reason', ['reason' => $this->markedReason($reason)]),
                __('Vorgemerkt'),
            );
        }

        $children = $this->childrenOf($draft, $versions);
        $limit = CarbonImmutable::now()->subDays($this->cooldownDays());
        $self = (int) $draft->getKey();

        // Die Fassung selbst zaehlt fuer die Sperrfrist mit, sofern sie eine
        // Aktualisierung ist: nach einem Lauf ist die Kindfassung die
        // veroeffentlichte, und genau sie haelt den Artikel gesperrt (#93).
        // Ohne diese Zeile staende die aktuelle Fassung ohne Streifen da.
        $done = $this->latest($children, fn (ArticleDraft $child): bool => ! $this->isRunning($child));

        if ($done !== null && $done->created_at !== null && $done->created_at->greaterThanOrEqualTo($limit)) {
            $since = $done->published_at ?? $done->created_at;
            $until = CarbonImmutable::parse($done->created_at)->addDays($this->cooldownDays());

            return $this->state(
                self::STATE_COOLDOWN,
                'neutral',
                __('Zuletzt aktualisiert am :date, wieder wählbar ab :until', [
                    'date' => $since->format('d.m.Y'),
                    'until' => $until->format('d.m.Y'),
                ]),
                __('Sperrfrist'),
            );
        }

        // Beim laufenden Lauf dagegen ist die eigene Fassung uninteressant —
        // gefragt ist eine ANDERE Fassung, die gerade entsteht.
        $running = $this->latest(
            $children,
            fn (ArticleDraft $child): bool => (int) $child->getKey() !== $self && $this->isRunning($child),
        );

        if ($running !== null) {
            return $this->state(
                self::STATE_RUNNING,
                'running',
                __('Eine Aktualisierung ist in Arbeit'),
                __('In Arbeit'),
                [
                    'child_id' => (int) $running->getKey(),
                    'child_status' => $running->display_status->value,
                    'child_label' => __('Neue Fassung ansehen'),
                ],
            );
        }

        $result = trim((string) ($reason['last_attempt_result'] ?? ''));
        $attemptedAt = $this->date($reason['last_attempt_at'] ?? null);

        if ($result !== '' && $attemptedAt !== null && $attemptedAt->greaterThanOrEqualTo($limit)) {
            return $this->state(
                self::STATE_ATTEMPTED,
                'neutral',
                __('Lauf am :date hat nichts geändert: :reason', [
                    'date' => $attemptedAt->format('d.m.Y'),
                    'reason' => $this->attemptReason($result),
                ]),
                __('ohne Änderung'),
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function state(string $state, string $tone, string $text, string $pill, array $extra = []): array
    {
        // $extra steht links: bei doppeltem Schluessel gewinnt der linke
        // Operand von `+`, die Vorgaben rechts fuellen nur die Luecken.
        return $extra + [
            'state' => $state,
            // Farbton der Pille und des Streifens. Die Namen bilden auf die
            // Modifikatoren in resources/css/content/theme.css ab; neue
            // Farbtokens entstehen dabei nicht.
            'tone' => $tone,
            'text' => $text,
            'pill' => $pill,
            'child_id' => null,
            'child_status' => null,
            'child_label' => null,
        ];
    }

    /**
     * Eine Kindfassung laeuft, solange sie weder live noch abgeschlossen ist.
     */
    private function isRunning(ArticleDraft $child): bool
    {
        if ($child->published_at !== null || $child->withdrawn_at !== null) {
            return false;
        }

        // `status` ist auf DraftStatus gecastet; der Vergleich laeuft
        // deshalb ueber den Fall, nicht ueber die Zeichenkette.
        return $child->status !== DraftStatus::FAILED
            && $child->status !== DraftStatus::PUBLISHED;
    }

    /**
     * @param  list<ArticleDraft>  $children
     * @param  callable(ArticleDraft): bool  $filter
     */
    private function latest(array $children, callable $filter): ?ArticleDraft
    {
        $found = null;

        foreach ($children as $child) {
            if (! $filter($child)) {
                continue;
            }

            if ($found === null || ($child->created_at?->getTimestamp() ?? 0) > ($found->created_at?->getTimestamp() ?? 0)) {
                $found = $child;
            }
        }

        return $found;
    }

    /*
    |--------------------------------------------------------------------------
    | Kindfassungen
    |--------------------------------------------------------------------------
    */

    /**
     * Alle Fassungen mit Elternbezug, die zu den uebergebenen Entwuerfen
     * gehoeren — eine Abfrage statt einer je Zeile.
     *
     * @param  Collection<int, ArticleDraft>  $drafts
     * @return array{by_article: array<int, list<ArticleDraft>>, by_parent: array<int, list<ArticleDraft>>}
     */
    private function versionIndex(Collection $drafts): array
    {
        $articleIds = $drafts->pluck('article_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
        $draftIds = $drafts->map(fn (ArticleDraft $draft): int => (int) $draft->getKey())->all();

        $query = ArticleDraft::query()
            ->whereNotNull('parent_draft_id')
            ->select(['id', 'article_id', 'parent_draft_id', 'status', 'created_at', 'published_at', 'withdrawn_at']);

        if ($articleIds === []) {
            $query->whereIn('parent_draft_id', $draftIds);
        } else {
            $query->where(function ($inner) use ($articleIds, $draftIds): void {
                $inner->whereIn('article_id', $articleIds)
                    ->orWhereIn('parent_draft_id', $draftIds);
            });
        }

        $byArticle = [];
        $byParent = [];

        foreach ($query->get() as $child) {
            if ($child->article_id !== null) {
                $byArticle[(int) $child->article_id][] = $child;
            }

            $byParent[(int) $child->parent_draft_id][] = $child;
        }

        return ['by_article' => $byArticle, 'by_parent' => $byParent];
    }

    /**
     * Die Aktualisierungsfassungen eines Artikels — die Frage gilt dem
     * Artikel, nicht der einzelnen Fassung (#93). Der Entwurf selbst ist
     * enthalten, wenn er selbst eine Aktualisierung ist; wer ihn nicht
     * gebrauchen kann, filtert ihn beim Auswerten heraus.
     *
     * @param  array{by_article: array<int, list<ArticleDraft>>, by_parent: array<int, list<ArticleDraft>>}  $versions
     * @return list<ArticleDraft>
     */
    private function childrenOf(ArticleDraft $draft, array $versions): array
    {
        $id = (int) $draft->getKey();

        return $draft->article_id !== null
            ? array_values($versions['by_article'][(int) $draft->article_id] ?? [])
            : array_values($versions['by_parent'][$id] ?? []);
    }

    /*
    |--------------------------------------------------------------------------
    | Begruendungen
    |--------------------------------------------------------------------------
    */

    /**
     * Klartext zur Marke des Metrik-Collectors (#23). Fehlt das Protokoll,
     * bleibt die Marke trotzdem gueltig — dann steht dort der Oberbegriff.
     *
     * @param  array<string, mixed>  $reason
     */
    private function markedReason(array $reason): string
    {
        foreach ((array) ($reason['reasons'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            return match ((string) ($entry['type'] ?? '')) {
                'position_drop' => __('Position von :from auf :to gefallen', [
                    'from' => $this->number($entry['from'] ?? null),
                    'to' => $this->number($entry['to'] ?? null),
                ]),
                'ctr_drop' => __('Klickrate deutlich eingebrochen'),
                default => __('Sichtbarkeit rückläufig'),
            };
        }

        return __('Sichtbarkeit rückläufig');
    }

    /**
     * Der Vermerk stammt aus RefreshArticleJob::markAttempted() und ist als
     * Protokolltext geschrieben; hier steht er in Satzform.
     */
    private function attemptReason(string $result): string
    {
        return match ($result) {
            'ohne Themenkandidat' => __('kein Themenkandidat vorhanden'),
            'kein betroffener Abschnitt gefunden' => __('kein betroffener Abschnitt gefunden'),
            'Modell hat nichts geaendert' => __('das Modell hat nichts geändert'),
            default => $result,
        };
    }

    private function number(mixed $value): string
    {
        return $value === null ? '?' : number_format((float) $value, 1, ',', '.');
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
