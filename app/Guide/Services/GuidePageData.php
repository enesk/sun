<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Content\Services\ArticleBlockPresenter;
use App\Content\Services\ArticleSeoService;
use App\Guide\Assets\HeroImageGenerator;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Category;
use App\Guide\Models\LegacyArticle;
use App\Guide\Models\Source;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Support\BrokenSources;
use App\Guide\Support\OutlineAnchors;
use App\Guide\Writing\HtmlAssembler;
use App\Models\Portal\Post;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * View-Daten der oeffentlichen Ratgeber-Seiten (#17): Uebersicht,
 * Kategorieseite, Suche und Artikel.
 *
 * Liefert ausschliesslich Arrays mit Skalaren und Datumswerten, damit das
 * Ergebnis im Seiten-Cache (GuidePageCache) liegen kann. Datumsvertrag nach
 * design/guide-frontend.md, §4.1: „Aktualisiert“ ist nur eine inhaltliche
 * Aenderung (guide_article_details.content_changed_at), das Pruefdatum
 * (last_checked_at) erscheint nur in der Stand-Zeile und nie in
 * dateModified oder auf Listenkarten.
 *
 * Nicht erreichbare Quellen (guide_sources.broken_at, §4.3a) werden nirgends
 * verlinkt: Quellenliste und Changelog ohne Verweis, im Fliesstext entfaellt
 * das <a>. Eine ersetzte kaputte Quelle verschwindet aus der Quellenliste.
 */
class GuidePageData
{
    public const DISPLAY_TIMEZONE = 'Europe/Berlin';

    public const CATEGORY_PAGE_SIZE = 24;

    /** Pagination erst ab 31 Themen (design/guide-frontend.md, §2.1) */
    public const CATEGORY_PAGINATE_FROM = 31;

    public const RECENT_DAYS = 30;

    public const RECENT_LIMIT = 5;

    public const SEARCH_LIMIT = 50;

    public const RELATED_LIMIT = 3;

    public const CHANGELOG_LIMIT = 20;

    private const TEASER_LENGTH = 160;

    private const STALE_SOURCE_MONTHS = 24;

    private ?bool $available = null;

    public function __construct(
        private readonly ArticleBlockPresenter $blocks,
        private readonly ArticleSeoService $articleSeo,
        private readonly TocBuilder $tocBuilder,
        private readonly HtmlAssembler $html,
    ) {}

    /**
     * Portale, deren Tenant-DB die Ratgeber-Tabellen (#3) noch nicht hat,
     * bleiben beim bisherigen Blog.
     */
    public function available(): bool
    {
        return $this->available ??= Schema::connection((new Category)->getConnectionName())->hasTable('guide_categories')
            && Schema::connection((new Category)->getConnectionName())->hasColumn('posts', 'guide_category_id');
    }

    /**
     * {{year}} in Titeln wird zur Renderzeit ersetzt (#17).
     */
    public static function replaceYear(?string $text): string
    {
        return str_replace(['{{year}}', '{{ year }}'], (string) now(self::DISPLAY_TIMEZONE)->year, (string) $text);
    }

    // ── Uebersicht ────────────────────────────────────────────────────────

    /**
     * Sichtbare Kategorien mit mindestens einem veroeffentlichten Artikel, in
     * der Reihenfolge der Kategorienverwaltung.
     *
     * @return list<array{id: int, name: string, slug: string, url: string, count: int, updated_at: ?CarbonInterface}>
     */
    public function categoryTiles(): array
    {
        $stats = $this->publishedGuidePosts()
            ->groupBy('posts.guide_category_id')
            ->selectRaw('posts.guide_category_id as category_id, count(*) as article_count, max(coalesce(d.content_changed_at, posts.published_at)) as last_at')
            ->toBase()
            ->get()
            ->keyBy('category_id');

        if ($stats->isEmpty()) {
            return [];
        }

        return Category::query()
            ->visible()
            ->ordered()
            ->whereIn('id', $stats->keys()->all())
            ->get(['id', 'name', 'slug'])
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'url' => route('guide.category', $category->slug),
                'count' => (int) $stats[$category->id]->article_count,
                'updated_at' => $stats[$category->id]->last_at !== null ? Carbon::parse($stats[$category->id]->last_at) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * Neu erschienene oder inhaltlich geaenderte Artikel der letzten 30 Tage,
     * neuester zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function recentlyUpdated(): array
    {
        $since = now()->subDays(self::RECENT_DAYS);

        return $this->cards(
            $this->publishedGuidePosts()
                ->whereRaw('coalesce(d.content_changed_at, posts.published_at) >= ?', [$since])
                ->orderByRaw('coalesce(d.content_changed_at, posts.published_at) desc')
                ->limit(self::RECENT_LIMIT)
        );
    }

    /**
     * Serverseitige Suche ueber Frage und Kurzantwort (LIKE, #17).
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $term): array
    {
        $like = '%'.addcslashes($term, '\\%_').'%';

        return $this->cards(
            $this->publishedGuidePosts()
                ->leftJoin('guide_topics as t', 't.id', '=', 'posts.guide_topic_id')
                ->where(fn (Builder $query) => $query
                    ->where('t.question', 'like', $like)
                    ->orWhere('d.short_answer', 'like', $like))
                ->orderByRaw('coalesce(d.content_changed_at, posts.published_at) desc')
                ->limit(self::SEARCH_LIMIT)
        );
    }

    // ── Kategorie ─────────────────────────────────────────────────────────

    /**
     * @return array{id: int, name: string, slug: string, description: ?string, intro_html: ?string, meta_title: ?string, meta_description: ?string}|null
     */
    public function category(string $slug): ?array
    {
        $category = Category::query()->visible()->where('slug', $slug)->first();

        if ($category === null) {
            return null;
        }

        return [
            'id' => (int) $category->id,
            'name' => (string) $category->name,
            'slug' => (string) $category->slug,
            'description' => filled($category->description) ? (string) $category->description : null,
            'intro_html' => filled($category->intro_html) ? $this->html->sanitizeLegacy((string) $category->intro_html) : null,
            'meta_title' => filled($category->meta_title) ? (string) $category->meta_title : null,
            'meta_description' => filled($category->meta_description) ? (string) $category->meta_description : null,
        ];
    }

    /**
     * Themenliste einer Kategorie in der Reihenfolge der Themenliste (Import-
     * reihenfolge = aufsteigende Themen-ID). Bis 30 Themen eine Seite, danach
     * 24 je Seite.
     *
     * @return array{items: list<array<string, mixed>>, total: int, per_page: int, updated_at: ?CarbonInterface}
     */
    public function categoryTopics(int $categoryId, int $page): array
    {
        $base = fn (): Builder => $this->publishedGuidePosts()->where('posts.guide_category_id', $categoryId);

        $total = (clone $base())->count('posts.id');
        $perPage = $total >= self::CATEGORY_PAGINATE_FROM ? self::CATEGORY_PAGE_SIZE : max($total, 1);

        $lastAt = (clone $base())->toBase()->selectRaw('max(coalesce(d.content_changed_at, posts.published_at)) as last_at')->value('last_at');

        $items = $this->cards(
            $base()
                ->orderByRaw('posts.guide_topic_id is null')
                ->orderBy('posts.guide_topic_id')
                ->orderBy('posts.id')
                ->forPage($page, $perPage)
        );

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'updated_at' => $lastAt !== null ? Carbon::parse($lastAt) : null,
        ];
    }

    /**
     * Uebrige Kategorien fuer „Weitere Themen“.
     *
     * @return list<array{name: string, url: string}>
     */
    public function otherCategories(int $exceptId): array
    {
        return array_values(array_map(
            fn (array $tile): array => ['name' => $tile['name'], 'url' => $tile['url']],
            array_filter($this->categoryTiles(), fn (array $tile): bool => $tile['id'] !== $exceptId),
        ));
    }

    // ── Artikel ───────────────────────────────────────────────────────────

    /**
     * Alle Bloecke der Artikelseite. Artikel mit Thema bekommen Stand-Zeile,
     * Changelog, Gliederung und Quellen aus dem Ratgebersystem; aeltere
     * Beitraege ohne Thema rendern im selben Template ohne Stand-Zeile und
     * Changelog (Bloecke aus guide_legacy_articles, soweit vorhanden, #34).
     *
     * @return array<string, mixed>
     */
    public function article(Post $post): array
    {
        $topic = $post->guide_topic_id !== null
            ? Topic::query()->with('category')->find($post->guide_topic_id)
            : null;

        return $topic !== null
            ? $this->topicArticle($post, $topic)
            : $this->legacyArticle($post);
    }

    /**
     * Artikeldaten einer noch nicht veroeffentlichten Fassung fuer die
     * Vorschau (#16, guide.preview): dieselbe Abbildung wie beim
     * Veroeffentlichen (ArticleMapper), aber in nicht gespeicherten Modellen.
     * Stand-Zeile so, wie sie nach der Freigabe gelten wuerde. Nie cachen.
     *
     * @return array<string, mixed>
     */
    public function previewArticle(Topic $topic, ArticleVersion $version): array
    {
        $mapper = app(ArticleMapper::class);
        $now = Carbon::now();
        $existing = $topic->article;

        // Bestehender Artikel: Kopie mit gleicher id, damit "Verwandte"
        // ihn selbst nicht zeigen. Das Modell wird nie gespeichert.
        $post = $existing !== null ? clone $existing : new Post;
        $post->forceFill($existing !== null
            ? $mapper->toUpdatedPostAttributes($topic, $version, $version->short_answer, $version->meta_title, $version->meta_description)
            : $mapper->toNewPostAttributes($topic, $version, $version->short_answer, $version->meta_title, $version->meta_description));

        $detail = new ArticleDetail;
        $detail->forceFill([
            ...$mapper->toDetailAttributes($version, $version->short_answer, (array) ($version->changelog_json ?? [])),
            'last_checked_at' => $now,
            'content_changed_at' => $now,
        ]);

        return $this->topicArticle($post, $topic, $detail);
    }

    /**
     * @return array<string, mixed>
     */
    private function topicArticle(Post $post, Topic $topic, ?ArticleDetail $detail = null): array
    {
        $detail ??= ArticleDetail::query()->where('article_id', $post->id)->first();
        $category = $topic->category ?? ($post->guide_category_id !== null ? Category::query()->find($post->guide_category_id) : null);

        $toc = array_map(
            fn (array $entry): array => [...$entry, 'text' => self::replaceYear($entry['text'])],
            OutlineAnchors::flatten($topic->outline_json),
        );
        $broken = BrokenSources::forTopic((int) $topic->getKey());
        $body = OutlineAnchors::apply($broken->stripLinks((string) $post->body), $toc);
        [$bodyBefore, $bodyAfter] = $this->tocBuilder->splitForRegionalBlock($body, $toc);

        $publishedAt = $post->published_at;
        $updatedAt = $this->effectiveUpdatedAt($publishedAt, $detail?->content_changed_at);
        $shortAnswer = $this->plain($detail?->short_answer) ?: null;
        $changelog = $this->changelog($detail?->changelog_json, $broken);
        $title = self::replaceYear($post->title);
        // Titelbild des Themas (#20); ohne eigenes Bild das Beitragsbild.
        $hero = HeroImageGenerator::presentation($detail, $title);

        return [
            'mode' => 'topic',
            'post_id' => (int) $post->id,
            'slug' => (string) $post->slug,
            'title' => $title,
            'meta_title' => self::replaceYear($post->meta_title ?: $post->title),
            'meta_description' => self::replaceYear($post->meta_description ?: ($shortAnswer ?? $post->excerpt_or_truncated)),
            'short_answer' => $shortAnswer,
            'published_at' => $publishedAt,
            'updated_at' => $updatedAt,
            'modified_at' => $updatedAt ?? $publishedAt,
            'checked_at' => $detail?->last_checked_at,
            'reading_time' => (int) $post->reading_time_minutes,
            'toc' => $toc,
            'body_before' => $bodyBefore,
            'body_after' => $bodyAfter,
            'key_facts' => $this->keyFacts($detail?->key_facts_json, $topic),
            'faq' => $this->faq($detail?->faq_json),
            'changelog' => $changelog,
            'sources' => $this->sources($topic, $broken, BrokenSources::hrefKeys(
                (string) $post->body,
                $detail?->short_answer,
                ...array_values(array_map(
                    fn (mixed $item): ?string => is_array($item) ? implode(' ', array_filter($item, 'is_string')) : null,
                    $detail?->faq_json ?? [],
                )),
            )),
            'category' => $category !== null && $category->is_visible
                ? ['name' => (string) $category->name, 'url' => route('guide.category', $category->slug)]
                : null,
            'related' => $this->related($post, $category !== null ? (int) $category->id : null),
            'hero_image' => $hero ?? $this->blocks->heroImage($post),
            'og_image' => $this->absoluteUrl($hero['src'] ?? $post->featured_image_url),
            'cta' => $this->cta($category?->slug),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyArticle(Post $post): array
    {
        $post->loadMissing('category');
        $legacy = LegacyArticle::forArticle((int) $post->id);

        // Altpfad lief nie durch den Writer: gegen die Whitelist saeubern (Review S3).
        $html = $this->html->sanitizeLegacy((string) $legacy?->body_html);
        $toc = $this->tocBuilder->build($html !== '' ? $html : (string) Str::markdown((string) $post->body));
        [$bodyBefore, $bodyAfter] = $this->tocBuilder->splitForRegionalBlock($toc['html'], $toc['headings']);
        $seo = $this->articleSeo->meta($post, $legacy, '');
        $shortAnswer = $this->blocks->shortAnswer($legacy);
        $guideCategory = $post->guide_category_id !== null ? Category::query()->visible()->find($post->guide_category_id) : null;

        return [
            'mode' => 'legacy',
            'post_id' => (int) $post->id,
            'slug' => (string) $post->slug,
            'title' => self::replaceYear($post->title),
            'meta_title' => self::replaceYear($legacy?->meta_title ?: ($post->meta_title ?: $post->title)),
            'meta_description' => self::replaceYear($legacy?->meta_description ?: ($post->meta_description ?: $post->excerpt_or_truncated)),
            'short_answer' => $shortAnswer,
            'published_at' => $post->published_at,
            'updated_at' => null,
            'modified_at' => $seo['modified_at'] ?? $post->published_at,
            'checked_at' => null,
            'reading_time' => (int) $post->reading_time_minutes,
            'toc' => $toc['headings'],
            'body_before' => $bodyBefore,
            'body_after' => $bodyAfter,
            'key_facts' => $this->blocks->keyFacts($legacy),
            'faq' => $this->blocks->faq($legacy),
            'changelog' => [],
            'sources' => array_map(fn (array $source): array => [
                'publisher' => $source['publisher'] ?? null,
                'title' => (string) ($source['title'] ?? ''),
                'url' => $this->safeUrl((string) ($source['url'] ?? '')),
                'stale_year' => $source['stale_year'] ?? null,
            ], $this->blocks->sources($legacy)),
            'category' => match (true) {
                $guideCategory !== null => ['name' => (string) $guideCategory->name, 'url' => route('guide.category', $guideCategory->slug)],
                $post->category !== null => ['name' => (string) $post->category->getAttribute('name'), 'url' => route('guide.category', $post->category->getAttribute('slug'))],
                default => null,
            },
            'related' => $this->related($post, $guideCategory !== null ? (int) $guideCategory->id : null),
            'hero_image' => $this->blocks->heroImage($post, $legacy),
            'og_image' => $seo['og_image'] ?? null,
            'cta' => $this->cta($guideCategory?->slug),
        ];
    }

    /**
     * Inhaltliche Aenderung nur, wenn sie an einem spaeteren Kalendertag als
     * die Erstveroeffentlichung liegt — der Publisher setzt content_changed_at
     * auch beim ersten Veroeffentlichen.
     */
    private function effectiveUpdatedAt(?CarbonInterface $publishedAt, ?CarbonInterface $changedAt): ?CarbonInterface
    {
        if ($changedAt === null) {
            return null;
        }

        if ($publishedAt === null) {
            return $changedAt;
        }

        $published = $publishedAt->copy()->timezone(self::DISPLAY_TIMEZONE)->startOfDay();
        $changed = $changedAt->copy()->timezone(self::DISPLAY_TIMEZONE)->startOfDay();

        return $changed->greaterThan($published) ? $changedAt : null;
    }

    /**
     * Key-Facts der veroeffentlichten Fassung (guide_article_details), sonst
     * die aktuellen Fakten des Themas. Die Herkunftszeile nennt Quelle und
     * Stand.
     *
     * @param  array<int|string, mixed>|null  $rows
     * @return array{rows: list<array{label: string, value: string}>, caption: ?string, numeric: bool}
     */
    private function keyFacts(?array $rows, Topic $topic): array
    {
        $facts = [];
        $publishers = [];
        $stand = null;

        foreach ($rows ?? [] as $key => $row) {
            if (is_array($row)) {
                $label = trim((string) ($row['label'] ?? $row['key'] ?? ''));
                $value = trim(trim((string) ($row['value'] ?? '')).' '.trim((string) ($row['unit'] ?? '')));
                $publisher = $row['source']['publisher'] ?? $row['source_publisher'] ?? $row['publisher'] ?? null;
                $date = $row['valid_from'] ?? $row['as_of'] ?? null;
            } else {
                $label = is_string($key) ? trim($key) : '';
                $value = trim((string) $row);
                $publisher = null;
                $date = null;
            }

            if ($label === '' || $value === '') {
                continue;
            }

            $facts[] = ['label' => $label, 'value' => $value];

            if (is_string($publisher) && $publisher !== '') {
                $publishers[$publisher] = true;
            }

            $stand = $this->laterDate($stand, $date);
        }

        if ($facts === []) {
            $current = $topic->currentFacts()->with('source')->orderBy('id')->get();

            foreach ($current as $fact) {
                $facts[] = [
                    'label' => (string) $fact->getAttribute('label'),
                    'value' => trim($fact->getAttribute('value').' '.($fact->getAttribute('unit') ?? '')),
                ];

                $publisher = $fact->getAttribute('source')?->getAttribute('publisher');

                if (filled($publisher)) {
                    $publishers[(string) $publisher] = true;
                }

                $stand = $this->laterDate($stand, $fact->getAttribute('valid_from') ?? $fact->getAttribute('last_seen_at'));
            }
        }

        $caption = null;

        if ($facts !== [] && ($publishers !== [] || $stand !== null)) {
            $caption = trim(
                ($publishers !== [] ? 'Quelle: '.implode(', ', array_keys($publishers)) : '')
                .($publishers !== [] && $stand !== null ? ' · ' : '')
                .($stand !== null ? 'Stand: '.$stand->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d.m.Y') : '')
            );
        }

        return [
            'rows' => $facts,
            'caption' => $caption,
            'numeric' => $facts !== [] && collect($facts)->every(fn (array $fact): bool => (bool) preg_match('/\d/', $fact['value'])),
        ];
    }

    private function laterDate(?CarbonInterface $current, mixed $candidate): ?CarbonInterface
    {
        if ($candidate === null || $candidate === '') {
            return $current;
        }

        try {
            $date = $candidate instanceof CarbonInterface ? $candidate : Carbon::parse((string) $candidate);
        } catch (\Throwable) {
            return $current;
        }

        return $current === null || $date->greaterThan($current) ? $date : $current;
    }

    /**
     * @param  array<int, mixed>|null  $items
     * @return list<array{question: string, answer: string}>
     */
    private function faq(?array $items): array
    {
        $faq = [];

        foreach ($items ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = $this->plain($item['question'] ?? $item['q'] ?? '');
            $answer = $this->plain($item['answer'] ?? $item['a'] ?? '');

            if ($question !== '' && $answer !== '') {
                $faq[] = ['question' => self::replaceYear($question), 'answer' => self::replaceYear($answer)];
            }
        }

        return $faq;
    }

    /**
     * Changelog „Was ist neu?“, juengster Eintrag zuerst, hoechstens 20.
     * Zeigt die Quelle eines Eintrags auf eine kaputte Quelle des Themas,
     * bleibt nur das Label stehen; Eintraege werden nie entfernt.
     *
     * @param  array<int, mixed>|null  $entries
     * @return list<array{at: CarbonInterface, text: string, source: ?array{label: string, url: ?string}}>
     */
    private function changelog(?array $entries, BrokenSources $broken): array
    {
        $changelog = [];

        foreach ($entries ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $text = $this->plain($entry['text'] ?? $entry['summary'] ?? $entry['change_summary'] ?? '');
            $at = $this->laterDate(null, $entry['at'] ?? $entry['date'] ?? $entry['published_at'] ?? null);

            if ($text === '' || $at === null) {
                continue;
            }

            $source = is_array($entry['source'] ?? null) ? $entry['source'] : [];
            $label = trim((string) ($source['label'] ?? $source['publisher'] ?? $entry['source_label'] ?? ''));
            $url = trim((string) ($source['url'] ?? $entry['source_url'] ?? ''));

            $changelog[] = [
                'at' => $at,
                'text' => $text,
                'source' => $label !== '' ? ['label' => $label, 'url' => $broken->linkable($this->safeUrl($url))] : null,
            ];
        }

        usort($changelog, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($changelog, 0, self::CHANGELOG_LIMIT);
    }

    /**
     * Quellenliste nach §4.3a: kaputte Quellen ohne Verweis, solange sie
     * einen aktuellen Fakt belegen oder im Text stehen, sonst gar nicht.
     *
     * @param  array<string, true>  $textKeys  BrokenSources::hrefKeys() der ausgelieferten Fassung
     * @return list<array{publisher: ?string, title: string, url: ?string, stale_year: ?string}>
     */
    private function sources(Topic $topic, BrokenSources $broken, array $textKeys): array
    {
        $staleBefore = now()->subMonths(self::STALE_SOURCE_MONTHS);

        return $topic->sources()
            ->orderBy('id')
            ->get()
            ->reject(fn (Source $source): bool => $broken->isReplaced((string) $source->url, $textKeys))
            ->map(function (Source $source) use ($staleBefore, $broken): array {
                $publishedAt = $source->getAttribute('published_at');

                return [
                    'publisher' => filled($source->publisher) ? (string) $source->publisher : null,
                    'title' => (string) ($source->title ?: ($source->publisher ?: parse_url((string) $source->url, PHP_URL_HOST) ?: $source->url)),
                    'url' => $broken->linkable($this->safeUrl((string) $source->url)),
                    'stale_year' => $publishedAt instanceof CarbonInterface && $publishedAt->lessThan($staleBefore)
                        ? (string) $publishedAt->year
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Verwandte Themen derselben Kategorie in Listenreihenfolge.
     *
     * @return list<array<string, mixed>>
     */
    private function related(Post $post, ?int $categoryId): array
    {
        if ($categoryId === null) {
            return [];
        }

        return $this->cards(
            $this->publishedGuidePosts()
                ->where('posts.guide_category_id', $categoryId)
                ->where('posts.id', '!=', $post->id)
                ->orderByRaw('posts.guide_topic_id is null')
                ->orderBy('posts.guide_topic_id')
                ->limit(self::RELATED_LIMIT)
        );
    }

    /**
     * Verweis auf die Firmensuche. Eine Zuordnung je Kategorie aus
     * tenant_guide_settings.category_cta_mapping_json hat Vorrang.
     *
     * @return array{text: string, label: string, url: string}
     */
    private function cta(?string $categorySlug): array
    {
        $setting = TenantGuideSetting::query()->first();
        $plural = trim((string) $setting?->branch_plural) ?: 'Fachbetriebe';
        $mapping = $categorySlug !== null ? ($setting?->category_cta_mapping_json[$categorySlug] ?? null) : null;

        // Wie die Validierung in den Einstellungen: Pfad oder http(s), kein '//fremd.de'.
        if (is_array($mapping) && preg_match('#^(/(?![/\\\\])|https?://)#i', trim((string) ($mapping['url'] ?? ''))) !== 1) {
            $mapping['url'] = null;
        }

        return [
            'text' => "{$plural} in Ihrer Nähe vergleichen",
            'label' => is_array($mapping) && filled($mapping['label'] ?? null) ? (string) $mapping['label'] : "{$plural} finden",
            'url' => is_array($mapping) && filled($mapping['url'] ?? null) ? (string) $mapping['url'] : route('portal.companies.index'),
        ];
    }

    // ── Portalseiten ──────────────────────────────────────────────────────

    /**
     * „Passende Ratgeber“ auf Portal-Kategorie-, Stadt- und Profilseiten
     * (#18): die drei zuletzt inhaltlich geaenderten Artikel.
     *
     * Die Zuordnung liest category_cta_mapping_json in umgekehrter Richtung:
     * dort zeigt je Ratgeber-Kategorie (Schluessel = Slug) ein Verweis auf eine
     * Portalseite; passend sind die Ratgeber-Kategorien, deren Verweis auf
     * /kategorien/{portalCategorySlug} fuehrt. Ohne Portal-Kategorie (etwa
     * eine Stadtseite ohne Filter) kommen die Artikel aller sichtbaren
     * Kategorien in Frage; eine Portal-Kategorie ohne Zuordnung zeigt nichts.
     *
     * @return list<array{title: string, url: string, teaser: string, date_label: string, date: ?CarbonInterface}>
     */
    public function relatedForPortalCategory(?string $portalCategorySlug): array
    {
        if (! $this->available()) {
            return [];
        }

        $query = $this->publishedGuidePosts()
            ->join('guide_categories as c', 'c.id', '=', 'posts.guide_category_id')
            ->where('c.is_visible', true);

        if ($portalCategorySlug !== null) {
            $slugs = $this->guideCategoriesLinkingTo(route('portal.categories.show', $portalCategorySlug, false));

            if ($slugs === []) {
                return [];
            }

            $query->whereIn('c.slug', $slugs);
        }

        return $this->cards(
            $query
                ->orderByRaw('coalesce(d.content_changed_at, posts.published_at) desc')
                ->orderByDesc('posts.id')
                ->limit(self::RELATED_LIMIT)
        );
    }

    /**
     * Slugs der Ratgeber-Kategorien, deren CTA-Verweis auf den Pfad zeigt.
     * Verweise duerfen absolut oder relativ sein; verglichen wird nur der Pfad.
     *
     * @return list<string>
     */
    private function guideCategoriesLinkingTo(string $path): array
    {
        $mapping = TenantGuideSetting::query()->value('category_cta_mapping_json');
        $mapping = is_string($mapping) ? json_decode($mapping, true) : $mapping;

        if (! is_array($mapping)) {
            return [];
        }

        $normalize = fn (string $url): string => rtrim(mb_strtolower((string) parse_url(trim($url), PHP_URL_PATH)), '/');
        $target = $normalize($path);

        $slugs = [];

        foreach ($mapping as $categorySlug => $entry) {
            $url = is_array($entry) ? ($entry['url'] ?? null) : $entry;

            if (is_string($url) && $url !== '' && $normalize($url) === $target) {
                $slugs[] = (string) $categorySlug;
            }
        }

        return $slugs;
    }

    // ── gemeinsam ─────────────────────────────────────────────────────────

    /**
     * Veroeffentlichte Artikel des Ratgebersystems samt Detailzeile.
     */
    private function publishedGuidePosts(): Builder
    {
        return Post::query()
            ->where('posts.status', Post::STATUS_PUBLISHED)
            ->where('posts.published_at', '<=', now())
            ->whereNotNull('posts.guide_category_id')
            ->leftJoin('guide_article_details as d', 'd.article_id', '=', 'posts.id');
    }

    /**
     * Themen-Karten (design/guide-frontend.md, §4.5): Titel, Kurzantwort als
     * Anriss, Datumszeile ohne Pruefdatum.
     *
     * @return list<array{title: string, url: string, teaser: string, date_label: string, date: ?CarbonInterface}>
     */
    private function cards(Builder $query): array
    {
        return $query
            ->get(['posts.id', 'posts.title', 'posts.slug', 'posts.excerpt', 'posts.published_at', 'd.short_answer', 'd.content_changed_at'])
            ->map(function (Post $post): array {
                $publishedAt = $post->published_at;
                $changedAt = $post->getAttribute('content_changed_at') !== null ? Carbon::parse($post->getAttribute('content_changed_at')) : null;
                $updatedAt = $this->effectiveUpdatedAt($publishedAt, $changedAt);

                [$label, $date] = match (true) {
                    $updatedAt !== null => ['Aktualisiert am', $updatedAt],
                    $publishedAt !== null && $publishedAt->greaterThanOrEqualTo(now()->subDays(self::RECENT_DAYS)) => ['Neu am', $publishedAt],
                    default => ['Veröffentlicht am', $publishedAt],
                };

                return [
                    'title' => self::replaceYear($post->title),
                    'url' => route('guide.show', $post->slug),
                    'teaser' => Str::limit($this->plain($post->getAttribute('short_answer') ?: $post->excerpt), self::TEASER_LENGTH, '…', preserveWords: true),
                    'date_label' => $label,
                    'date' => $date,
                ];
            })
            ->values()
            ->all();
    }

    private function plain(mixed $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
    }

    /**
     * Nur http(s)-Verweise gelangen in href-Attribute.
     */
    private function safeUrl(string $url): ?string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : null;
    }

    private function absoluteUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }
}
