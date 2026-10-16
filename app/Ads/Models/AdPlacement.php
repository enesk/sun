<?php

declare(strict_types=1);

namespace App\Ads\Models;

use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Support\AdSlotCatalog;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Die Belegung eines Ankers (SUN-AD-003, Ticket #3; docs/ad-slots.md 4.2/4.3).
 *
 * Aufloesung — bewusst hier dokumentiert, weil der Resolver (#4) und die
 * Verwaltung (#8) sich darauf verlassen:
 *
 *   Pro Anker, Geraet und Portal darf es beliebig viele Belegungen geben.
 *   Ausgespielt wird genau eine: aus allen, die ausspielbar sind
 *   ({@see scopeDeliverable()}, {@see scopeForTenant()}, {@see scopeForSlot()}),
 *   die mit der **hoechsten** `priority`; bei Gleichstand die **neueste**,
 *   also die mit der groessten `id`. Siehe {@see scopeResolutionOrder()}.
 *
 *   Eine portalgenaue Belegung schlaegt eine netzweite nicht automatisch —
 *   das regelt allein `priority`. Sonst gaebe es zwei Rangordnungen, von denen
 *   eine unsichtbar ist. Die Verwaltung (#8) bietet portalgenauen Belegungen
 *   deshalb eine hoehere Vorgabe-Prioritaet an.
 *
 * `page_type` wird nicht gepflegt, sondern beim Speichern aus dem Katalog
 * gesetzt; `slot_key` und `device` werden dort gegen den Katalog geprueft.
 *
 * @property int $id
 * @property int $ad_unit_id
 * @property AdPageType $page_type
 * @property string $slot_key
 * @property AdDevice $device
 * @property AdPlacementStatus $status
 * @property int $priority
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $applies_to_all_tenants
 * @property int|null $created_by
 */
class AdPlacement extends Model
{
    use CentralConnection;

    protected $table = 'ad_placements';

    protected $fillable = [
        'ad_unit_id',
        'page_type',
        'slot_key',
        'device',
        'status',
        'priority',
        'starts_at',
        'ends_at',
        'applies_to_all_tenants',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'page_type' => AdPageType::class,
            'device' => AdDevice::class,
            'status' => AdPlacementStatus::class,
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'applies_to_all_tenants' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $placement): void {
            $slotKey = (string) $placement->slot_key;

            // Der Katalog ist Code, es gibt keinen Fremdschluessel — also
            // haelt das Model die Beziehung sauber.
            if (! AdSlotCatalog::exists($slotKey)) {
                throw ValidationException::withMessages([
                    'slot_key' => [__('Der Werbeplatz :key steht nicht im Katalog.', ['key' => $slotKey])],
                ]);
            }

            $device = $placement->device instanceof AdDevice ? $placement->device : AdDevice::ALL;

            if (! AdSlotCatalog::allowsDevice($slotKey, $device)) {
                throw ValidationException::withMessages([
                    'device' => [__('Der Werbeplatz :key wird auf diesem Geraet nicht ausgespielt.', ['key' => $slotKey])],
                ]);
            }

            // Ein archiviertes Werbemittel ist nicht mehr waehlbar (#10).
            // Geprueft wird nur die **neue** Zuordnung: eine bestehende
            // Belegung muss sich weiter pausieren lassen, auch wenn ihr
            // Werbemittel inzwischen im Archiv liegt.
            if (! $placement->exists || $placement->isDirty('ad_unit_id')) {
                $unit = AdUnit::query()->find($placement->ad_unit_id);

                if ($unit?->isArchived() === true) {
                    throw ValidationException::withMessages([
                        'ad_unit_id' => [__('Das Werbemittel :name ist archiviert und kann nicht belegt werden.', ['name' => $unit->name])],
                    ]);
                }
            }

            // Der Anker verengt die Geraetewahl (docs/ad-slots.md 3.2).
            $placement->device = $device->narrowTo(AdSlotCatalog::deviceOf($slotKey));
            $placement->page_type = AdSlotCatalog::pageTypeOf($slotKey);
        });
    }

    /** @return BelongsTo<AdUnit, $this> */
    public function adUnit(): BelongsTo
    {
        return $this->belongsTo(AdUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Portale, fuer die diese Belegung gilt — nur ausgewertet, wenn
     * `applies_to_all_tenants` false ist.
     */
    /** @return BelongsToMany<Tenant, $this> */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'ad_placement_tenant');
    }

    /**
     * Status aktiv — ohne Zeitraum und ohne Werbemittel-Pruefung.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', AdPlacementStatus::ACTIVE->value);
    }

    /**
     * Zeitraum laeuft gerade.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithinSchedule(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= Carbon::now();

        $query->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at));
    }

    /**
     * Alles, was ueberhaupt ausgespielt werden darf: aktiv, im Zeitraum und
     * mit einem nicht abgeschalteten Werbemittel.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDeliverable(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->active()
            ->withinSchedule($at)
            ->whereHas('adUnit', fn (Builder $q) => $q->where('is_active', true));
    }

    /**
     * Belegungen, die fuer dieses Portal gelten: netzweit ODER im Pivot.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int|string $tenant): void
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        $query->where(function (Builder $q) use ($tenantId): void {
            $q->where('applies_to_all_tenants', true)
                ->orWhereHas('tenants', fn (Builder $t) => $t->whereKey($tenantId));
        });
    }

    /**
     * Ein Anker, optional auf eine Geraeteklasse eingegrenzt. `all` im
     * Datensatz passt immer, der Rest nur auf sein eigenes Geraet.
     *
     * @param  Builder<self>  $query
     */
    public function scopeForSlot(Builder $query, string $slotKey, ?AdDevice $device = null): void
    {
        $query->where('slot_key', $slotKey);

        if ($device === null || $device === AdDevice::ALL) {
            return;
        }

        $query->whereIn('device', [$device->value, AdDevice::ALL->value]);
    }

    /**
     * Rangfolge der Aufloesung: hoechste Prioritaet zuerst, bei Gleichstand
     * die neueste Belegung. Jede Abfrage, die "die eine" Belegung sucht,
     * benutzt genau diesen Scope — die Reihenfolge steht nur hier.
     *
     * @param  Builder<self>  $query
     */
    public function scopeResolutionOrder(Builder $query): void
    {
        $query->orderByDesc('priority')->orderByDesc('id');
    }

    /** Ist der Zeitraum abgelaufen, egal was in `status` steht? */
    public function hasExpired(?CarbonInterface $at = null): bool
    {
        $at ??= Carbon::now();

        return $this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($at);
    }

    public function appliesToTenant(Tenant|int|string $tenant): bool
    {
        if ($this->applies_to_all_tenants) {
            return true;
        }

        $tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;

        return $this->tenants()->whereKey($tenantId)->exists();
    }
}
