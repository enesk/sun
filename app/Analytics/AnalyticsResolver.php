<?php

namespace App\Analytics;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;

/**
 * Die einzige Stelle, an der entschieden wird, mit welcher Kennung ein Portal
 * messt (#14).
 *
 * Rangfolge: Kennung des Tenants -> globale Einstellung -> aus.
 *
 * Die Tenant-Kennung wird bewusst direkt am Modell gelesen und nicht durch den
 * ConfigService geschleust: dessen ueberschreibbare Werte liegen ueber
 * cache()->many() in Schluesseln ohne Tenant-Anteil. Eine Tenant-Kennung in
 * diesem Topf wuerde bedeuten, dass Portal B in die Property von Portal A
 * meldet. Darum hier kein Cache und kein gemeinsamer Schluessel.
 */
class AnalyticsResolver
{
    public function resolve(?Tenant $tenant = null): AnalyticsSettings
    {
        $tenant ??= self::currentTenant();

        return new AnalyticsSettings(
            gaId: self::firstKennung(
                $tenant?->getAttribute(TenantConfigConstants::GOOGLE_ANALYTICS_ID),
                config('app.google_tracking_id'),
            ),
            gtmId: self::firstKennung(
                $tenant?->getAttribute(TenantConfigConstants::GOOGLE_TAG_MANAGER_ID),
            ),
        );
    }

    private static function firstKennung(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) (is_scalar($value) ? $value : ''));

            if ($value !== '' && ! self::isPlatzhalter($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Platzhalter wie "G-XXXXX" oder "GTM-XXXXXX" sind keine Kennung. Sie
     * stehen in .env.example und sind von dort auf die Produktion gewandert;
     * ausgeliefert laden sie 170 KB von Google und melden ins Nichts. Ein
     * Platzhalter gilt deshalb als nicht gesetzt und die Rangfolge laeuft
     * weiter bis "aus".
     */
    private static function isPlatzhalter(string $value): bool
    {
        return (bool) preg_match('/^(G|UA|AW|DC|GT|GTM)-X+$/i', $value);
    }

    private static function currentTenant(): ?Tenant
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
