<?php

declare(strict_types=1);

namespace App\Ads\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Werbe-Einstellungen eines Portals (SUN-AD-003, Ticket #3). Genau eine Zeile
 * je Tenant, central; gepflegt wird sie in Ticket #10.
 *
 * `ads_enabled` ist der Freigabeschalter: steht er auf false — oder fehlt die
 * Zeile ganz —, spielt der Resolver (#4) in diesem Portal nichts aus, egal
 * welche Belegungen netzweit aktiv sind. Vorgabe beim Anlegen ist deshalb
 * false (AdSettingSeeder).
 *
 * @property int $id
 * @property int $tenant_id
 * @property bool $ads_enabled
 * @property string|null $adsense_publisher_id
 * @property bool $adsense_auto_ads
 * @property string|null $ads_txt_extra
 * @property int|null $updated_by
 */
class AdSetting extends Model
{
    use CentralConnection;

    protected $table = 'ad_settings';

    protected $fillable = [
        'tenant_id',
        'ads_enabled',
        'adsense_publisher_id',
        'adsense_auto_ads',
        'ads_txt_extra',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'ads_enabled' => 'boolean',
            'adsense_auto_ads' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('ads_enabled', true);
    }

    /** @param  Builder<self>  $query */
    public function scopeForTenant(Builder $query, Tenant|int|string $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    /**
     * Publisher-ID dieses Portals oder die Netz-Vorgabe aus
     * config('ad-slots.adsense.publisher_id'). Leer = kein AdSense.
     */
    public function adsensePublisherId(): ?string
    {
        $own = trim((string) $this->adsense_publisher_id);

        if ($own !== '') {
            return $own;
        }

        $fallback = trim((string) config('ad-slots.adsense.publisher_id'));

        return $fallback === '' ? null : $fallback;
    }

    public function hasOwnPublisherId(): bool
    {
        return trim((string) $this->adsense_publisher_id) !== '';
    }

    /**
     * Zusatzzeilen fuer ads.txt, ohne Leerzeilen und Kommentare (#10).
     *
     * @return list<string>
     */
    public function adsTxtLines(): array
    {
        $lines = preg_split('/\R/', (string) $this->ads_txt_extra) ?: [];

        return array_values(array_filter(
            array_map(trim(...), $lines),
            fn (string $line) => $line !== '' && ! str_starts_with($line, '#'),
        ));
    }

    /**
     * Vorgaben einer neuen Zeile (#3, auch vom Seeder benutzt): aus, ohne
     * eigene Publisher-ID.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'ads_enabled' => false,
            'adsense_publisher_id' => null,
            'adsense_auto_ads' => false,
            'ads_txt_extra' => null,
            'updated_by' => null,
        ];
    }
}
