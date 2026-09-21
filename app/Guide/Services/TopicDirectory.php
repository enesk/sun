<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\RunDisplay;
use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Enums\TopicStatus;
use App\Guide\Models\Category;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Support\UnreachableSources;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Themen und Kategorien des Ratgebers ueber alle gewaehlten Portale (#15).
 *
 * Es gibt keine zentrale Themen-Tabelle: Jedes Portal wird ueber
 * $tenant->run() gelesen, das Ergebnis sind flache Arrays (Filament-Tabellen
 * mit eigener Datenquelle). Bei "Alle Portale" wird das Ergebnis 60 s
 * gecacht; nach jeder Aenderung im Dashboard verwirft forget() den Stand.
 * Ein einzelnes Portal wird immer frisch gelesen, damit Laufstatus und
 * Polling aktuell sind.
 *
 * Schluessel einer Zeile ist "<tenant-id>-<id>", z. B. "7-123" — so bleiben
 * Themen verschiedener Portale unterscheidbar.
 */
class TopicDirectory
{
    public const CACHE_SECONDS = 60;

    private const CACHE_PREFIX = 'guide:directory:';

    /**
     * Stand innerhalb einer Anfrage (Tabelle, Filteroptionen und Zaehler
     * lesen dieselben Zeilen).
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $memo = [];

    /**
     * @var Collection<int, Tenant>|null
     */
    private ?Collection $available = null;

    public function __construct(private readonly ContentTenantContext $context) {}

    public static function key(int|string $tenantId, int|string $id): string
    {
        return "{$tenantId}-{$id}";
    }

    /**
     * @return array{0: int, 1: int}|null [tenant-id, id]
     */
    public static function parseKey(string $key): ?array
    {
        if (! preg_match('/^(\d+)-(\d+)$/', $key, $match)) {
            return null;
        }

        return [(int) $match[1], (int) $match[2]];
    }

    /**
     * Schluessel nach Portal gruppiert: tenant-id => [ids].
     *
     * @param  iterable<int, string>  $keys
     * @return array<int, list<int>>
     */
    public static function groupKeys(iterable $keys): array
    {
        $grouped = [];

        foreach ($keys as $key) {
            $parsed = self::parseKey((string) $key);

            if ($parsed !== null) {
                $grouped[$parsed[0]][] = $parsed[1];
            }
        }

        return $grouped;
    }

    /**
     * Portale der aktuellen Auswahl (ein Portal oder alle zugaenglichen).
     *
     * @return Collection<int, Tenant>
     */
    public function tenants(): Collection
    {
        $tenants = $this->available();
        $selected = $this->context->selectedId();

        return $selected !== null ? $tenants->only([$selected]) : $tenants;
    }

    /**
     * Portal aus der Zuordnung des angemeldeten Kontos, sonst null.
     */
    public function tenant(int $tenantId): ?Tenant
    {
        return $this->available()->get($tenantId);
    }

    /**
     * Portale aus der Zuordnung des angemeldeten Kontos, nach id.
     *
     * @return Collection<int, Tenant>
     */
    private function available(): Collection
    {
        if ($this->available !== null) {
            return $this->available;
        }

        /** @var Collection<int, Tenant> $tenants */
        $tenants = collect($this->context->available()->all())
            ->filter(fn (mixed $tenant): bool => $tenant instanceof Tenant)
            ->keyBy(fn (Tenant $tenant): int => (int) $tenant->getKey());

        return $this->available = $tenants;
    }

    public function isNetworkWide(): bool
    {
        return $this->context->isNetworkWide();
    }

    /**
     * Alle Themen der Auswahl, eine Zeile je Thema und Portal.
     *
     * @return list<array<string, mixed>>
     */
    public function topics(): array
    {
        return $this->remember('topics', fn (Collection $tenants): array => $tenants
            ->flatMap(fn (Tenant $tenant): array => $this->read($tenant, fn (): array => $this->topicRows($tenant)) ?? [])
            ->values()
            ->all());
    }

    /**
     * Kategorien der Auswahl, eine Zeile je Kategorie und Portal.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return $this->remember('categories', fn (Collection $tenants): array => $tenants
            ->flatMap(fn (Tenant $tenant): array => $this->read($tenant, fn (): array => $this->categoryRows($tenant)) ?? [])
            ->values()
            ->all());
    }

    /**
     * Kategorien fuer "Alle Portale": eine Zeile je Slug (design/guide-dashboard.md §6).
     *
     * @return list<array<string, mixed>>
     */
    public function categoriesBySlug(): array
    {
        $portals = $this->tenants()->count();

        return collect($this->categories())
            ->groupBy('slug')
            ->map(function (Collection $rows, string $slug) use ($portals): array {
                $first = $rows->sortBy('position')->first();

                return [
                    '__key' => "slug:{$slug}",
                    'slug' => $slug,
                    'name' => $first['name'],
                    'names' => $rows->pluck('name')->unique()->values()->all(),
                    'description' => $first['description'],
                    'meta_title' => $first['meta_title'],
                    'meta_description' => $first['meta_description'],
                    'position' => (int) $rows->min('position'),
                    'active' => (int) $rows->sum('active'),
                    'drafts' => (int) $rows->sum('drafts'),
                    'topics' => (int) $rows->sum('topics'),
                    'published' => (int) $rows->sum('published'),
                    'published_since' => $rows->pluck('published_since')->filter()->min(),
                    'last_changed_at' => $rows->pluck('last_changed_at')->filter()->max(),
                    'portals' => $rows->count(),
                    'portal_total' => $portals,
                    'members' => $rows->mapWithKeys(fn (array $row): array => [$row['tenant_id'] => $row['id']])->all(),
                    'tenant_names' => $rows->pluck('tenant_name')->all(),
                ];
            })
            ->sortBy([['position', 'asc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Alle zugaenglichen Portale unabhaengig von der Auswahl, mit Branche und
     * Zahl der Themen (Import-Wizard, Zuweisung). 60 s gecacht.
     *
     * @return list<array{tenant_id: int, name: string, domain: string|null, branch: string|null, topics: int}>
     */
    public function portals(): array
    {
        $tenants = $this->available();
        $key = self::CACHE_PREFIX.'portals:v'.$this->version().':'.md5($tenants->map->getKey()->implode(','));

        return $this->memo['portals'] ??= Cache::remember($key, self::CACHE_SECONDS, fn (): array => $tenants
            ->map(fn (Tenant $tenant): array => [
                'tenant_id' => (int) $tenant->getKey(),
                'name' => (string) $tenant->name,
                'domain' => $tenant->domain,
                ...($this->read($tenant, fn (): array => [
                    'branch' => filled($branch = TenantGuideSetting::query()->value('branch')) ? (string) $branch : null,
                    'topics' => Topic::query()->count(),
                ]) ?? ['branch' => null, 'topics' => 0]),
            ])
            ->sortBy([['branch', 'asc'], ['name', 'asc']])
            ->values()
            ->all());
    }

    /**
     * Verwirft den netzwerkweiten Stand; die naechste Anzeige liest frisch.
     */
    public function forget(): void
    {
        $this->memo = [];

        Cache::forever(self::CACHE_PREFIX.'version', $this->version() + 1);
    }

    /**
     * @param  callable(Collection<int, Tenant>): list<array<string, mixed>>  $build
     * @return list<array<string, mixed>>
     */
    private function remember(string $name, callable $build): array
    {
        if (isset($this->memo[$name])) {
            return $this->memo[$name];
        }

        $tenants = $this->tenants();

        if (! $this->isNetworkWide()) {
            return $this->memo[$name] = $build($tenants);
        }

        $key = self::CACHE_PREFIX.$name.':v'.$this->version().':'.md5($tenants->keys()->implode(','));

        return $this->memo[$name] = Cache::remember($key, self::CACHE_SECONDS, fn (): array => $build($tenants));
    }

    private function version(): int
    {
        return (int) Cache::get(self::CACHE_PREFIX.'version', 1);
    }

    /**
     * Ein Portal ohne Guide-Tabellen (frisch angelegt, Migration fehlt) wird
     * uebersprungen statt die ganze Ansicht zu sprengen.
     *
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
            Log::warning('Ratgeber-Dashboard: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topicRows(Tenant $tenant): array
    {
        $runs = TopicRun::query()
            ->whereIn('id', TopicRun::query()->selectRaw('max(id)')->groupBy('guide_topic_id'))
            ->get(['id', 'guide_topic_id', 'run_date', 'status', 'mode', 'error', 'started_at', 'finished_at', 'created_at'])
            ->keyBy('guide_topic_id');

        $categories = Category::query()->get(['id', 'name', 'slug', 'refresh_interval_days'])->keyBy('id');
        $unreachable = UnreachableSources::countsByTopic();
        $defaultInterval = (int) config('guide.schedule.probe_interval_days', 7);

        return Topic::query()
            ->get([
                'id', 'guide_category_id', 'question', 'slug', 'status', 'outline_json', 'outline_locked_at',
                'refresh_interval_days', 'article_id', 'last_checked_at', 'last_changed_at', 'next_due_at', 'updated_at',
            ])
            ->map(function (Topic $topic) use ($tenant, $runs, $categories, $unreachable, $defaultInterval): array {
                /** @var TopicRun|null $run */
                $run = $runs->get($topic->getKey());
                $category = $categories->get($topic->guide_category_id);

                return [
                    '__key' => self::key($tenant->getKey(), $topic->getKey()),
                    'tenant_id' => (int) $tenant->getKey(),
                    'tenant_name' => (string) $tenant->name,
                    'id' => (int) $topic->getKey(),
                    'question' => (string) $topic->question,
                    'slug' => (string) $topic->slug,
                    'category_id' => $category?->getKey(),
                    'category_name' => $category?->name,
                    'category_slug' => $category?->slug,
                    'status' => $topic->status->value,
                    'has_outline' => ($topic->outline_json ?? []) !== [],
                    'outline_locked' => $topic->isOutlineLocked(),
                    'interval' => $topic->refresh_interval_days ?? $category?->refresh_interval_days ?? $defaultInterval,
                    'interval_custom' => $topic->refresh_interval_days !== null,
                    'article_id' => $topic->article_id,
                    'last_checked_at' => $topic->last_checked_at?->toIso8601String(),
                    'last_changed_at' => $topic->last_changed_at?->toIso8601String(),
                    'next_due_at' => $topic->next_due_at?->toIso8601String(),
                    'updated_at' => $topic->updated_at?->toIso8601String(),
                    // Quellen mit broken_at, die noch einen aktuellen Fakt belegen (§5.7.2)
                    'unreachable_sources' => $unreachable[$topic->getKey()] ?? 0,
                    ...self::runFields($run),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * "Ausserhalb des Plans" (design/guide-dashboard.md §3.1 Punkt 6): aktives
     * Thema mit Artikel, seit mehr als Pruefabstand + Topic::OUT_OF_PLAN_GRACE_DAYS
     * nicht geprueft. Gleiche Regel wie Topic::scopeOverdue().
     *
     * @param  array<string, mixed>  $row  Zeile aus topics()
     */
    public static function isOutOfPlan(array $row, ?Carbon $now = null): bool
    {
        if ($row['status'] !== TopicStatus::ACTIVE->value || $row['article_id'] === null || $row['last_checked_at'] === null) {
            return false;
        }

        $interval = max((int) config('guide.schedule.min_probe_interval_days', 1), (int) $row['interval']);

        return Carbon::parse($row['last_checked_at'])
            ->addDays($interval + Topic::OUT_OF_PLAN_GRACE_DAYS)
            ->lessThanOrEqualTo($now ?? Carbon::now());
    }

    /**
     * Anzeigefelder des letzten Laufs.
     *
     * @return array<string, mixed>
     */
    public static function runFields(?TopicRun $run): array
    {
        if ($run === null) {
            return [
                'run_id' => null,
                'run_status' => null,
                'run_display' => null,
                'run_date' => null,
                'run_error' => null,
                'run_started_at' => null,
                'run_finished_at' => null,
            ];
        }

        $status = $run->status instanceof RunStatus ? $run->status : RunStatus::from((string) $run->status);
        $mode = $run->mode instanceof RunMode ? $run->mode : RunMode::tryFrom((string) $run->mode);

        return [
            'run_id' => (int) $run->getKey(),
            'run_status' => $status->value,
            'run_display' => RunDisplay::fromRun($status, $mode)->value,
            'run_date' => $run->run_date?->toDateString(),
            'run_error' => $run->error,
            'run_started_at' => Carbon::make($run->started_at ?? $run->created_at)?->toIso8601String(),
            'run_finished_at' => Carbon::make($run->finished_at)?->toIso8601String(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function categoryRows(Tenant $tenant): array
    {
        $counts = Topic::query()
            ->selectRaw('guide_category_id, status, count(*) as aggregate, max(last_changed_at) as last_changed_at')
            ->whereNotNull('guide_category_id')
            ->groupBy('guide_category_id', 'status')
            ->toBase()
            ->get()
            ->groupBy('guide_category_id');

        // posts.guide_category_id kommt mit der Tenant-Migration aus #3; fehlt
        // sie, gilt keine Kategorie als veroeffentlicht.
        try {
            $published = Post::query()
                ->selectRaw('guide_category_id, count(*) as aggregate, min(published_at) as since')
                ->whereNotNull('guide_category_id')
                ->where('status', 'published')
                ->groupBy('guide_category_id')
                ->toBase()
                ->get()
                ->keyBy('guide_category_id');
        } catch (QueryException) {
            $published = collect();
        }

        return Category::query()
            ->ordered()
            ->get()
            ->map(function (Category $category) use ($tenant, $counts, $published): array {
                $byStatus = collect($counts->get($category->getKey(), []));
                $count = fn (TopicStatus ...$statuses): int => (int) $byStatus
                    ->whereIn('status', array_map(fn (TopicStatus $status): string => $status->value, $statuses))
                    ->sum('aggregate');
                $posts = $published->get($category->getKey());
                $lastChanged = $byStatus->pluck('last_changed_at')->filter()->max();

                return [
                    '__key' => self::key($tenant->getKey(), $category->getKey()),
                    'tenant_id' => (int) $tenant->getKey(),
                    'tenant_name' => (string) $tenant->name,
                    'id' => (int) $category->getKey(),
                    'name' => (string) $category->name,
                    'slug' => (string) $category->slug,
                    'description' => $category->description,
                    'meta_title' => $category->meta_title,
                    'meta_description' => $category->meta_description,
                    'position' => (int) $category->position,
                    'is_visible' => (bool) $category->is_visible,
                    'active' => $count(TopicStatus::ACTIVE),
                    'drafts' => $count(TopicStatus::DRAFT, TopicStatus::OUTLINE_PENDING),
                    'topics' => (int) $byStatus->sum('aggregate'),
                    'published' => (int) ($posts->aggregate ?? 0),
                    'published_since' => isset($posts->since) ? Carbon::parse($posts->since)->toIso8601String() : null,
                    'last_changed_at' => $lastChanged !== null ? Carbon::parse($lastChanged)->toIso8601String() : null,
                ];
            })
            ->values()
            ->all();
    }
}
