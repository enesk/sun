<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Content\Providers\IndexNowClient;
use App\Models\Portal\Post;
use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Veroeffentlichung eines freigegebenen Entwurfs (#21).
 *
 * Der Ablauf ist bewusst zweigeteilt: erst das, was die Seite live schaltet,
 * danach alles, was nach aussen wirkt.
 *
 *  1. Artikel schreiben (ArticleMapper -> posts) und den Entwurf auf
 *     `published` setzen. Schlaegt das fehl, ist nichts passiert.
 *  2. Fingerprint anlegen, Backlinks eintragen, Sitemap neu schreiben,
 *     Caches verwerfen, IndexNow anpingen.
 *
 * Kein Schritt aus (2) darf (1) zurueckdrehen. Ein Artikel, der steht, bleibt
 * stehen — was in der Nachbereitung schiefging, steht im Protokoll
 * (`article_drafts.publication_json`) und im Log.
 *
 * Alle Methoden laufen im Tenant-Kontext (PublishDraftJob ruft sie in
 * `Tenant::run()`).
 */
class Publisher
{
    public function __construct(
        private readonly ArticleMapper $mapper,
        private readonly FingerprintService $fingerprints,
        private readonly IndexNowClient $indexNow,
        private readonly RatgeberSitemapGenerator $sitemap,
    ) {}

    /**
     * Veroeffentlicht den Entwurf. Rueckgabe null, wenn er dafuer nicht in
     * Frage kommt — das ist kein Fehler, sondern der Normalfall bei einem
     * doppelt eingereihten Job.
     */
    public function publish(Tenant $tenant, ArticleDraft $draft): ?Post
    {
        if ($draft->isWithdrawn()) {
            Log::info('Veroeffentlichung uebersprungen, Entwurf ist zurueckgezogen.', $this->context($tenant, $draft));

            return null;
        }

        if ($draft->status === DraftStatus::PUBLISHED && $draft->article_id !== null) {
            return $draft->article;
        }

        if (! in_array($draft->status, [DraftStatus::APPROVED, DraftStatus::SCHEDULED], true)) {
            Log::info('Veroeffentlichung uebersprungen, Status passt nicht.', $this->context($tenant, $draft) + [
                'status' => $draft->status->value,
            ]);

            return null;
        }

        if (trim((string) $draft->body_html) === '') {
            Log::warning('Veroeffentlichung abgelehnt, der Entwurf hat keinen Fliesstext.', $this->context($tenant, $draft));

            return null;
        }

        $settings = TenantContentSetting::current();
        $post = $draft->article_id !== null && $draft->article !== null
            ? $this->mapper->updateArticle($draft->article, $draft, $settings)
            : $this->mapper->createArticle($draft, $settings);

        $draft->forceFill([
            'article_id' => (int) $post->getKey(),
            'published_at' => $post->published_at ?? now(),
        ])->save();

        if ($draft->status === DraftStatus::APPROVED) {
            $draft->transitionTo(DraftStatus::SCHEDULED);
        }

        $draft->transitionTo(DraftStatus::PUBLISHED);

        $this->completeRefresh($draft);

        Log::info('Ratgeber veroeffentlicht.', $this->context($tenant, $draft) + [
            'article_id' => $post->getKey(),
            'slug' => $post->slug,
        ]);

        $this->afterPublish($tenant, $draft, $post);

        return $post;
    }

    /**
     * Zuruecknahme (#21, #28). Der Beitrag wird archiviert, das
     * Veroeffentlichungsdatum des Entwurfs entfaellt, der Fingerprint wird
     * geloescht — das Thema ist damit wieder frei — und Sitemap, Caches und
     * IndexNow erfahren davon.
     *
     * Der Pipeline-Status bleibt `published`: er sagt, wie weit die
     * Erstellung gekommen ist, die Zuruecknahme liegt eine Ebene darueber
     * (Withdrawable).
     */
    public function unpublish(Tenant $tenant, ArticleDraft $draft, string $reason, ?int $userId = null): bool
    {
        $post = $draft->article;

        if (! $draft->isWithdrawn()) {
            // withdraw() archiviert den Beitrag ueber ArticleDraft::afterWithdraw().
            $draft->withdraw($reason, $userId);
        } elseif ($post !== null) {
            $post->update(['status' => Post::STATUS_ARCHIVED]);
        }

        $publication = (array) ($draft->publication_json ?? []);
        $publication['unpublished_at'] = now()->toIso8601String();
        $publication['unpublished_reason'] = $reason;

        if ($post !== null) {
            $removed = $this->fingerprints->forget($tenant, (int) $post->getKey());
            $publication['fingerprint_removed'] = $removed > 0;
        }

        $draft->forceFill([
            'published_at' => null,
            'publication_json' => $publication,
        ])->save();

        $this->attempt('Cache', $tenant, $draft, function (): array {
            $this->flushCaches();

            return [];
        });

        $publication['sitemap'] = $this->attempt('Sitemap', $tenant, $draft, fn (): array => $this->writeSitemap($tenant));

        // Der Ping meldet dieselben URLs noch einmal: die Artikelseite ist
        // weg, die Uebersicht hat sich geaendert. Suchmaschinen holen sich
        // daraufhin den 404 bzw. die neue Liste.
        $publication['indexnow'] = $this->attempt('IndexNow', $tenant, $draft, fn (): array => $this->ping($tenant, $post));

        $draft->forceFill([
            'publication_json' => array_replace((array) ($draft->fresh()?->publication_json ?? []), $publication),
        ])->save();

        Log::info('Ratgeber zurueckgezogen.', $this->context($tenant, $draft) + [
            'article_id' => $post?->getKey(),
            'reason' => $reason,
        ]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Nachbereitung
    |--------------------------------------------------------------------------
    */

    /**
     * Abschluss einer Aktualisierung (#24).
     *
     * Zwei Dinge, beide erst hier moeglich:
     *
     *  - Der Aenderungshinweis bekommt sein Datum. Er entsteht beim
     *    Ueberarbeiten, erscheinen soll er mit dem Tag, an dem der Leser die
     *    neue Fassung sieht — dazwischen kann eine Pruefung liegen.
     *  - Die abgeloeste Fassung wird als abgeloest vermerkt. Sie behaelt
     *    ihren Status `published`: sie war veroeffentlicht, das bleibt wahr.
     *    Angezeigt wird ab jetzt die neue (PublicBlogController nimmt die
     *    juengste Fassung zum Artikel).
     *
     * Beides ist wiederholbar: ein zweiter Lauf findet kein leeres Datum mehr.
     */
    private function completeRefresh(ArticleDraft $draft): void
    {
        $entries = (array) ($draft->changelog_json ?? []);
        $stamped = false;

        foreach ($entries as $index => $entry) {
            if (! is_array($entry) || ($entry['at'] ?? null) !== null) {
                continue;
            }

            $entries[$index]['at'] = now()->toDateString();
            $stamped = true;
        }

        if ($stamped) {
            $draft->forceFill(['changelog_json' => $entries])->save();
        }

        if (! $draft->isRefresh()) {
            return;
        }

        $parent = $draft->parentDraft;

        if ($parent === null) {
            return;
        }

        $parent->forceFill([
            'needs_refresh' => false,
            'publication_json' => array_replace((array) ($parent->publication_json ?? []), [
                'superseded_by_draft_id' => (int) $draft->getKey(),
                'superseded_at' => now()->toIso8601String(),
            ]),
        ])->save();
    }

    /**
     * Alles, was nach dem Livegang passiert. Jeder Schritt ist einzeln
     * abgesichert: ein Ausfall haelt die uebrigen nicht auf.
     */
    private function afterPublish(Tenant $tenant, ArticleDraft $draft, Post $post): void
    {
        $url = $this->articleUrl($tenant, $post);

        $publication = (array) ($draft->publication_json ?? []);
        $publication['published_at'] = now()->toIso8601String();
        $publication['article_id'] = (int) $post->getKey();
        $publication['url'] = $url;
        $publication['fingerprint'] = $this->attempt('Fingerprint', $tenant, $draft, function () use ($tenant, $post, $draft, $url): array {
            $fingerprint = $this->fingerprints->register($tenant, $post, $draft, $url);

            return [
                'id' => (int) $fingerprint->getKey(),
                'has_embedding' => $fingerprint->embedding_json !== null,
            ];
        });

        // Was dieser Artikel verlinkt hat. Die Gegenrichtung — wer auf ihn
        // verweist — steht unter 'backlinks' und wird von den spaeteren
        // Artikeln geschrieben, nicht hier.
        $publication['linked_to'] = $this->attempt('Backlinks', $tenant, $draft, fn (): array => $this->registerBacklinks($post, $draft));

        $this->attempt('Cache', $tenant, $draft, function (): array {
            $this->flushCaches();

            return [];
        });

        $publication['sitemap'] = $this->attempt('Sitemap', $tenant, $draft, fn (): array => $this->writeSitemap($tenant));
        $publication['indexnow'] = $this->attempt('IndexNow', $tenant, $draft, fn (): array => $this->ping($tenant, $post));

        // Zwischenzeitlich kann ein anderer Lauf einen Backlink auf diesen
        // Entwurf geschrieben haben; der darf nicht verloren gehen.
        $draft->forceFill([
            'publication_json' => array_replace((array) ($draft->fresh()?->publication_json ?? []), $publication),
        ])->save();
    }

    /**
     * Traegt den neuen Artikel als Backlink bei jedem verlinkten eigenen
     * Ratgeber ein. Der Beitrag selbst wird nicht angefasst — der Verweis
     * steht im Protokoll des Zielentwurfs und erscheint auf dessen Seite
     * unter „verwandte Artikel" (PublicBlogController).
     *
     * @return array<int, array{article_id: int, url: string, title: string, linked_at: string}>
     */
    public function registerBacklinks(Post $post, ArticleDraft $draft): array
    {
        $slugs = $this->linkedArticleSlugs((string) ($draft->body_html ?: $post->body));

        if ($slugs === []) {
            return [];
        }

        $targets = Post::query()
            ->whereIn('slug', $slugs)
            ->whereKeyNot($post->getKey())
            ->get(['id', 'slug', 'title']);

        if ($targets->isEmpty()) {
            return [];
        }

        $entry = [
            'article_id' => (int) $post->getKey(),
            'url' => '/ratgeber/'.$post->slug,
            'title' => (string) $post->title,
            'linked_at' => now()->toIso8601String(),
        ];

        $written = [];

        foreach ($targets as $target) {
            $targetDraft = ArticleDraft::query()
                ->where('article_id', $target->getKey())
                ->latest('id')
                ->first();

            if ($targetDraft === null) {
                continue;
            }

            $publication = (array) ($targetDraft->publication_json ?? []);
            $backlinks = (array) ($publication['backlinks'] ?? []);

            // Ein Artikel steht hoechstens einmal in der Liste; ein
            // Refresh-Lauf (#24) darf sie nicht aufblaehen.
            $backlinks = array_values(array_filter(
                $backlinks,
                static fn ($existing): bool => (int) ($existing['article_id'] ?? 0) !== (int) $post->getKey(),
            ));
            $backlinks[] = $entry;

            $publication['backlinks'] = $backlinks;
            $targetDraft->forceFill(['publication_json' => $publication])->save();

            $written[] = [
                'article_id' => (int) $target->getKey(),
                'url' => '/ratgeber/'.$target->slug,
                'title' => (string) $target->title,
                'linked_at' => $entry['linked_at'],
            ];
        }

        return $written;
    }

    /**
     * Schreibt die Ratgeber-Sitemap neu. Nur diese eine Datei: der
     * vollstaendige Sitemap-Lauf (GenerateTenantSitemapJob) laeuft alle zwei
     * Stunden und wuerde hier den ganzen Firmenbestand mitziehen.
     *
     * @return array{file: string|null, written_at: string}
     */
    public function writeSitemap(Tenant $tenant): array
    {
        $directory = storage_path('app/public');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return [
            'file' => $this->sitemap->generate($this->baseUrl($tenant), $directory),
            'written_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Verwirft die Seitenzwischenspeicher, die eine Veroeffentlichung
     * veralten laesst: /llms.txt und /llms-full.txt, die Ratgeber-Seitenleiste
     * und die Artikelliste der Startseite.
     */
    public function flushCaches(): void
    {
        LlmsTxtBuilder::flush();

        foreach ((array) config('content.publishing.flush_cache_keys', []) as $key) {
            TenantCache::forget((string) $key);
        }
    }

    /**
     * Meldet Artikelseite, Ratgeber-Uebersicht und Kategorieseite an
     * IndexNow. Der Ping darf nie werfen.
     *
     * @return array<string, mixed>
     */
    public function ping(Tenant $tenant, ?Post $post): array
    {
        $base = $this->baseUrl($tenant);

        $category = trim((string) ($post?->category?->getAttribute('slug') ?? ''));

        $urls = array_filter([
            $post !== null ? "{$base}/ratgeber/{$post->slug}" : null,
            "{$base}/ratgeber",
            $category !== '' ? "{$base}/ratgeber/kategorie/{$category}" : null,
        ]);

        return $this->indexNow->submit($tenant, array_values($urls));
    }

    /*
    |--------------------------------------------------------------------------
    | Hilfsmittel
    |--------------------------------------------------------------------------
    */

    /**
     * Slugs eigener Ratgeber, auf die der Artikeltext verweist. Die Links
     * sind wurzelrelativ (InternalLinkResolver), absolute Adressen derselben
     * Domain werden trotzdem erkannt.
     *
     * @return array<int, string>
     */
    private function linkedArticleSlugs(string $html): array
    {
        if ($html === '' || ! preg_match_all('/href=["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $slugs = [];

        foreach ($matches[1] as $href) {
            $path = (string) (parse_url((string) $href, PHP_URL_PATH) ?? '');

            if (preg_match('#^/ratgeber/([a-z0-9\-]+)$#i', rtrim($path, '/'), $found) !== 1) {
                continue;
            }

            $slug = $found[1];

            // Die Unterseiten der Rubrik sind keine Artikel.
            if (in_array($slug, ['kategorie', 'tag', 'suche', 'redaktion', 'feed', 'vorschau'], true)) {
                continue;
            }

            $slugs[$slug] = $slug;
        }

        return array_values($slugs);
    }

    public function articleUrl(Tenant $tenant, Post $post): string
    {
        return $this->baseUrl($tenant).'/ratgeber/'.$post->slug;
    }

    private function baseUrl(Tenant $tenant): string
    {
        return $this->indexNow->baseUrl($tenant);
    }

    /**
     * Fuehrt einen Nachbereitungsschritt aus und schluckt seinen Fehler.
     *
     * @param  callable(): array<string, mixed>  $step
     * @return array<string, mixed>
     */
    private function attempt(string $label, Tenant $tenant, ArticleDraft $draft, callable $step): array
    {
        try {
            return $step();
        } catch (Throwable $exception) {
            Log::error("Veroeffentlichung: {$label} fehlgeschlagen.", $this->context($tenant, $draft) + [
                'exception' => $exception->getMessage(),
            ]);

            return ['error' => $exception->getMessage()];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Tenant $tenant, ArticleDraft $draft): array
    {
        return [
            'tenant_id' => $tenant->getKey(),
            'draft_id' => $draft->getKey(),
        ];
    }
}
