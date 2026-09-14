<?php

declare(strict_types=1);

namespace App\Content\Models\Central;

use App\Content\Enums\SourceFrequency;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;
use Throwable;

/**
 * Konfiguration eines Quell-Connectors (#45/#55),
 * design/content-dashboard.md, §7a.
 *
 * Zentral je Connector, ohne Portalbezug. Ein Datensatz entsteht erst beim
 * ersten Speichern: die Tabelle beschreibt Abweichungen, nicht den Bestand.
 * Was hier fehlt, gilt als Vorgabe.
 *
 * @property string $source_key
 * @property bool $is_enabled
 * @property string|null $frequency_override
 * @property int $weight
 * @property string|null $disabled_reason
 * @property int|null $updated_by_user_id
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class SourceSetting extends Model
{
    use CentralConnection;

    /** Gewicht ohne Abweichung, in Prozent. */
    public const DEFAULT_WEIGHT = 100;

    public const MAX_WEIGHT = 200;

    protected $table = 'content_source_settings';

    protected $fillable = [
        'source_key',
        'is_enabled',
        'frequency_override',
        'weight',
        'disabled_reason',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'weight' => 'integer',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function forKey(string $sourceKey): ?self
    {
        return static::query()->where('source_key', $sourceKey)->first();
    }

    /**
     * Alle Datensaetze, nach Connector-Schluessel abgelegt.
     *
     * Faengt Datenbankfehler ab: die Registry fragt die Werte auch waehrend
     * Migrationen und im Konsolenkontext ab, in dem die Tabelle noch fehlen
     * kann. Ohne Datensatz gilt ohnehin die Vorgabe.
     *
     * @return array<string, self>
     */
    public static function allKeyed(): array
    {
        try {
            /** @var Collection<int, self> $rows */
            $rows = static::query()->get();
        } catch (Throwable) {
            return [];
        }

        return $rows->keyBy('source_key')->all();
    }

    public function frequencyOverride(): ?SourceFrequency
    {
        return $this->frequency_override !== null
            ? SourceFrequency::tryFrom($this->frequency_override)
            : null;
    }

    /**
     * Anteil, mit dem die Signale dieser Quelle ins Scoring eingehen.
     */
    public function weightFactor(): float
    {
        return max(0, $this->weight) / 100;
    }

    /**
     * Weicht der Datensatz ueberhaupt von der Vorgabe ab? Ein Datensatz, der
     * nur Vorgabewerte enthaelt, ist keine Abweichung — die Karte traegt dann
     * auch keine Marke und das Formular keinen Ruecksetz-Verweis.
     */
    public function deviates(): bool
    {
        return ! $this->is_enabled
            || $this->frequency_override !== null
            || $this->weight !== self::DEFAULT_WEIGHT;
    }
}
