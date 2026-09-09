<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Models\Portal\Post;
use Illuminate\Support\Str;

/**
 * Slugs der Ratgeber-Artikel (#14).
 *
 * Zwei Aufgaben, die zusammengehoeren:
 *
 *  1. Aus dem Titel einen sauberen Slug bilden und dabei die
 *     Region-Doppelung entfernen. Titel wie "Waermepumpe Muenchen: Foerderung
 *     in Muenchen" entstehen, weil Outline und Meta-Stufe die Region beide
 *     nennen; im Slug ist das ein Rankingnachteil und sieht nach Doorway aus.
 *
 *  2. Kollisionen im Mandanten aufloesen. Ein Zaehlersuffix ist nur dann
 *     richtig, wenn zwei verschiedene Themen zufaellig denselben Slug
 *     ergeben. Deckt sich auch das Hauptkeyword, ist es dasselbe Thema — dann
 *     darf kein zweiter Artikel entstehen, und der Aufrufer erfaehrt das ueber
 *     `duplicateOf`.
 *
 * Geprueft wird gegen `posts` (veroeffentlichte Artikel) und gegen
 * `article_drafts` (Entwuerfe in der Pipeline), damit zwei Entwuerfe desselben
 * Tages nicht mit demselben Slug bis zur Veroeffentlichung laufen.
 *
 * Laeuft im Tenant-Kontext.
 */
class SlugService
{
    /** Laengengrenze des Slugs; `posts.slug` ist ein String-Feld. */
    private const MAX_LENGTH = 120;

    /**
     * Slug aus dem Titel, ohne Region-Doppelung.
     */
    public function fromTitle(string $title, ?string $regionName = null): string
    {
        $slug = Str::slug($title);
        $slug = $this->dedupeRegion($slug, $regionName);
        $slug = $this->dedupeWords($slug);

        return Str::limit($slug, self::MAX_LENGTH, '');
    }

    /**
     * Freier Slug fuer einen Entwurf.
     *
     * @return array{slug: string, duplicate_of: ?int} duplicate_of = posts.id des
     *                                                 gleichnamigen Artikels zum
     *                                                 selben Keyword
     */
    public function resolve(
        string $title,
        string $primaryKeyword,
        ?string $regionName = null,
        ?int $exceptDraftId = null,
    ): array {
        $base = $this->fromTitle($title, $regionName);

        if ($base === '') {
            $base = Str::limit(Str::slug($primaryKeyword), self::MAX_LENGTH, '');
        }

        $duplicate = $this->duplicateArticle($base, $primaryKeyword);

        if ($duplicate !== null) {
            return ['slug' => $base, 'duplicate_of' => (int) $duplicate->getKey()];
        }

        $slug = $base;
        $suffix = 2;

        while ($this->taken($slug, $exceptDraftId)) {
            $slug = Str::limit($base, self::MAX_LENGTH - 3, '')."-{$suffix}";
            $suffix++;
        }

        return ['slug' => $slug, 'duplicate_of' => null];
    }

    /**
     * Der Artikel, der denselben Slug und dasselbe Hauptkeyword traegt — also
     * kein Namensunfall, sondern dasselbe Thema.
     */
    private function duplicateArticle(string $slug, string $primaryKeyword): ?Post
    {
        $keyword = mb_strtolower(trim($primaryKeyword));

        if ($keyword === '') {
            return null;
        }

        $post = Post::query()->where('slug', $slug)->first(['id', 'title', 'slug']);

        if ($post === null) {
            return null;
        }

        $draft = ArticleDraft::query()
            ->where('article_id', $post->getKey())
            ->with('topicCandidate:id,primary_keyword')
            ->first();

        $existingKeyword = mb_strtolower(trim(
            (string) ($draft?->topicCandidate?->primary_keyword ?? ''),
        ));

        return $existingKeyword !== '' && $existingKeyword === $keyword ? $post : null;
    }

    private function taken(string $slug, ?int $exceptDraftId): bool
    {
        $inPosts = Post::query()->where('slug', $slug)->exists();

        if ($inPosts) {
            return true;
        }

        return ArticleDraft::query()
            ->where('slug', $slug)
            ->when($exceptDraftId !== null, fn ($query) => $query->whereKeyNot($exceptDraftId))
            ->exists();
    }

    /**
     * Nennt der Slug die Region mehrfach, bleibt das erste Vorkommen stehen.
     */
    private function dedupeRegion(string $slug, ?string $regionName): string
    {
        $region = Str::slug((string) $regionName);

        if ($region === '' || $slug === '') {
            return $slug;
        }

        $parts = explode('-', $slug);
        $needle = explode('-', $region);
        $length = count($needle);
        $result = [];
        $seen = false;

        for ($index = 0; $index < count($parts);) {
            $window = array_slice($parts, $index, $length);

            if ($window === $needle) {
                if (! $seen) {
                    $result = array_merge($result, $needle);
                    $seen = true;
                }

                $index += $length;

                continue;
            }

            $result[] = $parts[$index];
            $index++;
        }

        return implode('-', $result);
    }

    /**
     * Unmittelbar wiederholte Woerter entfernen ("foerderung-foerderung").
     */
    private function dedupeWords(string $slug): string
    {
        $parts = explode('-', $slug);
        $result = [];

        foreach ($parts as $part) {
            if ($part === '' || ($result !== [] && end($result) === $part)) {
                continue;
            }

            $result[] = $part;
        }

        return implode('-', $result);
    }
}
