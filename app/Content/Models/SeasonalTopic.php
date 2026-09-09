<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Saisonkalender-Eintrag (#13): welches Thema in welchem Zeitfenster
 * Vorlauf braucht.
 */
class SeasonalTopic extends Model
{
    use TenantConnection;

    protected $fillable = [
        'title',
        'primary_keyword',
        'keywords_json',
        'start_month',
        'end_month',
        'lead_time_days',
        'region_scope',
        'region_code',
        'weight',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'keywords_json' => 'array',
            'start_month' => 'integer',
            'end_month' => 'integer',
            'lead_time_days' => 'integer',
            'weight' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Eintraege, deren Fenster den uebergebenen Monat enthaelt. Fenster ueber
     * den Jahreswechsel (z. B. 11 bis 2) werden mit beruecksichtigt.
     */
    public function scopeForMonth(Builder $query, int $month): Builder
    {
        return $query->where(fn (Builder $q) => $this->applyMonthFilter($q, $month));
    }

    /**
     * Themen, die jetzt vorproduziert werden muessen, damit sie zum Saisonstart
     * online sind. Beruecksichtigt den laufenden und die zwei folgenden Monate.
     */
    public function scopeDueWithLeadTime(Builder $query, ?\DateTimeInterface $now = null): Builder
    {
        $now = $now ? Carbon::instance($now) : Carbon::now();

        return $query->where('is_active', true)->where(function (Builder $q) use ($now): void {
            for ($offset = 0; $offset <= 2; $offset++) {
                $month = (int) $now->copy()->addMonths($offset)->format('n');
                $q->orWhere(fn (Builder $inner) => $this->applyMonthFilter($inner, $month));
            }
        });
    }

    /**
     * Fenster ueber den Jahreswechsel (z. B. 11 bis 2) werden mit abgedeckt.
     */
    private function applyMonthFilter(Builder $query, int $month): Builder
    {
        return $query->where(function (Builder $inner) use ($month): void {
            $inner->whereColumn('start_month', '<=', 'end_month')
                ->where('start_month', '<=', $month)
                ->where('end_month', '>=', $month);
        })->orWhere(function (Builder $inner) use ($month): void {
            $inner->whereColumn('start_month', '>', 'end_month')
                ->where(function (Builder $wrap) use ($month): void {
                    $wrap->where('start_month', '<=', $month)
                        ->orWhere('end_month', '>=', $month);
                });
        });
    }
}
