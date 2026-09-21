<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Topic;
use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use Illuminate\Support\Str;

/**
 * Uebersetzt eine Artikelfassung des Ratgebersystems in die bestehende
 * Artikel-Tabelle `posts` und in ihre 1:1-Erweiterung `guide_article_details`.
 *
 * Die reale Spaltenstruktur von `posts` ist in docs/guide-system.md, §4.1
 * dokumentiert. Der Mapper ist die einzige Stelle im Ratgebersystem, die beide
 * Strukturen kennt; aendert sich `posts`, aendert sich nur diese Klasse.
 *
 * Er schreibt nichts: Anlegen, Versionieren und Rollback macht der Publisher (#12).
 */
class ArticleMapper
{
    private const EXCERPT_LENGTH = 297;

    private const META_DESCRIPTION_LENGTH = 155;

    /**
     * Attribute fuer Post::create(). Der Slug des Themas wird uebernommen,
     * solange ihn kein anderer Beitrag belegt.
     *
     * @return array<string, mixed>
     */
    public function toNewPostAttributes(
        Topic $topic,
        ArticleVersion $version,
        ?string $shortAnswer = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
    ): array {
        return [
            ...$this->contentAttributes($topic, $version, $shortAnswer, $metaTitle, $metaDescription),
            'slug' => $this->uniqueSlug($topic->slug, $topic->article_id),
            'author_id' => $this->authorId(),
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ];
    }

    /**
     * Attribute fuer Post::fill() bei einer neuen Fassung oder einem Rollback.
     * Slug, Autor und Erstveroeffentlichung bleiben unangetastet, damit URL
     * und Datum stabil sind.
     *
     * @return array<string, mixed>
     */
    public function toUpdatedPostAttributes(
        Topic $topic,
        ArticleVersion $version,
        ?string $shortAnswer = null,
        ?string $metaTitle = null,
        ?string $metaDescription = null,
    ): array {
        return $this->contentAttributes($topic, $version, $shortAnswer, $metaTitle, $metaDescription);
    }

    /**
     * Attribute fuer ArticleDetail::updateOrCreate(['article_id' => ...], ...).
     * Die Zeitstempel (last_checked_at, content_changed_at) setzt der Publisher.
     *
     * @param  array<int, array<string, mixed>>  $changelog
     * @return array<string, mixed>
     */
    public function toDetailAttributes(ArticleVersion $version, ?string $shortAnswer, array $changelog): array
    {
        return [
            'short_answer' => $shortAnswer,
            'faq_json' => $version->faq_json,
            'key_facts_json' => $version->key_facts_json,
            'changelog_json' => $changelog,
            'section_fact_map_json' => $version->section_fact_map_json,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentAttributes(
        Topic $topic,
        ArticleVersion $version,
        ?string $shortAnswer,
        ?string $metaTitle,
        ?string $metaDescription,
    ): array {
        $body = (string) $version->body_html;
        $excerpt = $this->excerpt($shortAnswer, $body);

        return [
            'title' => Str::limit((string) $version->title, 255, ''),
            'excerpt' => $excerpt,
            'body' => $body,
            'category_id' => $this->postCategoryId($topic),
            'guide_topic_id' => $topic->getKey(),
            'guide_category_id' => $topic->guide_category_id,
            'meta_title' => Str::limit($metaTitle ?: (string) $version->title, 255, ''),
            'meta_description' => Str::limit(trim($metaDescription ?: $excerpt), self::META_DESCRIPTION_LENGTH),
            // Post::booted() rechnet die Lesezeit nur beim Update des body nach, nicht beim Anlegen.
            'reading_time_minutes' => Post::calculateReadingTime($body),
        ];
    }

    /**
     * Slug in `posts`, bei Kollision mit einem handgeschriebenen Beitrag mit Zaehler.
     */
    public function uniqueSlug(string $base, ?int $exceptArticleId = null): string
    {
        $base = Str::slug($base);
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $exceptArticleId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugTaken(string $slug, ?int $exceptArticleId): bool
    {
        return Post::query()
            ->where('slug', $slug)
            ->when($exceptArticleId !== null, fn ($query) => $query->whereKeyNot($exceptArticleId))
            ->exists();
    }

    /**
     * `posts.category_id` zeigt auf `post_categories`, die Verwaltung und das
     * bisherige Frontend lesen es weiter. Belegt wird es mit der Post-Kategorie
     * gleichen Slugs, sonst bleibt es leer; massgeblich fuer das Ratgebersystem
     * ist guide_category_id.
     */
    private function postCategoryId(Topic $topic): ?int
    {
        $slug = $topic->category?->slug;

        if ($slug === null) {
            return null;
        }

        $id = PostCategory::query()->where('slug', $slug)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Die Kurzantwort ist der Anrisstext; fehlt sie, wird der Fliesstext gekuerzt.
     */
    private function excerpt(?string $shortAnswer, string $body): string
    {
        $source = $shortAnswer ?: $body;

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($source)) ?? ''), self::EXCERPT_LENGTH);
    }

    /**
     * `posts.author_id` verweist auf einen Benutzer der Central-DB. Bis zum
     * Rueckbau (#19) gilt derselbe Redaktionsbenutzer wie in der alten Pipeline.
     */
    private function authorId(): int
    {
        return (int) config('content.publishing.author_user_id', 1);
    }
}
