<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ads\Dto\LegacyTakeoverPlan;
use App\Ads\LegacyAdTakeover;
use Illuminate\Console\Command;

/**
 * Uebernimmt den Altbestand der Werbeplaetze in das neue System (Ticket #15).
 *
 * Der Trockenlauf ist der eigentliche Zweck des Kommandos: #11 steigt Portal
 * fuer Portal um und muss vorher sehen, welche Belegung aus welchem Alt-Slot
 * entsteht und was bewusst liegen bleibt. Die Abbildung selbst steht in
 * App\Ads\LegacyAdTakeover::SLOT_MAP, nicht hier.
 *
 *   php artisan ads:legacy-takeover --dry-run
 *   php artisan ads:legacy-takeover --tenants=sanitaerfinden.com
 *   php artisan ads:legacy-takeover --force
 */
class AdsLegacyTakeoverCommand extends Command
{
    protected $signature = 'ads:legacy-takeover
        {--dry-run : Nur den Plan zeigen, nichts schreiben}
        {--tenants=* : Portal als UUID, ID oder Domain; Vorgabe sind alle Portale}
        {--keep-disabled : ads_enabled nicht mitsetzen (Pilotbetrieb, #11)}
        {--force : Ohne Rueckfrage schreiben}';

    protected $description = 'Bildet die alten Ad-Slots (Tenant-DB) auf ad_units/ad_placements ab und zieht Publisher-ID, Auto Ads und ads.txt nach';

    public function handle(LegacyAdTakeover $takeover): int
    {
        /** @var list<string> $tenants */
        $tenants = array_values(array_filter((array) $this->option('tenants')));
        $enableAds = ! $this->option('keep-disabled');

        $plan = $takeover->plan($tenants, $enableAds);

        $this->printPlan($plan);

        if ($plan->isEmpty()) {
            $this->warn('Kein uebernehmbarer Bestand gefunden.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('Trockenlauf — nichts geschrieben.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Plan jetzt in die Central-DB schreiben?', false)) {
            $this->info('Abgebrochen.');

            return self::SUCCESS;
        }

        $result = $takeover->apply($plan);

        $this->newLine();
        $this->info('Geschrieben:');
        $this->table(
            ['Werbemittel neu', 'Werbemittel erkannt', 'Belegungen neu', 'Belegungen erkannt', 'Portal-Zuordnungen', 'Einstellungen'],
            [[
                $result['counts']['units_created'],
                $result['counts']['units_reused'],
                $result['counts']['placements_created'],
                $result['counts']['placements_reused'],
                $result['counts']['tenants_attached'],
                $result['counts']['settings_written'],
            ]],
        );

        foreach ($result['messages'] as $message) {
            $this->warn("• {$message}");
        }

        if (! $enableAds) {
            $this->warn('ads_enabled wurde nicht gesetzt — bis zur Freigabe je Portal (#10/#11) erscheint nichts.');
        }

        return self::SUCCESS;
    }

    private function printPlan(LegacyTakeoverPlan $plan): void
    {
        $this->info("Alt-Slots gelesen: {$plan->legacySlotsSeen}, davon uebernommen: {$plan->legacySlotsTaken}.");
        $this->newLine();

        if ($plan->units !== []) {
            $this->line('<options=bold>Werbemittel und Belegungen</>');
            $this->table(
                ['Alt-Position', 'Anker', 'Geraet', 'Format', 'Konto', 'Block', 'Portale'],
                array_map(fn ($unit) => [
                    $unit->legacyPosition,
                    $unit->slotKey,
                    $unit->device->value,
                    $unit->format,
                    $unit->publisherId,
                    $unit->slotId,
                    count($unit->tenantIds),
                ], $plan->units),
            );
        }

        if ($plan->settings !== []) {
            $this->line('<options=bold>Einstellungen je Portal</>');
            $this->table(
                ['Portal', 'Publisher-ID', 'Auto Ads', 'ads.txt', 'ads_enabled'],
                array_map(fn ($setting) => [
                    $setting->tenantName,
                    $setting->publisherId ?? '—',
                    $setting->autoAds ? 'ja' : '—',
                    $setting->adsTxt === null ? '—' : substr_count(trim($setting->adsTxt), "\n") + 1 .' Zeilen',
                    $setting->enableAds ? 'wird gesetzt' : 'bleibt',
                ], $plan->settings),
            );
        }

        if ($plan->notes === []) {
            return;
        }

        $this->line('<options=bold>Nicht uebernommen</>');
        $this->table(
            ['Portal', 'Position', 'Grund'],
            array_map(fn (array $note) => [$note['tenant'], $note['position'], $note['reason']], $plan->notes),
        );
    }
}
