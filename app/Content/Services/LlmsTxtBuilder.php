<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Models\Portal\Post;
use App\Support\TenantCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Baut /llms.txt und /llms-full.txt des Mandanten (#18).
 *
 * llms.txt ist der Kurzindex nach llmstxt.org: H1 mit dem Portalnamen,
 * Blockquote mit der Kurzbeschreibung, danach Abschnitte mit Zeilen der Form
 * '- [Titel](URL): Kurzantwort'. llms-full.txt haengt die Ratgeber-Volltexte
 * als Markdown an.
 *
 * Beide Dateien liegen eine Stunde im Cache. Veroeffentlichung, Zuruecknahme
 * und jede Aenderung an einem Beitrag verwerfen ihn (siehe
 * ContentServiceProvider::boot).
 */
class LlmsTxtBuilder
{
    public const CACHE_TTL = 3600;

    private const CACHE_PREFIX = 'content.llms';

    /** Obergrenze fuer den Kurzindex; darueber wuerde die Datei unlesbar lang. */
    private const MAX_INDEX_ARTICLES = 500;

    /** Obergrenze fuer die Volltexte, damit die Antwort im Rahmen bleibt. */
    private const MAX_FULL_ARTICLES = 200;

    public function __construct(
        private readonly PortalProfileService $profile,
        private readonly ArticleSeoService $seo,
    ) {}

    public function index(): string
    {
        return Cache::remember($this->cacheKey('index'), self::CACHE_TTL, fn () => $this->buildIndex());
    }

    public function full(): string
    {
        return Cache::remember($this->cacheKey('full'), self::CACHE_TTL, fn () => $this->buildFull());
    }

    /**
     * Verwirft beide Dateien des angegebenen (oder des laufenden) Mandanten.
     */
    public static function flush(?string $tenantKey = null): void
    {
        $key = $tenantKey ?? self::tenantKey();

        foreach (['index', 'full'] as $variant) {
            Cache::forget(self::CACHE_PREFIX.'.'.$key.'.'.$variant);
        }
    }

    private function cacheKey(string $variant): string
    {
        return self::CACHE_PREFIX.'.'.self::tenantKey().'.'.$variant;
    }

    /**
     * Der Cache ist nicht mandantengetrennt (CacheTenancyBootstrapper ist
     * abgeschaltet), deshalb steckt der Tenant-Schluessel im Cache-Key.
     */
    private static function tenantKey(): string
    {
        return TenantCache::tenantKey();
    }

    private function buildIndex(): string
    {
        $articles = $this->articles(self::MAX_INDEX_ARTICLES);
        $modified = $this->latestModification($articles);

        $lines = [
            '# '.$this->profile->siteName(),
            '',
            '> '.$this->oneLine($this->profile->description()),
            '',
        ];

        foreach ($this->facts($modified, count($articles)) as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        $lines[] = '';
        $lines[] = '## Ratgeber';
        $lines[] = '';

        if ($articles === []) {
            $lines[] = '- Es sind noch keine Ratgeber veroeffentlicht.';
        }

        foreach ($articles as $article) {
            $lines[] = '- ['.$this->oneLine($article['title']).']('.$article['url'].'): '.$this->oneLine($article['summary']);
        }

        $lines[] = '';
        $lines[] = '## Ueber das Portal';
        $lines[] = '';
        $lines[] = '- [So arbeitet unsere Redaktion]('.route('portal.blog.editorial').'): Themenauswahl, Entstehungsweg, Quellen und Aktualisierung der Ratgeber.';
        $lines[] = '- ['.$this->oneLine($this->profile->authorName()).']('.$this->profile->authorUrl().'): Redaktionell verantwortlich fuer die Ratgeber.';
        $lines[] = '- [Ratgeber-Uebersicht]('.route('portal.blog.index').'): Alle Ratgeber des Portals.';
        $lines[] = '';
        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Volltexte]('.route('portal.llms-full').'): Alle Ratgeber als Markdown in einer Datei.';
        $lines[] = '- [Ratgeber-Sitemap]('.url('/sitemap-ratgeber.xml').'): Maschinenlesbare Liste mit Aktualisierungsdatum.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildFull(): string
    {
        $articles = $this->articles(self::MAX_FULL_ARTICLES);
        $modified = $this->latestModification($articles);

        $lines = [
            '# '.$this->profile->siteName().' — Ratgeber-Volltexte',
            '',
            '> '.$this->oneLine($this->profile->description()),
            '',
        ];

        foreach ($this->facts($modified, count($articles)) as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        $lines[] = '';
        $lines[] = 'Alle Texte sind redaktionell geprueft, maschinell erstellt und stehen unter '
            .route('portal.blog.editorial').' erlaeutert.';

        $converter = $this->converter();

        foreach ($articles as $article) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = '';
            $lines[] = '## '.$this->oneLine($article['title']);
            $lines[] = '';
            $lines[] = 'URL: '.$article['url'];

            if ($article['published_at'] instanceof Carbon) {
                $lines[] = 'Veroeffentlicht: '.$article['published_at']->toDateString();
            }

            if ($article['modified_at'] instanceof Carbon) {
                $lines[] = 'Aktualisiert: '.$article['modified_at']->toDateString();
            }

            if ($article['summary'] !== '') {
                $lines[] = '';
                $lines[] = '> '.$this->oneLine($article['summary']);
            }

            $lines[] = '';
            $lines[] = $this->markdown($article['body_html'], $article['body_markdown'], $converter);
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private function facts(?Carbon $modified, int $count): array
    {
        return array_filter([
            'Adresse' => url('/'),
            'Branche' => $this->profile->branchLabel(),
            'Region' => $this->profile->regionLabel() ?? 'Deutschland (bundesweit)',
            'Sprache' => 'Deutsch (de-DE)',
            'Ratgeber' => (string) $count,
            'Stand' => ($modified ?? now())->toDateString(),
        ], fn (?string $value) => $value !== null && $value !== '');
    }

    /**
     * Veroeffentlichte Ratgeber mit Kurzantwort und Volltext.
     *
     * @return list<array{title: string, url: string, summary: string, body_html: string, body_markdown: string, published_at: ?Carbon, modified_at: ?Carbon}>
     */
    private function articles(int $limit): array
    {
        $posts = Post::published()
            ->latest('published_at')
            ->limit($limit)
            ->get();

        $drafts = $this->draftsFor($posts->pluck('id')->all());

        $articles = $posts->map(function (Post $post) use ($drafts): array {
            $draft = $drafts->get($post->id);

            return [
                'title' => (string) $post->title,
                'url' => route('portal.blog.show', $post->slug),
                'summary' => $this->summary($post, $draft),
                'body_html' => (string) $draft?->body_html,
                'body_markdown' => (string) $post->body,
                'published_at' => $post->published_at,
                'modified_at' => $this->seo->modifiedAt($post, $draft),
            ];
        })->values()->all();

        return $articles;
    }

    /**
     * @param  list<int>  $postIds
     * @return Collection<int, ArticleDraft>
     */
    private function draftsFor(array $postIds): Collection
    {
        if ($postIds === [] || ! Schema::connection((new ArticleDraft)->getConnectionName())->hasTable('article_drafts')) {
            return collect();
        }

        return ArticleDraft::query()
            ->whereIn('article_id', $postIds)
            ->orderBy('id')
            ->get()
            ->keyBy('article_id');
    }

    /**
     * Einzeiler zum Artikel: die Kurzantwort des Entwurfs, sonst die
     * Meta-Beschreibung, sonst der gekuerzte Anrisstext.
     */
    private function summary(Post $post, ?ArticleDraft $draft): string
    {
        foreach ([$draft?->short_answer, $draft?->meta_description, $post->meta_description, $post->excerpt_or_truncated] as $candidate) {
            $value = trim(strip_tags((string) $candidate));

            if ($value !== '') {
                return Str::limit($value, 300);
            }
        }

        return '';
    }

    /**
     * @param  list<array{modified_at: ?Carbon}>  $articles
     */
    private function latestModification(array $articles): ?Carbon
    {
        $latest = null;

        foreach ($articles as $article) {
            $candidate = $article['modified_at'];

            if ($candidate instanceof Carbon && ($latest === null || $candidate->greaterThan($latest))) {
                $latest = $candidate;
            }
        }

        return $latest;
    }

    /**
     * Der Pipeline-Entwurf liefert HTML, von Hand gepflegte Beitraege liefern
     * bereits Markdown.
     */
    private function markdown(string $bodyHtml, string $bodyMarkdown, HtmlConverter $converter): string
    {
        $html = trim($bodyHtml);

        $markdown = $html === '' ? trim($bodyMarkdown) : trim($converter->convert($html));

        return $this->withoutLeadingTitle($markdown);
    }

    /**
     * Der Titel steht bereits als Abschnittsueberschrift ueber dem Text; eine
     * fuehrende H1 im Text selbst waere die zweite Ueberschrift in Folge.
     */
    private function withoutLeadingTitle(string $markdown): string
    {
        $break = strpos($markdown, "\n");

        if (! str_starts_with($markdown, '# ') || $break === false) {
            return $markdown;
        }

        return ltrim(substr($markdown, $break));
    }

    private function converter(): HtmlConverter
    {
        return new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'remove_nodes' => 'script style iframe form',
            'hard_break' => false,
        ]);
    }

    private function oneLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    }
}
