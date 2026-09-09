<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Bundesland oder Stadt der Geo-Liste (#12).
 *
 * Die Tabelle ist Nachschlagewerk fuer die Regionserkennung und Lieferant der
 * Slugs, mit denen regionale Ratgeber ausgespielt werden. Sie wird vom
 * GeoRegionSeeder befuellt und danach nur noch gelesen; Schreibzugriffe aus
 * der Pipeline gibt es nicht.
 */
class GeoRegion extends Model
{
    use TenantConnection;

    public const SCOPE_STATE = 'state';

    public const SCOPE_CITY = 'city';

    /** Kleinste Einwohnerzahl der amtlichen Liste. */
    public const MIN_POPULATION = 30000;

    private const CACHE_SECONDS = 3600;

    protected $fillable = [
        'scope',
        'code',
        'name',
        'slug',
        'state_code',
        'population',
        'latitude',
        'longitude',
        'source',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'population' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function scopeStates(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_STATE)->where('is_active', true);
    }

    public function scopeCities(Builder $query): Builder
    {
        return $query->where('scope', self::SCOPE_CITY)->where('is_active', true);
    }

    public function scopeInState(Builder $query, string $stateCode): Builder
    {
        return $query->where('state_code', $stateCode);
    }

    /**
     * Bundesland einer Stadt, als ISO-3166-2-Code.
     */
    public function stateCode(): ?string
    {
        return $this->scope === self::SCOPE_STATE ? (string) $this->code : $this->state_code;
    }

    /**
     * Eintrag zu einem region_code, unabhaengig vom Zuschnitt.
     */
    public static function findByCode(string $scope, string $code): ?self
    {
        return static::query()->where('scope', $scope)->where('code', $code)->first();
    }

    /**
     * Alle aktiven Eintraege eines Zuschnitts als code => name, zwischen-
     * gespeichert. Die Liste aendert sich nur beim Seeden.
     *
     * @return array<string, string>
     */
    public static function lookup(string $scope): array
    {
        return Cache::remember(
            'content:geo-regions:'.$scope.':'.(tenant()?->getTenantKey() ?? 'central'),
            self::CACHE_SECONDS,
            static fn (): array => static::query()
                ->where('scope', $scope)
                ->where('is_active', true)
                ->orderByDesc('population')
                ->pluck('name', 'code')
                ->all(),
        );
    }

    /**
     * Ist die Tabelle bereits geseedet? Ohne Geo-Liste faellt die
     * Regionserkennung auf die Bundeslaender aus der Konfiguration zurueck.
     */
    public static function isSeeded(): bool
    {
        return static::query()->where('scope', self::SCOPE_CITY)->exists();
    }
}
