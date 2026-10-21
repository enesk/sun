<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;

/**
 * Die Portale der GOLD-Belegung (SUN-ADS-GOLD-001).
 *
 * Eine Stelle fuer die Frage, WELCHE Portale die GOLD-Anzeigenbloecke
 * bekommen — AdSettingsGoldSeeder (#2) und AdPlacementsGoldCompanySeeder (#4)
 * muessen dieselbe Menge treffen, sonst stehen Belegungen in Portalen ohne
 * Publisher-ID oder umgekehrt.
 *
 * Zugeordnet wird ueber die Domain des Tenants (Spalte `tenants.domain`, es
 * gibt keine domains()-Relation): die Domain selbst oder eine Subdomain
 * davon. `firmenfreund.net` zaehlt damit bewusst **nicht** mit — andere
 * Domain, nicht in der Vorgabe.
 */
final class AdsGoldTargets
{
    /** Publisher-ID des Kontos, zu dem die GOLD-Bloecke gehoeren. */
    public const PUBLISHER_ID = 'ca-pub-2563111817853634';

    /** Die Google-Zeile dieses Kontos in der ads.txt. */
    public const ADS_TXT_LINE = 'google.com, pub-2563111817853634, DIRECT, f08c47fec0942fa0';

    /** @var list<string> */
    public const DOMAINS = [
        'findegutachter.de',
        'firmenfreund.de',
        'bodenlegerfinden.com',
        'sanitaerfinden.com',
    ];

    /**
     * Die betroffenen Portale, nach Domain sortiert.
     *
     * @return array<int, Tenant>
     */
    public static function tenants(): array
    {
        /** @var array<int, Tenant> $tenants */
        $tenants = Tenant::all()
            ->filter(fn (Tenant $tenant) => self::matches((string) $tenant->domain))
            ->sortBy(fn (Tenant $tenant) => (string) $tenant->domain)
            ->values()
            ->all();

        return $tenants;
    }

    /** @return list<int|string> */
    public static function tenantIds(): array
    {
        return array_map(fn (Tenant $tenant) => $tenant->getKey(), self::tenants());
    }

    private static function matches(string $domain): bool
    {
        $domain = strtolower(trim($domain));

        foreach (self::DOMAINS as $base) {
            if ($domain === $base || str_ends_with($domain, '.'.$base)) {
                return true;
            }
        }

        return false;
    }
}
