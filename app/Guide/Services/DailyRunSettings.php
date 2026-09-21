<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\TopicStatus;
use App\Guide\Models\Category;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\Topic;
use App\Guide\Support\Usd;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Einstellungen › Tageslauf (#33, design/guide-dashboard.md §9.1): globaler
 * Schalter, Pruefabstand je Kategorie mit Kostenfolge, Liste der Pausen.
 *
 * Pruefabstand je Kategorie: guide_categories.refresh_interval_days je
 * Portal; das Dashboard zeigt eine Zeile je Kategorie-Slug und schreibt in
 * alle Portale der Auswahl (wie Umbenennen, §6). Ein eigener Pruefabstand am
 * Thema geht vor und wird nicht angefasst.
 *
 * Kostenfolge je Kategorie: aktive Themen ohne eigenen Abstand
 * × guide.estimates.check_usd × (1 / Abstand − 1 / Vorgabe) — der Mehr- oder
 * Minderbetrag je Tag gegenueber der Vorgabe aus der Konfiguration.
 */
class DailyRunSettings
{
    /** Auswahl im Dashboard (§9.1). */
    public const INTERVALS = [1, 3, 7, 14];

    public function __construct(
        private readonly TopicDirectory $directory,
        private readonly RunOverviewService $overview,
    ) {}

    /**
     * @return array{paused: bool, at: string|null, by: string|null}
     */
    public function state(): array
    {
        if (! GuideRunState::isPaused()) {
            return ['paused' => false, 'at' => null, 'by' => null];
        }

        $state = GuideRunState::current();

        return ['paused' => true, 'at' => $state->paused_at?->toIso8601String(), 'by' => $state->paused_by_name];
    }

    public function pause(?User $user): void
    {
        GuideRunState::current()->pause($user);

        Log::info('Ratgeber: Tageslauf global pausiert.', ['user_id' => $user?->getKey()]);

        $this->forget();
    }

    public function resume(): void
    {
        GuideRunState::current()->resume();

        Log::info('Ratgeber: Tageslauf global fortgesetzt.');

        $this->forget();
    }

    public static function defaultInterval(): int
    {
        return max(1, (int) config('guide.schedule.probe_interval_days', 7));
    }

    /**
     * Mehr- (positiv) oder Minderkosten je Tag gegenueber der Vorgabe.
     */
    public static function dailyCostDelta(int $topics, ?int $interval): float
    {
        $interval = max((int) config('guide.schedule.min_probe_interval_days', 1), $interval ?? self::defaultInterval());

        return round($topics * (float) config('guide.estimates.check_usd', 0) * (1 / $interval - 1 / self::defaultInterval()), 2);
    }

    public static function formatDelta(float $delta): string
    {
        if (abs($delta) < 0.005) {
            return __('± 0,00 USD/Tag');
        }

        return ($delta > 0 ? '+ ' : '− ').Usd::format(abs($delta)).__('/Tag');
    }

    /**
     * Eine Zeile je Kategorie-Slug der Portale: Name, Portale, aktive Themen
     * ohne eigenen Pruefabstand, gesetzter Abstand (null = Vorgabe,
     * 'gemischt' = Portale weichen voneinander ab).
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{slug: string, name: string, portals: int, topics: int, interval: int|string|null}>
     */
    public function categories(Collection $tenants): array
    {
        $rows = [];

        foreach ($tenants as $tenant) {
            $categories = $this->read($tenant, fn (): array => Category::query()
                ->ordered()
                ->withCount(['topics as active_topics' => fn ($query) => $query
                    ->where('status', TopicStatus::ACTIVE->value)
                    ->whereNull('refresh_interval_days')])
                ->get(['id', 'name', 'slug', 'refresh_interval_days'])
                ->map(fn (Category $category): array => [
                    'slug' => (string) $category->slug,
                    'name' => (string) $category->name,
                    'topics' => (int) $category->getAttribute('active_topics'),
                    'interval' => $category->refresh_interval_days,
                ])
                ->all()) ?? [];

            foreach ($categories as $category) {
                $row = $rows[$category['slug']] ?? ['slug' => $category['slug'], 'name' => $category['name'], 'portals' => 0, 'topics' => 0, 'intervals' => []];
                $row['portals']++;
                $row['topics'] += $category['topics'];
                $row['intervals'][] = $category['interval'];
                $rows[$category['slug']] = $row;
            }
        }

        return array_values(array_map(function (array $row): array {
            $intervals = array_values(array_unique($row['intervals'], SORT_REGULAR));
            unset($row['intervals']);

            return [...$row, 'interval' => count($intervals) === 1 ? $intervals[0] : 'gemischt'];
        }, $rows));
    }

    /**
     * Setzt den Pruefabstand je Slug in allen Portalen der Auswahl und
     * rechnet die naechste Faelligkeit der betroffenen Themen vom letzten
     * Pruefdatum aus neu (wie TopicAdminService::setInterval()).
     *
     * @param  Collection<int, Tenant>  $tenants
     * @param  array<string, int|null>  $intervals  slug => Tage, null = Vorgabe
     * @return int Zahl der geaenderten Kategorien ueber alle Portale
     */
    public function setIntervals(Collection $tenants, array $intervals): int
    {
        $changed = 0;

        foreach ($tenants as $tenant) {
            $changed += (int) $tenant->run(function () use ($intervals): int {
                $count = 0;

                foreach ($intervals as $slug => $days) {
                    $days = in_array($days, self::INTERVALS, true) ? $days : null;
                    $category = Category::query()->where('slug', (string) $slug)->first();

                    if ($category === null || $category->refresh_interval_days === $days) {
                        continue;
                    }

                    $category->forceFill(['refresh_interval_days' => $days])->save();
                    $count++;

                    Topic::query()
                        ->where('guide_category_id', $category->getKey())
                        ->whereNull('refresh_interval_days')
                        ->whereNotNull('last_checked_at')
                        ->with('category')
                        ->each(function (Topic $topic): void {
                            $topic->forceFill(['next_due_at' => $topic->last_checked_at?->copy()->addDays($topic->refreshIntervalDays())])->save();
                        });
                }

                return $count;
            });
        }

        $this->forget();

        return $changed;
    }

    /**
     * Aktive Pausen (§9.1): global und pausierte Themen je Portal. Pausen
     * je Portal und je Kategorie gibt es im System nicht; ein nicht
     * freigeschaltetes Portal steht unter Einstellungen › Portal.
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array{kind: string, key: string|null, label: string, portal: string|null, at: string|null, by: string|null}>
     */
    public function pauses(Collection $tenants): array
    {
        $rows = [];
        $state = $this->state();

        if ($state['paused']) {
            $rows[] = ['kind' => 'global', 'key' => null, 'label' => __('Tageslauf (alle Portale)'), 'portal' => null, 'at' => $state['at'], 'by' => $state['by']];
        }

        $limit = Topic::maxConsecutiveFailures();

        foreach ($tenants as $tenant) {
            $topics = $this->read($tenant, fn (): array => Topic::query()
                ->where('status', TopicStatus::PAUSED->value)
                ->orderByDesc('updated_at')
                ->get(['id', 'question', 'consecutive_failures', 'updated_at'])
                ->map(fn (Topic $topic): array => [
                    'kind' => 'topic',
                    'key' => TopicDirectory::key($tenant->getKey(), $topic->getKey()),
                    'label' => (string) $topic->question,
                    'portal' => (string) $tenant->name,
                    'at' => $topic->updated_at?->toIso8601String(),
                    // Wer pausiert hat, wird nicht gespeichert; erkennbar ist
                    // nur die automatische Pause nach Fehlschlaegen (#9).
                    'by' => (int) $topic->consecutive_failures >= $limit
                        ? __('automatisch nach :count Fehlschlägen', ['count' => (int) $topic->consecutive_failures])
                        : null,
                ])
                ->all()) ?? [];

            array_push($rows, ...$topics);
        }

        return $rows;
    }

    private function forget(): void
    {
        $this->overview->forget();
        $this->directory->forget();
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function read(Tenant $tenant, callable $callback): mixed
    {
        try {
            return $tenant->run($callback);
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Einstellungen: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
