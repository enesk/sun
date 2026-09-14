<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Models\Portal\Post;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uebersetzt einen Artikelentwurf in die bestehende Artikel-Tabelle des
 * Portals (`posts`).
 *
 * Die Spaltenstruktur von `posts` ist in docs/content-pipeline.md, §9
 * dokumentiert. Der Mapper ist die einzige Stelle, die beide Strukturen kennt —
 * aendert sich `posts`, aendert sich nur diese Klasse.
 *
 * Die Lesezeit wird hier gesetzt: das Post-Modell rechnet sie nur beim
 * Aktualisieren des Fliesstexts nach, nicht beim Anlegen.
 */
class ArticleMapper
{
    /**
     * Attribut-Array fuer Post::create() bzw. Post::fill().
     *
     * @return array<string, mixed>
     */
    public function toAttributes(ArticleDraft $draft, ?TenantContentSetting $settings = null): array
    {
        $settings ??= TenantContentSetting::current();

        return [
            'title' => $draft->title,
            'slug' => $this->uniqueSlug($draft),
            'excerpt' => $this->excerpt($draft),
            'body' => (string) $draft->body_html,
            'category_id' => $settings->categoryIdFor($draft->topicCandidate?->cluster?->slug),
            'author_id' => $this->authorId(),
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => $draft->published_at ?? $draft->scheduled_for ?? now(),
            // Titelbild aus #16. `posts.featured_image` wird als URL gelesen
            // (Post::getFeaturedImageUrlAttribute), der Entwurf haelt daneben
            // den Pfad auf der mandantenspezifischen Platte.
            'featured_image' => $this->featuredImage($draft),
            'meta_title' => $draft->meta_title ?: $draft->title,
            'meta_description' => $this->metaDescription($draft),
            'reading_time_minutes' => Post::calculateReadingTime((string) $draft->body_html),
        ];
    }

    /**
     * Legt den Artikel an und verknuepft ihn mit dem Entwurf.
     */
    public function createArticle(ArticleDraft $draft, ?TenantContentSetting $settings = null): Post
    {
        $post = Post::create($this->toAttributes($draft, $settings));

        $draft->forceFill([
            'article_id' => $post->getKey(),
            'published_at' => $post->published_at,
        ])->save();

        return $post;
    }

    /**
     * Schreibt einen aktualisierten Entwurf in den bereits veroeffentlichten
     * Artikel zurueck (Refresh-Loop, #24). Slug und Veroeffentlichungsdatum
     * bleiben unangetastet, damit URL und Erstveroeffentlichung stabil sind.
     */
    public function updateArticle(Post $post, ArticleDraft $draft, ?TenantContentSetting $settings = null): Post
    {
        $attributes = $this->toAttributes($draft, $settings);

        unset($attributes['slug'], $attributes['published_at'], $attributes['author_id']);

        $post->fill($attributes)->save();

        return $post;
    }

    /**
     * Slug des Entwurfs, bei Kollision in `posts` mit Zaehler.
     */
    public function uniqueSlug(ArticleDraft $draft): string
    {
        $base = Str::slug($draft->slug ?: $draft->title);
        $slug = $base;
        $suffix = 2;

        while ($this->slugTaken($slug, $draft->article_id)) {
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
     * Der Kurzantwort-Absatz ist der Anrisstext. Fehlt er, wird der Fliesstext
     * gekuerzt.
     */
    private function excerpt(ArticleDraft $draft): string
    {
        $source = $draft->short_answer ?: strip_tags((string) $draft->body_html);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($source)) ?? ''), 297);
    }

    /**
     * Oeffentliche URL der groessten Hero-Variante (#16). Sie steht im
     * Asset-Bericht des Entwurfs; ohne ihn wird der Pfad ueber die Platte
     * aufgeloest, auf der der Job ihn abgelegt hat.
     */
    private function featuredImage(ArticleDraft $draft): ?string
    {
        $url = Arr::get((array) ($draft->assets_json ?? []), 'hero.variants.0.url');

        if (is_string($url) && $url !== '') {
            return $url;
        }

        if ($draft->hero_image_path === null) {
            return null;
        }

        return Storage::disk((string) config('content.assets.disk', 'public'))->url($draft->hero_image_path);
    }

    private function metaDescription(ArticleDraft $draft): string
    {
        $source = $draft->meta_description ?: $this->excerpt($draft);

        return Str::limit(trim($source), 155);
    }

    /**
     * Autor des Artikels. `posts.author_id` verweist auf einen Benutzer der
     * Central-DB; der angemeldete Administrator soll dort nie stehen,
     * deshalb kommt die ID aus der Konfiguration.
     */
    private function authorId(): int
    {
        return (int) config('content.publishing.author_user_id', 1);
    }
}
