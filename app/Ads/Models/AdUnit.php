<?php

declare(strict_types=1);

namespace App\Ads\Models;

use App\Ads\AdProviderRegistry;
use App\Ads\Contracts\AdProvider;
use App\Ads\Enums\AdProviderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ein Werbemittel (SUN-AD-003, Ticket #3; docs/ad-slots.md 4.2).
 *
 * Liegt central — auch waehrend einer Anfrage im Tenant-Kontext, deshalb
 * CentralConnection. Die providerspezifischen Angaben stehen in
 * `config_json` und werden beim Speichern gegen das Schema des Providers
 * geprueft ({@see AdProvider::validateConfig()}); ein Datensatz mit
 * unbrauchbarer Konfiguration kommt gar nicht erst in die Tabelle, damit der
 * Provider beim Rendern nichts mehr pruefen muss.
 *
 * `provider` ist bewusst ein **String** und kein Enum-Cast: welche Provider es
 * gibt, steht in `config('ad-slots.providers')` (Ticket #4). Mit Enum-Cast
 * waere ein neuer Provider ohne Aenderung an diesem Model nicht moeglich —
 * `AdProviderType` bleibt nur die Konstantenliste der beiden eingebauten.
 *
 * @property int $id
 * @property string $name
 * @property string $provider
 * @property array<string, mixed> $config_json
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $archived_at
 */
class AdUnit extends Model
{
    use CentralConnection;

    protected $table = 'ad_units';

    protected $fillable = [
        'name',
        'provider',
        'config_json',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $unit): void {
            // Archiviert heisst ausgespielt-nie-mehr: ohne diese Zusicherung
            // haette ein archiviertes Werbemittel mit is_active = true weiter
            // in bestehenden Belegungen gelaufen (Ticket #10).
            if ($unit->archived_at !== null) {
                $unit->is_active = false;
            }

            $provider = $unit->providerInstance();

            if ($provider === null) {
                throw ValidationException::withMessages([
                    'provider' => [__('Unbekannte Art des Werbemittels.')],
                ]);
            }

            $unit->config_json = $provider->normalizeConfig((array) $unit->config_json);

            $errors = $provider->validateConfig((array) $unit->config_json);

            if ($errors !== []) {
                throw ValidationException::withMessages(['config_json' => $errors]);
            }
        });
    }

    /**
     * Der Provider dieses Werbemittels aus der Registry; null, wenn der Wert
     * in der Spalte nicht (mehr) registriert ist. Einzige Stelle, an der ein
     * Provider-Objekt aus einem Model heraus geholt wird.
     */
    public function providerInstance(): ?AdProvider
    {
        return app(AdProviderRegistry::class)->for($this);
    }

    /** Eingebaute Art, falls es eine ist — fuer Abfragen und Badges (#10). */
    public function providerType(): ?AdProviderType
    {
        return AdProviderType::tryFrom((string) $this->provider);
    }

    public function providerLabel(): string
    {
        return $this->providerInstance()?->label() ?? (string) $this->provider;
    }

    public function placements(): HasMany
    {
        return $this->hasMany(AdPlacement::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeOfProvider(Builder $query, AdProviderType|string $provider): void
    {
        $query->where('provider', $provider instanceof AdProviderType ? $provider->value : $provider);
    }

    /**
     * Was in einer neuen Belegung waehlbar ist (#8/#10): alles, was nicht
     * archiviert ist — auch gerade abgeschaltete Werbemittel, denn `is_active`
     * ist nur der Not-Aus.
     *
     * @param  Builder<self>  $query
     */
    public function scopeSelectable(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** @param  Builder<self>  $query */
    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Aus dem Verkehr ziehen statt loeschen (Ticket #10). Loeschen wuerde die
     * Belegungen per ON DELETE CASCADE mitnehmen; hier bleiben sie stehen,
     * spielen aber nichts mehr aus, weil `is_active` mitfaellt.
     */
    public function archive(): bool
    {
        if ($this->isArchived()) {
            return false;
        }

        $this->archived_at = now();

        return $this->save();
    }

    /**
     * Zurueck in die Auswahl. Bewusst **ohne** `is_active` wieder anzuschalten:
     * wer ein Werbemittel reaktiviert, entscheidet selbst, ob es laufen soll.
     */
    public function unarchive(): bool
    {
        if (! $this->isArchived()) {
            return false;
        }

        $this->archived_at = null;

        return $this->save();
    }

    /**
     * Darf dieses Werbemittel geloescht werden? Nur, solange keine Belegung
     * daran haengt — sonst raeumt die Datenbank sie mit weg.
     */
    public function isDeletable(): bool
    {
        return $this->placements()->doesntExist();
    }

    /** Formatschluessel aus `ad-slots.formats`; Vorgabe `responsive`. */
    public function format(): string
    {
        return (string) ($this->config_json['format'] ?? 'responsive');
    }

    /** `data-ad-slot`; nur bei AdSense gesetzt. */
    public function adsenseSlotId(): ?string
    {
        if ((string) $this->provider !== AdProviderType::ADSENSE->value) {
            return null;
        }

        $slotId = (string) ($this->config_json['slot_id'] ?? '');

        return $slotId === '' ? null : $slotId;
    }

    /** `data-ad-layout-key`; nur bei In-Feed-Bloecken gesetzt. */
    public function adsenseLayoutKey(): ?string
    {
        if ((string) $this->provider !== AdProviderType::ADSENSE->value) {
            return null;
        }

        $key = (string) ($this->config_json['layout_key'] ?? '');

        return $key === '' ? null : $key;
    }

    /** Rohes Markup; nur bei provider = html und nie ungesandboxt ausgeben (#4). */
    public function html(): ?string
    {
        if ((string) $this->provider !== AdProviderType::HTML->value) {
            return null;
        }

        $code = (string) ($this->config_json['code'] ?? '');

        return $code === '' ? null : $code;
    }

    /**
     * Braucht die Ausspielung eine Einwilligung? Entscheidet der Provider —
     * AdSense ja, eigenes HTML im Sandbox-Rahmen nicht (SUN-AD-009).
     */
    public function requiresConsent(): bool
    {
        return $this->providerInstance()?->requiresConsent($this) ?? true;
    }
}
