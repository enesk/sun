<?php

declare(strict_types=1);

namespace App\Guide\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Einstellungen des Ratgebersystems je Portal. Genau eine Zeile je Tenant-DB.
 *
 * Leere Werte bei Budget und Zeitfenster bedeuten: Vorgabe aus config/guide.php.
 */
class TenantGuideSetting extends Model
{
    use TenantConnection;

    public const DEFAULT_THRESHOLD = 80;

    public const YMYL_THRESHOLD = 90;

    // Ab dieser wirksamen Schwelle gibt es keine Auto-Freigabe (#38 G4).
    public const REVIEW_ALL_THRESHOLD = 100;

    protected $table = 'tenant_guide_settings';

    protected $fillable = [
        'is_active',
        'is_ymyl',
        'auto_publish_threshold',
        'daily_budget_usd',
        'run_window_start',
        'run_window_end',
        'author_name',
        'author_bio',
        'branch',
        'branch_plural',
        'category_cta_mapping_json',
        'source_whitelist_json',
        'source_blacklist_json',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_ymyl' => 'boolean',
            'auto_publish_threshold' => 'integer',
            'daily_budget_usd' => 'decimal:2',
            'category_cta_mapping_json' => 'array',
            'source_whitelist_json' => 'array',
            'source_blacklist_json' => 'array',
        ];
    }

    /**
     * Einstellungen des aktuellen Tenants; legt beim ersten Zugriff die
     * Defaults an (inaktiv, Schwelle 80).
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'auto_publish_threshold' => self::DEFAULT_THRESHOLD,
        ]);
    }

    /**
     * Wirksame Freigabeschwelle (#38 G3): bei YMYL nie unter 90, sonst der
     * eingetragene Wert. Gespeichert bleibt immer der eingetragene Wert.
     * Qualitaetsgate, Panel-Hinweis, guide:rollout und guide:golive:check
     * lesen nur diese Methode bzw. effectiveThresholdFor().
     */
    public function effectiveThreshold(): int
    {
        return self::effectiveThresholdFor($this->auto_publish_threshold, (bool) $this->is_ymyl);
    }

    public static function effectiveThresholdFor(?int $entered, bool $isYmyl): int
    {
        $threshold = $entered ?? self::DEFAULT_THRESHOLD;

        return $isYmyl ? max($threshold, self::YMYL_THRESHOLD) : $threshold;
    }

    /**
     * Schwelle 100 heisst "jede Fassung geht in die Pruefung" (#38 G4),
     * auch bei einer Bewertung von exakt 100.
     */
    public static function allowsAutoPublish(int $effectiveThreshold): bool
    {
        return $effectiveThreshold < self::REVIEW_ALL_THRESHOLD;
    }

    public function dailyBudgetUsd(): float
    {
        return $this->daily_budget_usd !== null
            ? (float) $this->daily_budget_usd
            : (float) config('guide.budget.daily_usd_per_tenant');
    }
}
