<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Support\AdSlotCatalog;
use App\Ads\Support\AdThemeContext;
use App\Ads\Support\ThemeAnchorIndex;
use App\Models\Portal\AdSlot;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Macht stumme Werbeplaetze sichtbar (Ticket #14).
 *
 * Die Positionen des alten Systems gelten global, ihre Anker stehen je Theme.
 * Ein Slot auf einer Position, die das Theme des Portals nicht rendert, ist
 * aktiv, gepflegt — und wird nie ausgeliefert. Dieses Kommando zaehlt das je
 * Portal aus, damit die Bereinigung nicht aus dem Gefuehl laeuft.
 *
 * Nur Lesezugriff: es aendert nichts, auch nicht mit `--stumm`.
 */
class AdsAnchorReportCommand extends Command
{
    protected $signature = 'ads:anker-bericht
        {--stumm : Nur Portale mit stummen Slots zeigen}
        {--theme= : Nur Portale mit diesem Theme}';

    protected $description = 'Zeigt je Portal, welche aktiven Ad-Slots im aktiven Theme keinen Anker haben';

    public function handle(): int
    {
        $themeFilter = $this->option('theme');
        $onlySilent = (bool) $this->option('stumm');

        $rows = [];
        $silentTotal = 0;
        $activeTotal = 0;
        $themeCounts = [];

        foreach (Tenant::orderBy('id')->get() as $tenant) {
            $theme = AdThemeContext::forTenant($tenant);
            $themeCounts[$theme] = ($themeCounts[$theme] ?? 0) + 1;

            if (is_string($themeFilter) && $themeFilter !== '' && $theme !== $themeFilter) {
                continue;
            }

            $anchored = ThemeAnchorIndex::positions($theme);
            $slots = $this->activeSlotPositions($tenant);

            $domain = (string) $tenant->getAttribute('domain');

            if ($slots === null) {
                $rows[] = [$tenant->getKey(), $domain, $theme, '—', '—', 'Tenant-DB nicht lesbar'];

                continue;
            }

            $silent = array_diff_key($slots, array_flip($anchored));
            $active = array_sum($slots);
            $silentCount = array_sum($silent);

            $activeTotal += $active;
            $silentTotal += $silentCount;

            if ($onlySilent && $silentCount === 0) {
                continue;
            }

            $rows[] = [
                $tenant->getKey(),
                $domain,
                $theme,
                (string) $active,
                (string) $silentCount,
                $silent === [] ? '' : implode(', ', array_keys($silent)),
            ];
        }

        $this->table(
            ['ID', 'Domain', 'Theme', 'aktive Slots', 'davon stumm', 'Positionen ohne Anker'],
            $rows,
        );

        $this->newLine();
        $this->line("Aktive Slots gesamt: {$activeTotal}, davon stumm: {$silentTotal}");

        foreach ($themeCounts as $theme => $count) {
            $catalogSlots = count(AdSlotCatalog::forTheme((string) $theme));
            $this->line("Theme {$theme}: {$count} Portale, neuer Katalog kennt dafuer {$catalogSlots} Anker");
        }

        return self::SUCCESS;
    }

    /**
     * Aktive Slots des Portals, gezaehlt je Position. null, wenn die
     * Tenant-DB nicht erreichbar ist — ein kaputtes Portal soll den Bericht
     * ueber alle anderen nicht abbrechen.
     *
     * @return array<string, int>|null
     */
    private function activeSlotPositions(Tenant $tenant): ?array
    {
        try {
            return $tenant->run(fn () => AdSlot::query()
                ->where('is_active', true)
                ->get(['position'])
                ->countBy('position')
                ->all());
        } catch (\Throwable $e) {
            $this->warn("Portal {$tenant->getAttribute('domain')}: {$e->getMessage()}");

            return null;
        }
    }
}
