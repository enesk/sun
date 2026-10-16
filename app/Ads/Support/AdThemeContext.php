<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Models\Tenant;
use App\Themes\ThemeManager;

/**
 * Welches Theme gilt fuer die Werbe-Auswahl? (Ticket #14)
 *
 * Der Anker-Katalog ist theme-abhaengig, die Frage nach dem aktiven Theme aber
 * nicht Sache des Katalogs: sie haengt am Tenant. Diese Klasse ist die einzige
 * Stelle, an der das Werbesystem danach fragt — Frontend (Komponente,
 * Resolver), Portal-Backoffice und Filament (#7/#8) benutzen dieselbe Antwort.
 *
 * Bewusst statisch wie {@see AdSlotCatalog}: es gibt keinen Zustand, nur den
 * Umweg ueber den ThemeManager, und ein fehlendes Theme faellt immer auf
 * {@see ThemeManager::DEFAULT_THEME} zurueck — wie ResolveTheme es auch tut.
 */
final class AdThemeContext
{
    /** Theme des gerade bedienten Portals; ohne Tenant das Vorgabe-Theme. */
    public static function current(): string
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        return self::forTenant($tenant instanceof Tenant ? $tenant : null);
    }

    /** Theme eines bestimmten Portals — fuer Backoffice-Listen ueber mehrere Tenants. */
    public static function forTenant(?Tenant $tenant): string
    {
        if ($tenant === null) {
            return ThemeManager::DEFAULT_THEME;
        }

        return app(ThemeManager::class)->getTenantTheme($tenant);
    }

    /**
     * Anker des aktiven Themes, als Auswahlliste nach Seitentyp gruppiert.
     *
     * Mit Routenname bleiben nur die Anker dieser Route uebrig (Ticket #27);
     * ohne ihn tragen routengebundene Anker den Zusatz "nur auf …".
     *
     * @return array<string, array<string, string>>
     */
    public static function slotOptions(?Tenant $tenant = null, ?string $route = null): array
    {
        return AdSlotCatalog::options(
            $tenant === null ? self::current() : self::forTenant($tenant),
            $route,
        );
    }

    /**
     * Kennt der Katalog fuer dieses Theme ueberhaupt Anker? Ist die Antwort
     * nein, hat das Portal bis zur Theme-Umstellung keine Werbeplaetze — das
     * muss die Oberflaeche sagen, statt eine leere Liste zu zeigen.
     */
    public static function hasSlots(?Tenant $tenant = null): bool
    {
        $theme = $tenant === null ? self::current() : self::forTenant($tenant);

        return AdSlotCatalog::forTheme($theme) !== [];
    }
}
