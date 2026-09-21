<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\TopicStatus;
use App\Guide\Events\OutlineLocked;
use App\Guide\Models\Category;
use App\Guide\Models\Topic;
use App\Guide\Support\OutlineDraft;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Aenderungen an Ratgeber-Themen aus dem Dashboard (#15): Einzel- und
 * Sammelaktionen der Themenliste, Gliederung bestaetigen, Gliederungs-Editor.
 *
 * Arbeitet auf Schluesseln "<tenant-id>-<topic-id>" (TopicDirectory::key) und
 * je Portal in $tenant->run(). Statuswechsel gehen ausschliesslich ueber
 * TopicStatus::canTransitionTo(); ein Thema, fuer das der Wechsel nicht
 * erlaubt ist, wird uebersprungen und gezaehlt. Nach jeder Aenderung wird der
 * netzwerkweite Stand verworfen.
 */
class TopicAdminService
{
    public function __construct(private readonly TopicDirectory $directory) {}

    /**
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function pause(iterable $keys): array
    {
        return $this->transition($keys, TopicStatus::PAUSED);
    }

    /**
     * Pausierte Themen fortsetzen; Entwuerfe und offene Gliederungen nur,
     * wenn ihre Gliederung schon gesperrt ist.
     *
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function activate(iterable $keys): array
    {
        return $this->each($keys, function (Topic $topic): bool {
            if (! $topic->status->canTransitionTo(TopicStatus::ACTIVE) || ! $topic->isOutlineLocked()) {
                return false;
            }

            // Nach automatischer Pause (#9) zaehlt der Fehlerzaehler neu.
            $topic->update(['status' => TopicStatus::ACTIVE, 'consecutive_failures' => 0]);

            return true;
        });
    }

    /**
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function archive(iterable $keys): array
    {
        return $this->transition($keys, TopicStatus::ARCHIVED);
    }

    /**
     * Kategorie ueber ihren Slug setzen; Portale ohne diese Kategorie werden
     * uebersprungen.
     *
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function setCategory(iterable $keys, string $categorySlug): array
    {
        $categories = [];

        return $this->each($keys, function (Topic $topic, Tenant $tenant) use ($categorySlug, &$categories): bool {
            $categoryId = $categories[$tenant->getKey()] ??= Category::query()->where('slug', $categorySlug)->value('id');

            if ($categoryId === null) {
                return false;
            }

            $topic->update(['guide_category_id' => $categoryId]);

            return true;
        });
    }

    /**
     * Pruefabstand in Tagen, null = Vorgabe aus der Konfiguration. Die naechste
     * Faelligkeit wird vom letzten Pruefdatum aus neu gerechnet.
     *
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function setInterval(iterable $keys, ?int $days): array
    {
        return $this->each($keys, function (Topic $topic) use ($days): bool {
            $topic->refresh_interval_days = $days;

            if ($topic->last_checked_at !== null) {
                $topic->next_due_at = $topic->last_checked_at->copy()->addDays($topic->refreshIntervalDays());
            }

            $topic->save();

            return true;
        });
    }

    /**
     * Sperrt die vorliegende Gliederung unveraendert ("Vorschlag uebernehmen",
     * "Alle bestaetigen"). Themen ohne Gliederung oder mit bereits gesperrter
     * werden uebersprungen.
     *
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    public function lockOutlines(iterable $keys): array
    {
        return $this->each($keys, function (Topic $topic, Tenant $tenant): bool {
            if ($topic->isOutlineLocked() || ($topic->outline_json ?? []) === []) {
                return false;
            }

            $this->lock($topic, $tenant, $topic->outline_json);

            return true;
        });
    }

    /**
     * Speichert die Gliederung aus dem Editor, ohne sie zu sperren.
     *
     * @param  array<int, array<string, mixed>>  $rows  Zeilen aus OutlineDraft::rows()
     */
    public function saveOutline(Tenant $tenant, int $topicId, array $rows): void
    {
        $this->one($tenant, $topicId, function (Topic $topic) use ($rows): void {
            if ($topic->isOutlineLocked()) {
                throw new LogicException('Eine gesperrte Gliederung muss erst entsperrt werden.');
            }

            $topic->update(['outline_json' => OutlineDraft::toOutline($this->knownIds($topic, $rows))]);
        });
    }

    /**
     * Speichert und sperrt die Gliederung aus dem Editor. Vorher muessen die
     * Regeln aus OutlineDraft::violations() erfuellt sein.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function saveAndLockOutline(Tenant $tenant, int $topicId, array $rows): void
    {
        if (OutlineDraft::violations($rows) !== []) {
            throw new LogicException('Die Gliederung verletzt die Regeln und kann nicht gesperrt werden.');
        }

        $this->one($tenant, $topicId, function (Topic $topic) use ($tenant, $rows): void {
            if ($topic->isOutlineLocked()) {
                throw new LogicException('Die Gliederung ist bereits gesperrt.');
            }

            $this->lock($topic, $tenant, OutlineDraft::toOutline($this->knownIds($topic, $rows)));
        });
    }

    /**
     * Entsperrt die Gliederung bewusst. Die ids bleiben in outline_json
     * stehen; hat das Thema einen Artikel, schreibt der naechste Lauf nach
     * dem erneuten Sperren den ganzen Artikel neu (OutlineLocked::$rewrite).
     */
    public function unlockOutline(Tenant $tenant, int $topicId): void
    {
        $this->one($tenant, $topicId, function (Topic $topic): void {
            $topic->update(['outline_locked_at' => null]);
        });
    }

    /**
     * Startet `guide:run` je Portal und liefert die Ergebnisse je Thema.
     *
     * @param  iterable<int, string>  $keys
     * @return list<array{key: string, outcome: string, run_id: int|null, reason: string|null}>
     */
    public function runNow(iterable $keys): array
    {
        $results = [];

        foreach (TopicDirectory::groupKeys($keys) as $tenantId => $topicIds) {
            if ($this->directory->tenant($tenantId) === null) {
                continue;
            }

            Artisan::call('guide:run', [
                '--tenant' => (string) $tenantId,
                '--topic' => array_map('strval', $topicIds),
                '--json' => true,
            ]);

            $decoded = json_decode(trim(Artisan::output()), true);

            foreach (is_array($decoded) ? $decoded : [] as $result) {
                $results[] = [
                    'key' => TopicDirectory::key($tenantId, (int) $result['topic']),
                    'outcome' => (string) $result['outcome'],
                    'run_id' => $result['run_id'] !== null ? (int) $result['run_id'] : null,
                    'reason' => $result['reason'] ?? null,
                ];
            }
        }

        $this->directory->forget();

        return $results;
    }

    /**
     * Eine id aus dem Editor gilt nur, wenn die gespeicherte Gliederung sie
     * kennt; alles andere ist eine neue Zeile. So kann kein Formular fremde
     * oder doppelte Sprungziele einschleusen.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function knownIds(Topic $topic, array $rows): array
    {
        $known = array_flip($topic->outlineSectionIds());
        $used = [];

        return array_map(function (array $row) use ($known, &$used): array {
            $id = (string) ($row['id'] ?? '');
            $row['id'] = isset($known[$id]) && ! isset($used[$id]) ? $id : null;

            if ($row['id'] !== null) {
                $used[$id] = true;
            }

            return $row;
        }, array_values($rows));
    }

    /**
     * @param  array<int, mixed>|null  $outline
     */
    private function lock(Topic $topic, Tenant $tenant, ?array $outline): void
    {
        // Gesperrt wird nur eine offene Gliederung. Hat das Thema schon einen
        // Artikel, war das ein bewusstes Entsperren: Der Artikel wird neu
        // geschrieben.
        $rewrite = $topic->article_id !== null;

        $topic->outline_json = $outline;
        $topic->outline_locked_at = Carbon::now();

        if ($topic->status->canTransitionTo(TopicStatus::ACTIVE)
            && in_array($topic->status, [TopicStatus::DRAFT, TopicStatus::OUTLINE_PENDING], true)) {
            $topic->status = TopicStatus::ACTIVE;
        }

        $topic->save();

        OutlineLocked::dispatch($tenant->getKey(), (int) $topic->getKey(), $rewrite);
    }

    /**
     * @param  iterable<int, string>  $keys
     * @return array{done: int, skipped: int}
     */
    private function transition(iterable $keys, TopicStatus $target): array
    {
        return $this->each($keys, function (Topic $topic) use ($target): bool {
            if (! $topic->status->canTransitionTo($target)) {
                return false;
            }

            $topic->update(['status' => $target]);

            return true;
        });
    }

    /**
     * @param  iterable<int, string>  $keys
     * @param  callable(Topic, Tenant): bool  $callback  true = geaendert
     * @return array{done: int, skipped: int}
     */
    private function each(iterable $keys, callable $callback): array
    {
        $done = 0;
        $skipped = 0;

        foreach (TopicDirectory::groupKeys($keys) as $tenantId => $topicIds) {
            $tenant = $this->directory->tenant($tenantId);

            if ($tenant === null) {
                $skipped += count($topicIds);

                continue;
            }

            try {
                [$tenantDone, $tenantSkipped] = $tenant->run(function () use ($tenant, $topicIds, $callback): array {
                    $done = 0;
                    $topics = Topic::query()->whereKey($topicIds)->get();

                    foreach ($topics as $topic) {
                        if ($callback($topic, $tenant)) {
                            $done++;
                        }
                    }

                    return [$done, count($topicIds) - $done];
                });

                $done += $tenantDone;
                $skipped += $tenantSkipped;
            } catch (Throwable $exception) {
                Log::warning('Ratgeber-Dashboard: Aenderung in Portal fehlgeschlagen.', [
                    'tenant' => $tenantId,
                    'message' => $exception->getMessage(),
                ]);

                $skipped += count($topicIds);
            }
        }

        $this->directory->forget();

        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * @param  callable(Topic): void  $callback
     */
    private function one(Tenant $tenant, int $topicId, callable $callback): void
    {
        $tenant->run(function () use ($topicId, $callback): void {
            $callback(Topic::query()->findOrFail($topicId));
        });

        $this->directory->forget();
    }
}
