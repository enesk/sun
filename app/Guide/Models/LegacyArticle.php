<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Anzeigebloecke eines Altartikels (#34): Beitraege, die die alte
 * Content-Pipeline veroeffentlicht hat und die kein Ratgeber-Thema haben.
 *
 * Einmalig aus deren Entwurfstabelle uebernommen (Migration
 * tenant/2026_09_23_000002) und danach nur noch gelesen — von
 * GuidePageData, PublicBlogController, Sitemap und llms.txt ueber den
 * ArticleBlockPresenter. Es gibt keinen Schreibweg im Code.
 *
 * @property int $article_id
 * @property string $title
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $short_answer
 * @property string|null $body_html
 * @property array<array-key, mixed>|null $key_facts_json
 * @property array<array-key, mixed>|null $faq_json
 * @property array<int, array<string, mixed>>|null $sources_json
 * @property array<int, mixed>|null $changelog_json
 * @property array<string, mixed>|null $outline_json
 * @property array<string, mixed>|null $assets_json
 * @property array<int, int>|null $backlinks_json
 * @property string|null $hero_image_alt
 * @property string|null $hero_image_credit
 * @property string|null $hero_image_source
 * @property string $region_scope
 * @property string|null $region_code
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon|null $content_updated_at
 * @property Post|null $article
 */
class LegacyArticle extends Model
{
    use TenantConnection;

    protected $table = 'guide_legacy_articles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'key_facts_json' => 'array',
            'faq_json' => 'array',
            'sources_json' => 'array',
            'changelog_json' => 'array',
            'outline_json' => 'array',
            'assets_json' => 'array',
            'backlinks_json' => 'array',
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    /**
     * Zeile zu einem Beitrag; null, solange die Tabelle fehlt (Portal noch
     * nicht migriert) oder der Beitrag nie aus der Pipeline kam.
     */
    public static function forArticle(int $articleId): ?self
    {
        if (! static::tableExists()) {
            return null;
        }

        return static::query()->where('article_id', $articleId)->first();
    }

    public static function tableExists(): bool
    {
        $model = new self;

        return $model->getConnection()->getSchemaBuilder()->hasTable($model->getTable());
    }

    /**
     * Aenderungshinweise, aelteste zuerst.
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

    /**
     * Beitraege, die beim Veroeffentlichen auf diesen verlinkt haben (#21).
     *
     * @return array<int, int>
     */
    public function backlinkArticleIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->backlinks_json ?? [])),
            static fn (int $id): bool => $id > 0,
        )));
    }
}
