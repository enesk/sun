<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Guide\Enums\TopicStatus;
use App\Guide\Models\Central\TopicListItem;
use App\Guide\Research\FactsHasher;
use App\Models\Portal\Post;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Ein Ratgeber-Thema des Portals: eine Frage, eine feste Gliederung, ein
 * Artikel in `posts`.
 *
 * `outline_json` hat die Form
 * [{id: 's1', level: 2, heading: '...', children: [{id: 's1-1', level: 3, heading: '...'}]}].
 * Die `id` ist stabil; sie adressiert abschnittsweise Updates
 * (guide_topic_runs.changed_section_ids_json) und die HTML-Anker.
 *
 * `slug` ist nach der Anlage unveraenderlich, weil er Teil der
 * oeffentlichen URL /ratgeber/{kategorie}/{thema} ist.
 *
 * @property TopicStatus $status
 * @property array<int, array<string, mixed>>|null $outline_json
 * @property \Illuminate\Support\Carbon|null $outline_locked_at
 * @property \Illuminate\Support\Carbon|null $next_due_at
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon|null $last_changed_at
 * @property-read Category|null $category
 */
class Topic extends Model
{
    use TenantConnection;

    /** Tage ueber dem Pruefabstand, ab denen ein Thema "ausserhalb des Plans" ist (§3.1). */
    public const OUT_OF_PLAN_GRACE_DAYS = 2;

    protected $table = 'guide_topics';

    protected $fillable = [
        'list_item_id',
        'guide_category_id',
        'question',
        'slug',
        'notes',
        'outline_json',
        'outline_locked_at',
        'status',
        'priority',
        'refresh_interval_days',
        'article_id',
        'last_checked_at',
        'last_changed_at',
        'next_due_at',
        'facts_hash',
        'consecutive_failures',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'outline_json' => 'array',
            'outline_locked_at' => 'datetime',
            'status' => TopicStatus::class,
            'priority' => 'integer',
            'refresh_interval_days' => 'integer',
            'last_checked_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'next_due_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $topic): void {
            if ($topic->isDirty('slug')) {
                throw new LogicException("Der Slug eines Ratgeber-Themas ist unveraenderlich (Thema {$topic->getKey()}).");
            }
        });
    }

    public function listItem(): BelongsTo
    {
        return $this->belongsTo(TopicListItem::class, 'list_item_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'guide_category_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    public function articleDetail(): HasOne
    {
        return $this->hasOne(ArticleDetail::class, 'article_id', 'article_id');
    }

    /**
     * @return HasMany<TopicRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(TopicRun::class, 'guide_topic_id');
    }

    /**
     * @return HasMany<Fact, $this>
     */
    public function facts(): HasMany
    {
        return $this->hasMany(Fact::class, 'guide_topic_id');
    }

    /**
     * @return HasMany<Fact, $this>
     */
    public function currentFacts(): HasMany
    {
        return $this->facts()->where('is_current', true);
    }

    /**
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class, 'guide_topic_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class, 'guide_topic_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TopicStatus::ACTIVE->value);
    }

    /**
     * Themen, die der Tageslauf zum Stichtag aufnehmen darf (#9):
     *  - Status draft (erster Lauf) oder active; paused, archived und
     *    outline_pending nie,
     *  - weniger als guide.schedule.max_consecutive_failures Fehlschlaege in
     *    Folge (danach pausiert recordFailure() das Thema),
     *  - next_due_at erreicht; ohne next_due_at: Themen ohne Artikel oder ohne
     *    Pruefung sofort, sonst last_checked_at + Pruefabstand
     *    (refresh_interval_days des Themas, sonst der Kategorie, sonst
     *    Vorgabe) erreicht,
     *  - oder unabhaengig vom Pruefabstand: ein aktueller Fakt haengt an einer
     *    Quelle, die seit der letzten Recherche als nicht erreichbar markiert
     *    wurde (broken_at > last_checked_at, #27). Findet die Recherche keinen
     *    Ersatz, gilt danach wieder der Pruefabstand.
     */
    public function scopeDue(Builder $query, ?DateTimeInterface $at = null): Builder
    {
        $at = $at !== null ? Carbon::instance($at) : Carbon::now();

        $dispatchable = array_map(
            static fn (TopicStatus $status): string => $status->value,
            array_values(array_filter(TopicStatus::cases(), static fn (TopicStatus $status): bool => $status->isDispatchable())),
        );

        return $query
            ->whereIn('status', $dispatchable)
            ->where('consecutive_failures', '<', self::maxConsecutiveFailures())
            ->where(fn (Builder $q) => $q
                ->where('next_due_at', '<=', $at)
                ->orWhere(fn (Builder $open) => $open
                    ->whereNull('next_due_at')
                    ->where(fn (Builder $never) => $never
                        ->whereNull('article_id')
                        ->orWhereNull('last_checked_at')
                        ->orWhereRaw(self::intervalReachedSql($query), [
                            (int) config('guide.schedule.probe_interval_days', 7),
                            (int) config('guide.schedule.min_probe_interval_days', 1),
                            $at->copy()->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString(),
                        ])))
                ->orWhere(fn (Builder $broken) => $broken
                    ->whereNotNull('article_id')
                    ->whereHas('currentFacts.source', fn (Builder $source) => $source
                        ->whereNotNull('broken_at')
                        ->where(fn (Builder $since) => $since
                            ->whereNull($query->qualifyColumn('last_checked_at'))
                            ->orWhereColumn('broken_at', '>', $query->qualifyColumn('last_checked_at'))))));
    }

    /**
     * Ein aktueller Fakt haengt an einer nicht erreichbaren Quelle (#27): der
     * Lauf geht ohne Freshness-Probe direkt in die Tiefenrecherche.
     */
    public function hasBrokenCurrentSource(): bool
    {
        return $this->currentFacts()
            ->whereHas('source', fn (Builder $source) => $source->whereNotNull('broken_at'))
            ->exists();
    }

    /**
     * Fehlgeschlagener Lauf: Zaehler hoch, naechster Versuch morgen; ab
     * guide.schedule.max_consecutive_failures wird ein aktives Thema pausiert.
     *
     * @return bool true, wenn die Grenze erreicht ist (Alarm faellig)
     */
    public function recordFailure(?DateTimeInterface $at = null): bool
    {
        $at = $at !== null ? Carbon::instance($at) : Carbon::now();
        $failures = (int) $this->consecutive_failures + 1;
        $limitReached = $failures >= self::maxConsecutiveFailures();

        $attributes = [
            'consecutive_failures' => $failures,
            'next_due_at' => $at->copy()
                ->setTimezone((string) config('guide.timezone', 'Europe/Berlin'))
                ->addDay()
                ->startOfDay()
                ->setTimezone((string) config('app.timezone', 'UTC')),
        ];

        if ($limitReached && $this->status->canTransitionTo(TopicStatus::PAUSED)) {
            $attributes['status'] = TopicStatus::PAUSED;
        }

        $this->forceFill($attributes)->save();

        return $limitReached;
    }

    public static function maxConsecutiveFailures(): int
    {
        return max(1, (int) config('guide.schedule.max_consecutive_failures', 5));
    }

    /**
     * last_checked_at + max(Thema ?? Kategorie ?? Vorgabe, Minimum) <= Stichtag.
     * Bindings: Vorgabe, Minimum, Stichtag.
     */
    private static function intervalReachedSql(Builder $query): string
    {
        $interval = self::intervalSql($query);

        return match ($query->getModel()->getConnection()->getDriverName()) {
            'sqlite' => "datetime(last_checked_at, '+' || max({$interval}, ?) || ' days') <= ?",
            'pgsql' => "last_checked_at + (greatest({$interval}, ?) * interval '1 day') <= ?",
            default => "date_add(last_checked_at, interval greatest({$interval}, ?) day) <= ?",
        };
    }

    /**
     * Pruefabstand ohne Minimum: Thema, sonst Kategorie (#33), sonst die
     * Vorgabe als erstes Binding.
     */
    private static function intervalSql(Builder $query): string
    {
        $topics = $query->getModel()->getTable();

        return "coalesce({$topics}.refresh_interval_days, (select guide_categories.refresh_interval_days from guide_categories where guide_categories.id = {$topics}.guide_category_id), ?)";
    }

    /**
     * Themen, die seit mehr als ihrem Pruefabstand plus $graceDays nicht
     * geprueft wurden ("Ausserhalb des Plans", design/guide-dashboard.md
     * §3.1 Punkt 6). Nur aktive Themen mit Artikel; nie geprueft zaehlt nicht,
     * das ist ein offener erster Lauf.
     */
    public function scopeOverdue(Builder $query, int $graceDays = self::OUT_OF_PLAN_GRACE_DAYS, ?DateTimeInterface $at = null): Builder
    {
        $at = ($at !== null ? Carbon::instance($at) : Carbon::now())->copy()->subDays($graceDays);

        return $query
            ->where('status', TopicStatus::ACTIVE->value)
            ->whereNotNull('article_id')
            ->whereNotNull('last_checked_at')
            ->whereRaw(self::intervalReachedSql($query), [
                (int) config('guide.schedule.probe_interval_days', 7),
                (int) config('guide.schedule.min_probe_interval_days', 1),
                $at->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString(),
            ]);
    }

    public function isOutlineLocked(): bool
    {
        return $this->outline_locked_at !== null;
    }

    /**
     * Pruefabstand in Tagen; ohne eigenen Wert gilt der der Kategorie (#33),
     * sonst die Vorgabe aus der Konfiguration.
     */
    public function refreshIntervalDays(): int
    {
        return max(
            (int) config('guide.schedule.min_probe_interval_days', 1),
            $this->refresh_interval_days
                ?? $this->category?->refresh_interval_days
                ?? (int) config('guide.schedule.probe_interval_days', 7),
        );
    }

    /**
     * Alle Abschnitts-IDs der Gliederung in Lesereihenfolge.
     *
     * @return array<int, string>
     */
    public function outlineSectionIds(): array
    {
        $ids = [];

        $walk = function (array $sections) use (&$walk, &$ids): void {
            foreach ($sections as $section) {
                if (isset($section['id'])) {
                    $ids[] = (string) $section['id'];
                }

                $walk((array) ($section['children'] ?? []));
            }
        };

        $walk($this->outline_json ?? []);

        return $ids;
    }

    /**
     * Grundlage der Aenderungserkennung: sha1 ueber die sortierten Tupel
     * (key, value, unit, valid_from) aller aktuellen Fakten.
     */
    public function calculateFactsHash(): string
    {
        return app(FactsHasher::class)->hash(
            $this->currentFacts()->get(['key', 'value', 'unit', 'valid_from']),
        );
    }
}
