<?php

declare(strict_types=1);

namespace App\Guide\Models;

use App\Models\Portal\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Eine Fassung eines Ratgeber-Artikels, Grundlage fuer Diff und Rollback (#12).
 * Versionen werden nie geaendert, nur neu angelegt; deshalb gibt es nur
 * `created_at`.
 *
 * `article_id` ist leer, solange die erste Fassung noch nicht veroeffentlicht
 * ist; die Zuordnung haelt dann `guide_topic_id`.
 *
 * Seit #10 traegt die Fassung alles, was der Publisher (#12) in posts und
 * guide_article_details schreibt: Kurzantwort, Meta, den vollstaendigen
 * Changelog (juengster Eintrag zuerst) und die SectionFactMap.
 *
 * @property array<int, array<string, mixed>>|null $faq_json
 * @property array<int, array<string, mixed>>|null $key_facts_json
 * @property array<int, array<string, mixed>>|null $changelog_json
 * @property array<string, mixed>|null $section_fact_map_json
 * @property \Illuminate\Support\Carbon|null $published_at
 *
 * `published_at` (#12): wann die Fassung live ging; leer bei Entwuerfen aus
 * der Pruef-Queue und bei inhaltsgleichen Fassungen, die der Publisher nicht
 * uebernommen hat.
 */
class ArticleVersion extends Model
{
    use TenantConnection;

    public const UPDATED_AT = null;

    protected $table = 'guide_article_versions';

    protected $fillable = [
        'article_id',
        'guide_topic_id',
        'guide_topic_run_id',
        'version',
        'title',
        'meta_title',
        'meta_description',
        'body_html',
        'short_answer',
        'faq_json',
        'key_facts_json',
        'changelog_json',
        'section_fact_map_json',
        'change_summary',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'faq_json' => 'array',
            'key_facts_json' => 'array',
            'changelog_json' => 'array',
            'section_fact_map_json' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'article_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'guide_topic_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TopicRun::class, 'guide_topic_run_id');
    }
}
