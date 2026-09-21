<?php

declare(strict_types=1);

namespace App\Guide\Publishing;

use App\Guide\Enums\RunStatus;
use App\Guide\Enums\TopicStatus;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Services\ArticleMapper;
use App\Guide\Support\GuidePageCache;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Einziger Weg, auf dem das Ratgebersystem in `posts` schreibt (#12).
 *
 * publish(): freigegebene Fassung eines Laufs veroeffentlichen. In einer
 * Transaktion: Fassung -> Artikel -> Details -> Thema -> Lauf. Danach
 * Seiten-Cache (GuidePageCache::flush(), schliesst Sitemap und llms.txt ein)
 * und bei inhaltlicher Aenderung IndexNow.
 *
 *  - create:    kein Post zum Thema; neuer Post mit dem Slug des Themas,
 *               published_at = jetzt.
 *  - update:    Post vorhanden (auch ein per Slug-Uebernahme verknuepfter
 *               Altartikel, #19/#24); Inhalt ueberschrieben, Slug, Autor und
 *               published_at bleiben. content_changed_at = jetzt.
 *  - unchanged: Fassung inhaltsgleich zur veroeffentlichten; nur
 *               last_checked_at, kein lastmod, kein Ping.
 *
 * content_changed_at (dateModified, Sitemap-lastmod) setzen nur create,
 * update und ein inhaltlich wirksamer Rollback; last_checked_at setzt jeder
 * erfolgreiche Lauf, auch einer ohne Aenderung (markChecked()).
 *
 * Muss im Tenant-Kontext laufen (Seiten-Cache, IndexNow-Domain).
 */
class GuidePublisher
{
    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_UNCHANGED = 'unchanged';

    public function __construct(
        private readonly ArticleMapper $mapper,
        private readonly VersionStore $versions,
        private readonly IndexNowClient $indexNow,
    ) {}

    /**
     * Veroeffentlicht die Fassung und setzt den Lauf auf published.
     *
     * @return array<string, mixed> Protokoll, wie in guide_topic_runs.publish_json
     */
    public function publish(TopicRun $run, ArticleVersion $version): array
    {
        if ((int) $version->guide_topic_run_id !== (int) $run->getKey()) {
            throw new InvalidArgumentException("Fassung {$version->getKey()} gehoert nicht zu Lauf {$run->getKey()}.");
        }

        $now = Carbon::now();

        /** @var array{0: string, 1: Post, 2: Topic} $result */
        $result = DB::connection($this->connection())->transaction(function () use ($run, $version, $now): array {
            /** @var Topic $topic */
            $topic = Topic::query()->lockForUpdate()->findOrFail($run->guide_topic_id);
            $post = $topic->article_id !== null ? Post::query()->find($topic->article_id) : null;
            $current = $post !== null ? $this->versions->current($post) : null;

            $action = match (true) {
                $post === null => self::ACTION_CREATE,
                $current !== null && $this->versions->fingerprint($current) === $this->versions->fingerprint($version) => self::ACTION_UNCHANGED,
                default => self::ACTION_UPDATE,
            };

            if ($action === self::ACTION_UNCHANGED) {
                ArticleDetail::query()->where('article_id', $post->getKey())->update(['last_checked_at' => $now]);
            } else {
                $post = $this->writeArticle($topic, $version, $post, $now);
            }

            $this->touchTopic($topic, $post, $now);

            if (! $run->status->canTransitionTo(RunStatus::PUBLISHED)) {
                throw new RuntimeException("Lauf {$run->getKey()} steht auf {$run->status->value} und kann nicht veroeffentlicht werden.");
            }

            $run->forceFill([
                'status' => RunStatus::PUBLISHED,
                'finished_at' => $now,
                'publish_json' => [
                    'action' => $action,
                    'article_id' => (int) $post->getKey(),
                    'version_id' => (int) $version->getKey(),
                    'version' => (int) $version->version,
                    'published_at' => $now->toIso8601String(),
                    'indexnow' => null,
                ],
            ])->save();

            return [$action, $post, $topic];
        });

        [$action, $post, $topic] = $result;

        GuidePageCache::flush();

        $log = (array) $run->publish_json;

        if ($action !== self::ACTION_UNCHANGED) {
            $log['indexnow'] = $this->ping($this->urls($post, $topic, $action === self::ACTION_CREATE));
            $run->forceFill(['publish_json' => $log])->save();
        }

        return $log;
    }

    /**
     * Stellt Inhalt, FAQ, Key-Facts, Kurzantwort, Meta und Changelog einer
     * frueheren Fassung wieder her. Legt dafuer eine neue Fassung an, die
     * alte bleibt unveraendert in der Historie. dateModified springt nur,
     * wenn sich der sichtbare Inhalt tatsaechlich aendert.
     */
    public function rollback(Post $article, ArticleVersion $version, ?string $note = null): ArticleVersion
    {
        if ((int) $version->article_id !== (int) $article->getKey()) {
            throw new InvalidArgumentException("Fassung {$version->getKey()} gehoert nicht zu Artikel {$article->getKey()}.");
        }

        if ($article->guide_topic_id === null) {
            throw new InvalidArgumentException("Artikel {$article->getKey()} gehoert zu keinem Ratgeber-Thema.");
        }

        $now = Carbon::now();

        /** @var array{0: ArticleVersion, 1: bool, 2: Topic} $result */
        $result = DB::connection($this->connection())->transaction(function () use ($article, $version, $note, $now): array {
            /** @var Topic $topic */
            $topic = Topic::query()->lockForUpdate()->findOrFail($article->guide_topic_id);
            $current = $this->versions->current($article);
            $changed = $current === null || $this->versions->fingerprint($current) !== $this->versions->fingerprint($version);

            $summary = trim("Rollback auf Version {$version->version}".($note !== null && trim($note) !== '' ? ': '.trim($note) : ''));
            $copy = $this->versions->copyAsNew($version, $topic, mb_substr($summary, 0, 1000), $now);

            $article->fill($this->mapper->toUpdatedPostAttributes($topic, $copy, $copy->short_answer, $copy->meta_title, $copy->meta_description))->save();

            $detail = ArticleDetail::query()->firstOrNew(['article_id' => $article->getKey()]);
            $detail->fill([
                ...$this->mapper->toDetailAttributes($copy, $copy->short_answer, (array) ($copy->changelog_json ?? [])),
                'published_version_id' => $copy->getKey(),
            ]);

            if ($changed || $detail->content_changed_at === null) {
                $detail->content_changed_at = $now;
            }

            $detail->save();

            return [$copy, $changed, $topic];
        });

        [$copy, $changed, $topic] = $result;

        GuidePageCache::flush();

        $indexNow = $changed ? $this->ping($this->urls($article, $topic, false)) : null;

        Log::info('Ratgeber: Rollback durchgefuehrt.', [
            'tenant_id' => tenant()?->getTenantKey(),
            'article_id' => $article->getKey(),
            'from_version' => $version->version,
            'new_version' => $copy->version,
            'content_changed' => $changed,
            'indexnow' => $indexNow['status'] ?? null,
        ]);

        return $copy;
    }

    /**
     * Erfolgreicher Lauf ohne neue Fassung (Probe oder Tiefenrecherche ohne
     * Aenderung): nur "Zuletzt geprueft", nie lastmod.
     */
    public function markChecked(Topic $topic, ?Carbon $at = null): void
    {
        if ($topic->article_id === null) {
            return;
        }

        $updated = ArticleDetail::query()
            ->where('article_id', $topic->article_id)
            ->update(['last_checked_at' => $at ?? Carbon::now()]);

        if ($updated > 0) {
            GuidePageCache::flush();
        }
    }

    private function writeArticle(Topic $topic, ArticleVersion $version, ?Post $post, Carbon $now): Post
    {
        $shortAnswer = $version->short_answer;

        if ($post === null) {
            /** @var Post $post */
            $post = Post::query()->create([
                ...$this->mapper->toNewPostAttributes($topic, $version, $shortAnswer, $version->meta_title, $version->meta_description),
                'published_at' => $now,
            ]);
        } else {
            // Slug, Autor, Status und published_at bleiben: die URL aendert sich nie.
            $post->fill($this->mapper->toUpdatedPostAttributes($topic, $version, $shortAnswer, $version->meta_title, $version->meta_description))->save();
        }

        $this->versions->markPublished($version, $post, $now);

        ArticleDetail::query()->updateOrCreate(
            ['article_id' => $post->getKey()],
            [
                ...$this->mapper->toDetailAttributes($version, $shortAnswer, (array) ($version->changelog_json ?? [])),
                'published_version_id' => $version->getKey(),
                'last_checked_at' => $now,
                'content_changed_at' => $now,
            ],
        );

        return $post;
    }

    private function touchTopic(Topic $topic, Post $post, Carbon $now): void
    {
        $attributes = [
            'article_id' => $post->getKey(),
            'last_checked_at' => $now,
            'consecutive_failures' => 0,
        ];

        // Erste Veroeffentlichung: das Thema geht in den regulaeren Pruefrhythmus.
        if ($topic->status === TopicStatus::DRAFT && $topic->status->canTransitionTo(TopicStatus::ACTIVE)) {
            $attributes['status'] = TopicStatus::ACTIVE;
        }

        if ($topic->next_due_at === null) {
            $attributes['next_due_at'] = $now->copy()->addDays($topic->refreshIntervalDays());
        }

        $topic->forceFill($attributes)->save();
    }

    /**
     * Artikel immer; bei Neuanlage auch Kategorie und Uebersicht, deren
     * Listen sich damit aendern.
     *
     * @return array<int, string>
     */
    private function urls(Post $post, Topic $topic, bool $created): array
    {
        $urls = [route('guide.show', $post->slug, false)];

        if (! $created) {
            return $urls;
        }

        $category = $topic->category;

        if ($category !== null && $category->is_visible) {
            $urls[] = route('guide.category', $category->slug, false);
        }

        $urls[] = route('guide.index', [], false);

        return $urls;
    }

    /**
     * IndexNow darf nie blockieren: jeder Fehler landet im Protokoll.
     *
     * @param  array<int, string>  $urls
     * @return array<string, mixed>|null
     */
    private function ping(array $urls): ?array
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            Log::warning('Ratgeber: IndexNow ohne Tenant-Kontext uebersprungen.', ['urls' => $urls]);

            return null;
        }

        try {
            return $this->indexNow->submit($tenant, $urls);
        } catch (Throwable $exception) {
            Log::warning('Ratgeber: IndexNow-Meldung fehlgeschlagen.', [
                'tenant_id' => $tenant->getKey(),
                'urls' => $urls,
                'message' => $exception->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => mb_substr($exception->getMessage(), 0, 300)];
        }
    }

    private function connection(): ?string
    {
        return (new Post)->getConnectionName();
    }
}
