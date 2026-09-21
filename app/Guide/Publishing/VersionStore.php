<?php

declare(strict_types=1);

namespace App\Guide\Publishing;

use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Topic;
use App\Models\Portal\Post;
use Illuminate\Support\Carbon;

/**
 * Versionshistorie der Ratgeber-Artikel (guide_article_versions, #12).
 *
 * Fassungen werden nie geaendert, nur angelegt, verknuepft und beim
 * Aufraeumen geloescht. Die Nummer zaehlt je Thema bzw. Artikel fortlaufend
 * (Unique auf article_id, version); der Writer (#10) legt die Fassung eines
 * Laufs an, der Publisher verknuepft sie beim Veroeffentlichen mit dem
 * Artikel und stempelt published_at. Ein Rollback legt eine Kopie der alten
 * Fassung als neue Nummer an.
 */
class VersionStore
{
    public function nextNumber(Topic $topic): int
    {
        return 1 + (int) ArticleVersion::query()
            ->where(fn ($query) => $query
                ->where('guide_topic_id', $topic->getKey())
                ->when($topic->article_id !== null, fn ($inner) => $inner->orWhere('article_id', $topic->article_id)))
            ->max('version');
    }

    /**
     * Haengt die Fassung und alle noch unverknuepften Fassungen des Themas
     * (Entwuerfe vor dem ersten Post) an den Artikel und markiert die
     * veroeffentlichte Fassung.
     */
    public function markPublished(ArticleVersion $version, Post $post, Carbon $at): void
    {
        ArticleVersion::query()
            ->where('guide_topic_id', $version->guide_topic_id)
            ->whereNull('article_id')
            ->update(['article_id' => $post->getKey()]);

        $version->forceFill(['article_id' => $post->getKey(), 'published_at' => $at])->save();
    }

    /**
     * Neue Fassung mit dem Inhalt einer alten (Rollback). Laeuft ohne Lauf,
     * guide_topic_run_id bleibt leer.
     */
    public function copyAsNew(ArticleVersion $source, Topic $topic, string $changeSummary, Carbon $at): ArticleVersion
    {
        $copy = $source->replicate(['published_at']);
        $copy->forceFill([
            'article_id' => $source->article_id,
            'guide_topic_id' => $topic->getKey(),
            'guide_topic_run_id' => null,
            'version' => $this->nextNumber($topic),
            'change_summary' => $changeSummary,
            'published_at' => $at,
        ])->save();

        return $copy;
    }

    /**
     * Die Fassung, die gerade in posts steht.
     */
    public function current(Post $post): ?ArticleVersion
    {
        $id = ArticleDetail::query()->where('article_id', $post->getKey())->value('published_version_id');

        return $id !== null ? ArticleVersion::query()->find($id) : null;
    }

    /**
     * Fingerabdruck des sichtbaren Inhalts. Gleicher Abdruck = keine
     * inhaltliche Aenderung, dateModified und lastmod bleiben stehen.
     */
    public function fingerprint(ArticleVersion $version): string
    {
        return sha1((string) json_encode([
            $version->title,
            $version->meta_title,
            $version->meta_description,
            $version->short_answer,
            $version->body_html,
            $version->faq_json,
            $version->key_facts_json,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Loescht je Artikel alles ueber $keep Fassungen, aelteste zuerst. Die
     * erste Fassung und die veroeffentlichte bleiben immer, zaehlen aber mit.
     *
     * @return int Zahl der geloeschten Fassungen
     */
    public function prune(int $keep, bool $dryRun = false): int
    {
        $keep = max(2, $keep);
        $deleted = 0;

        $articleIds = ArticleVersion::query()
            ->whereNotNull('article_id')
            ->groupBy('article_id')
            ->havingRaw('count(*) > ?', [$keep])
            ->pluck('article_id');

        foreach ($articleIds as $articleId) {
            $versions = ArticleVersion::query()
                ->where('article_id', $articleId)
                ->orderBy('version')
                ->pluck('id')
                ->all();

            $protected = array_filter([
                $versions[0],
                ArticleDetail::query()->where('article_id', $articleId)->value('published_version_id'),
            ]);

            $excess = count($versions) - $keep;
            $candidates = array_values(array_diff($versions, $protected));
            $doomed = array_slice($candidates, 0, max(0, $excess));

            if ($doomed === []) {
                continue;
            }

            $deleted += $dryRun ? count($doomed) : ArticleVersion::query()->whereKey($doomed)->delete();
        }

        return $deleted;
    }
}
