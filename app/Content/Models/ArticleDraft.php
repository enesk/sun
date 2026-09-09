<?php

declare(strict_types=1);

namespace App\Content\Models;

use App\Content\Concerns\Withdrawable;
use App\Content\Enums\DraftStatus;
use App\Models\Portal\Post;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Artikelentwurf (#14, #15, #21).
 *
 * `article_id` zeigt nach der Veroeffentlichung auf die bestehende
 * Artikel-Tabelle des Portals (`posts`).
 *
 * @property DraftStatus $status
 * @property Post|null $article
 * @property TopicCandidate|null $topicCandidate
 * @property float|null $quality_score
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property array<string, mixed>|null $publication_json
 */
class ArticleDraft extends Model
{
    use TenantConnection, Withdrawable;

    protected $fillable = [
        'topic_candidate_id',
        'parent_draft_id',
        'article_id',
        'status',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'short_answer',
        'outline_json',
        'body_html',
        'faq_json',
        'key_facts_json',
        'hero_image_path',
        'hero_image_alt',
        'hero_image_credit',
        'hero_image_source',
        'infographic_svg_path',
        'assets_json',
        'publication_json',
        'region_scope',
        'region_code',
        'quality_score',
        'quality_report_json',
        'attempt',
        'scheduled_for',
        'published_at',
        'needs_refresh',
        'needs_refresh_at',
        'refresh_reason_json',
        'changelog_json',
        'generation_cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'status' => DraftStatus::class,
            'outline_json' => 'array',
            'faq_json' => 'array',
            'key_facts_json' => 'array',
            'assets_json' => 'array',
            'publication_json' => 'array',
            'quality_report_json' => 'array',
            'quality_score' => 'float',
            'attempt' => 'integer',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'needs_refresh' => 'boolean',
            'needs_refresh_at' => 'datetime',
            'refresh_reason_json' => 'array',
            'changelog_json' => 'array',
            'generation_cost_usd' => 'float',
        ];
    }

    public function topicCandidate(): BelongsTo
    {
        return $this->belongsTo(TopicCandidate::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    /**
     * Die Fassung, aus der diese hier entstanden ist (#24). Nur eine
     * Aktualisierung hat eine Elternfassung; ein Erstentwurf hat keine.
     */
    public function parentDraft(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_draft_id');
    }

    /**
     * Aktualisierungen dieser Fassung (#24).
     */
    public function refreshes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_draft_id');
    }

    /**
     * Eine Aktualisierung eines bereits veroeffentlichten Artikels — kein
     * neuer Artikel. Sie zaehlt nicht gegen das Tagesziel (#19) und wird in
     * der Pruefung als Gegenueberstellung gezeigt (#20).
     */
    public function isRefresh(): bool
    {
        return $this->parent_draft_id !== null;
    }

    /**
     * Aenderungshinweise dieser Fassung, aelteste zuerst (#24). Jede Fassung
     * erbt die Liste ihrer Elternfassung; das Ratgeber-Template rendert sie
     * unter der Autorenbox.
     *
     * @return array<int, array{at: string|null, summary: string, reasons: array<int, string>, sections: array<int, string>}>
     */
    public function changelogEntries(): array
    {
        $entries = [];

        foreach ((array) ($this->changelog_json ?? []) as $entry) {
            if (! is_array($entry) || trim((string) ($entry['summary'] ?? '')) === '') {
                continue;
            }

            $entries[] = [
                'at' => isset($entry['at']) && $entry['at'] !== null ? (string) $entry['at'] : null,
                'summary' => trim((string) $entry['summary']),
                'reasons' => array_values(array_map('strval', (array) ($entry['reasons'] ?? []))),
                'sections' => array_values(array_map('strval', (array) ($entry['sections'] ?? []))),
            ];
        }

        return $entries;
    }

    public function sources(): HasMany
    {
        return $this->hasMany(DraftSource::class);
    }

    /**
     * Schnipsel, die bei der Generierung dieses Entwurfs entstanden sind —
     * NICHT die Fakten, auf die der Artikel sich stuetzt (#63).
     *
     * Faktenschnipsel sind gemeinsamer Bestand des Mandanten (#11) und tragen
     * `article_draft_id` in aller Regel gar nicht. Die Belege eines Artikels
     * stehen in `outline_json.fact_snippet_ids` und in `sources()`; wer sie
     * braucht, nimmt factSnippetIds() statt dieser Beziehung.
     */
    public function factSnippets(): HasMany
    {
        return $this->hasMany(FactSnippet::class);
    }

    /**
     * Die IDs der Fakten, die der Generator (#14) fuer diesen Artikel benutzt
     * hat. Massgeblich fuer Qualitaetsgate und Quellenanzeige.
     *
     * @return array<int, int>
     */
    public function factSnippetIds(): array
    {
        $ids = ($this->outline_json ?? [])['fact_snippet_ids'] ?? [];

        return array_values(array_unique(array_map('intval', (array) $ids)));
    }

    /**
     * Artikel, die auf diesen Ratgeber verlinken (#21). Der Publisher traegt
     * sie beim Veroeffentlichen des verlinkenden Artikels ein; die
     * Ratgeber-Seite zeigt sie als „verwandte Artikel" vor der
     * Kategorie-Auffuellung.
     *
     * @return array<int, int>
     */
    public function backlinkArticleIds(): array
    {
        $ids = [];

        foreach ((array) (($this->publication_json ?? [])['backlinks'] ?? []) as $backlink) {
            $id = (int) ($backlink['article_id'] ?? 0);

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ArticleMetric::class);
    }

    /**
     * Zurueckziehen archiviert zusaetzlich den veroeffentlichten Beitrag —
     * damit verschwindet er sofort aus Frontend, Sitemap und Feed. Der
     * Entwurf behaelt seinen Pipeline-Status und seinen Fingerprint, das
     * Thema wird also nicht sofort neu erzeugt (#21).
     */
    protected function afterWithdraw(string $reason): void
    {
        $this->article?->update(['status' => Post::STATUS_ARCHIVED]);
    }

    protected function afterRepublish(): void
    {
        if ($this->status === DraftStatus::PUBLISHED) {
            $this->article?->update(['status' => Post::STATUS_PUBLISHED]);
        }
    }

    /**
     * Statuswechsel nur entlang der in DraftStatus definierten Kanten.
     */
    public function transitionTo(DraftStatus $target): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            return false;
        }

        $this->status = $target;

        return $this->save();
    }

    public function scopeWithStatus(Builder $query, DraftStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * Entwuerfe, die auf einen Menschen warten (Pruef-Queue, #20).
     */
    public function scopeNeedsHumanAction(Builder $query): Builder
    {
        return $query->whereIn('status', [DraftStatus::REVIEW->value, DraftStatus::FAILED->value]);
    }

    /**
     * Faellige Entwuerfe fuer den Publisher (#21).
     */
    public function scopeDueForPublishing(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        return $query->where('status', DraftStatus::SCHEDULED->value)
            ->whereNull('withdrawn_at')
            ->where('scheduled_for', '<=', $at ?? now());
    }

    /**
     * Live stehende Entwuerfe. Zurueckgezogene sind bewusst ausgeschlossen —
     * damit greift auch der Refresh-Loop (#24) sie nicht als
     * Aktualisierungskandidaten auf.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', DraftStatus::PUBLISHED->value)
            ->whereNull('withdrawn_at');
    }

    /**
     * Nur Erstentwuerfe. Aktualisierungen (#24) haben eine Elternfassung und
     * zaehlen nirgends gegen das Tagesziel von zwei neuen Artikeln.
     */
    public function scopeOriginals(Builder $query): Builder
    {
        return $query->whereNull('parent_draft_id');
    }

    /**
     * Aktualisierungen (#24), wahlweise auf einen Tag eingegrenzt — so zaehlt
     * der RefreshSelector das Tageskontingent.
     */
    public function scopeRefreshesOn(Builder $query, ?\DateTimeInterface $day = null): Builder
    {
        $day = CarbonImmutable::parse($day ?? now());

        return $query->whereNotNull('parent_draft_id')
            ->whereBetween('created_at', [$day->startOfDay(), $day->endOfDay()]);
    }

    /**
     * Entwuerfe, die laenger als erlaubt in einem Arbeitsstatus haengen (#22).
     */
    public function scopeStuck(Builder $query, ?int $minutes = null): Builder
    {
        $minutes ??= (int) config('content.pipeline.stuck_after_minutes', 45);

        return $query->whereIn('status', [DraftStatus::GENERATING->value, DraftStatus::CHECKING->value])
            ->where('updated_at', '<', now()->subMinutes($minutes));
    }

    public function scopeForRegion(Builder $query, string $scope, ?string $code = null): Builder
    {
        return $query->where('region_scope', $scope)
            ->when($code !== null, fn (Builder $q) => $q->where('region_code', $code));
    }
}
