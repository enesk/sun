<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * 1:1-Erweiterung von `posts` um die Ratgeber-Felder der aktuellen Fassung.
 *
 * `last_checked_at` setzt jeder Lauf, `content_changed_at` nur eine
 * inhaltliche Aenderung; nur Letzteres ist das Sitemap-lastmod
 * (docs/guide-system.md, §5).
 *
 * `section_fact_map_json`: Fakt-Schluessel je Abschnitt (App\Guide\Research\SectionFactMap).
 *
 * `hero_image_*`: Titelbild des Themas (#20), einmalig erzeugt von
 * App\Guide\Jobs\GenerateTopicImageJob; der Pfad ist relativ zur Platte
 * 'public' und zeigt auf die groesste WebP-Variante.
 *
 * @property array<string, mixed>|null $section_fact_map_json
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property \Illuminate\Support\Carbon|null $content_changed_at
 * @property int|null $published_version_id
 * @property string|null $hero_image_path
 * @property string|null $hero_image_alt
 * @property int|null $hero_image_width
 * @property int|null $hero_image_height
 */
class ArticleDetail extends Model
{
    use TenantConnection;

    protected $table = 'guide_article_details';

    protected $fillable = [
        'article_id',
        'published_version_id',
        'short_answer',
        'faq_json',
        'key_facts_json',
        'changelog_json',
        'section_fact_map_json',
        'last_checked_at',
        'content_changed_at',
        'hero_image_path',
        'hero_image_alt',
        'hero_image_width',
        'hero_image_height',
    ];

    protected function casts(): array
    {
        return [
            'faq_json' => 'array',
            'key_facts_json' => 'array',
            'changelog_json' => 'array',
            'section_fact_map_json' => 'array',
            'last_checked_at' => 'datetime',
            'content_changed_at' => 'datetime',
            'hero_image_width' => 'integer',
            'hero_image_height' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    /**
     * Die Fassung, die gerade in posts steht (#12).
     */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class, 'published_version_id');
    }
}
