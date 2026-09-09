<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Zustand eines externen Providers: Circuit Breaker, Tageszaehler und
 * letzter Fehler. Speist den Quellen-Monitor (#20) und die Alarme (#22).
 *
 * @property \Illuminate\Support\Carbon|null $circuit_open_until
 * @property \Illuminate\Support\Carbon|null $last_success_at
 * @property \Illuminate\Support\Carbon|null $last_failure_at
 * @property \Illuminate\Support\Carbon|null $counters_date
 * @property array<string, mixed>|null $meta_json
 * @property string $provider
 * @property string $status
 * @property int $consecutive_failures
 * @property int $requests_today
 * @property float $cost_today_usd
 * @property string|null $last_error
 */
class ProviderState extends Model
{
    use CentralConnection;

    public const STATUS_OK = 'ok';

    public const STATUS_DEGRADED = 'degraded';

    public const STATUS_OPEN = 'open';

    /**
     * Budget erschoepft (#6). Anders als STATUS_OPEN kein Fehlerzustand des
     * Providers: der BudgetGuard setzt ihn und gibt ihn um 00:00 wieder frei.
     */
    public const STATUS_PAUSED = 'paused';

    /**
     * Der Zugang selbst ist tot (#104): Guthaben aufgebraucht oder
     * Schluessel abgelehnt. Anders als STATUS_OPEN hilft hier kein
     * Wiederholungsversuch und anders als STATUS_PAUSED kein Tageswechsel —
     * es muss jemand aufladen oder einen Schluessel erneuern.
     */
    public const STATUS_FAILED = 'failed';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'provider',
        'status',
        'consecutive_failures',
        'last_success_at',
        'last_failure_at',
        'circuit_open_until',
        'requests_today',
        'cost_today_usd',
        'counters_date',
        'last_error',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'consecutive_failures' => 'integer',
            'requests_today' => 'integer',
            'cost_today_usd' => 'float',
            'last_success_at' => 'datetime',
            'last_failure_at' => 'datetime',
            'circuit_open_until' => 'datetime',
            'counters_date' => 'date',
            'meta_json' => 'array',
        ];
    }

    public function isAvailable(): bool
    {
        if ($this->status === self::STATUS_DISABLED) {
            return false;
        }

        if ($this->status === self::STATUS_PAUSED) {
            return false;
        }

        if ($this->status === self::STATUS_FAILED) {
            return false;
        }

        return $this->circuit_open_until === null || $this->circuit_open_until->isPast();
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Zustandszeile eines Providers, ohne sie doppelt anzulegen.
     */
    public static function forProvider(string $provider): self
    {
        return static::query()->firstOrCreate(
            ['provider' => $provider],
            ['status' => self::STATUS_OK],
        );
    }

    public function scopeHealthy(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OK);
    }

    public function scopeImpaired(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_DEGRADED, self::STATUS_OPEN, self::STATUS_PAUSED, self::STATUS_FAILED, self::STATUS_DISABLED]);
    }
}
