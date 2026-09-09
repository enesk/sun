<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Redaktionelle Einstellungen des Mandanten. Genau eine Zeile je Tenant-DB.
 */
class TenantContentSetting extends Model
{
    use TenantConnection;

    protected $fillable = [
        'articles_per_day',
        'is_active',
        'activated_at',
        'auto_publish_threshold',
        'is_ymyl',
        'tone',
        'allowed_region_scopes_json',
        'preferred_states_json',
        'branch_keywords_json',
        'publish_window_start',
        'publish_window_end',
        'author_name',
        'author_bio',
        'organization_same_as_json',
        'author_same_as_json',
        'category_mapping_json',
        'cluster_performance_json',
        'brand_colors_json',
        'scoring_weights_json',
        'gsc_property',
    ];

    protected function casts(): array
    {
        return [
            'articles_per_day' => 'integer',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'auto_publish_threshold' => 'integer',
            'is_ymyl' => 'boolean',
            'allowed_region_scopes_json' => 'array',
            'preferred_states_json' => 'array',
            'branch_keywords_json' => 'array',
            'organization_same_as_json' => 'array',
            'author_same_as_json' => 'array',
            'category_mapping_json' => 'array',
            'cluster_performance_json' => 'array',
            'brand_colors_json' => 'array',
            'scoring_weights_json' => 'array',
        ];
    }

    /**
     * Einstellungen des aktuellen Tenants; legt beim ersten Zugriff die
     * Defaults an, damit die Pipeline nie ohne Konfiguration dasteht.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'articles_per_day' => (int) config('content.targets.articles_per_tenant_per_day', 2),
        ]);
    }

    /**
     * Dimensionen des Themen-Scorings (#12). Schluessel sind die Teilscores
     * aus `topic_candidates`; die Beschriftungen stehen im Content-Panel.
     *
     * @return array<string, string>
     */
    public static function scoringDimensions(): array
    {
        return [
            'trend' => __('Trend'),
            'demand' => __('Nachfrage'),
            'gap' => __('Lücke'),
            'seasonal' => __('Saison'),
            'uniqueness' => __('Eigenständigkeit'),
            'performance' => __('Performance'),
        ];
    }

    /**
     * Gewichte des Themen-Scorings, wie sie das Content-Panel als
     * Schieberegler zeigt: ganze Prozentpunkte je Dimension. Ohne eigene
     * Einstellung gilt die Vorgabe aus config('content.topics.weights')
     * (Trend 30, Nachfrage 25, Luecke 20, Saison 15, Eigenstaendigkeit 10,
     * Performance 10).
     *
     * @return array<string, int>
     */
    public function scoringWeights(): array
    {
        $stored = $this->scoring_weights_json ?? [];
        $defaults = (array) config('content.topics.weights', []);
        $weights = [];

        foreach (array_keys(static::scoringDimensions()) as $key) {
            $weights[$key] = (int) round((float) ($stored[$key] ?? ((float) ($defaults[$key] ?? 0.2) * 100)));
        }

        return $weights;
    }

    /**
     * Dieselben Gewichte fuer die Rechnung im TopicScorer (#12): auf die
     * Summe 1.0 normiert, damit eine unvollstaendige oder uebergrosse Pflege
     * den Gesamtscore weder staucht noch aufblaeht.
     *
     * @return array<string, float>
     */
    public function normalizedScoringWeights(): array
    {
        $weights = array_map(
            static fn (int $weight): float => max(0.0, (float) $weight),
            $this->scoringWeights(),
        );

        $sum = array_sum($weights);

        if ($sum <= 0.0) {
            $defaults = array_map(
                static fn ($weight): float => (float) $weight,
                (array) config('content.topics.weights', []),
            );

            return $defaults === [] ? [] : $defaults;
        }

        return array_map(static fn (float $weight): float => $weight / $sum, $weights);
    }

    /**
     * Post-Kategorie fuer eine Ratgeber-Rubrik, sonst die Standardkategorie.
     */
    public function categoryIdFor(?string $key): ?int
    {
        $mapping = $this->category_mapping_json ?? [];

        $id = $key !== null ? ($mapping[$key] ?? null) : null;

        return $id !== null ? (int) $id : (isset($mapping['default']) ? (int) $mapping['default'] : null);
    }
}
