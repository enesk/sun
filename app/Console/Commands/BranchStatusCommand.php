<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Themes\SunV2\BranchConfig;
use App\Themes\ThemeManager;
use Illuminate\Console\Command;

/**
 * Arbeitsliste fuer die Umstellung der Portale auf sun-v2 (#12).
 *
 * Zeigt je Portal: aktives Theme, zugeordnetes Branchenpaket, ob die Texte
 * schon da sind und wie viele Leistungskacheln das Paket hat. Damit ist
 * nachvollziehbar, welches Portal umgestellt werden darf (#15) und wo noch
 * Texte fehlen (#13).
 *
 * Ein Paket mit `_texte_fehlen` darf NICHT aktiviert werden: die fehlenden
 * Schluessel fallen sonst auf die Vorgabe zurueck, und das sind die
 * Elektriker-Texte.
 */
class BranchStatusCommand extends Command
{
    protected $signature = 'sun:branches:status {--bereit : Nur Portale zeigen, die umgestellt werden koennen}';

    protected $description = 'Zeigt je Portal Theme, Branchenpaket und ob die Texte des Pakets vollstaendig sind';

    /** Schluessel, die ein Paket braucht, damit kein Text der Vorgabe durchschlaegt. */
    private const PFLICHTTEXTE = ['hero', 'seo', 'search', 'city', 'cities_index', 'login'];

    public function handle(): int
    {
        $themes = app(ThemeManager::class);
        $zeilen = [];
        $offen = 0;

        /** @var \Illuminate\Support\Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $theme = (string) ($themes->getTenantTheme($tenant) ?: 'default');
            $branch = BranchConfig::resolve($tenant);
            $paket = $branch === null ? [] : (array) config("branches.{$branch}", []);

            $domain = (string) $tenant->getAttribute('domain');

            $status = match (true) {
                // Nicht ausgelieferte Portale zuerst (#16): die Domain zeigt
                // an der Kante auf ein anderes Angebot, dort ist nichts zu
                // betexten oder umzustellen — unabhaengig davon, wie weit das
                // Paket ist.
                ! $tenant->isDelivered() => 'nicht ausgeliefert',
                $branch === null => 'kein Paket',
                ($paket['_texte_fehlen'] ?? false) === true => 'Texte fehlen',
                $this->fehlendeTexte($paket) !== [] => 'unvollstaendig: '.implode(', ', $this->fehlendeTexte($paket)),
                default => 'bereit',
            };

            if ($theme !== 'sun-v2' && $status !== 'bereit' && $tenant->isDelivered()) {
                $offen++;
            }

            if ($this->option('bereit') && ! ($theme !== 'sun-v2' && $status === 'bereit')) {
                continue;
            }

            $zeilen[] = [
                (string) $tenant->getAttribute('id'),
                $domain,
                $theme,
                $branch ?? '—',
                $status,
                (string) count((array) ($paket['services'] ?? [])),
            ];
        }

        $this->table(['ID', 'Domain', 'Theme', 'Paket', 'Texte', 'Kacheln'], $zeilen);

        if (! $this->option('bereit')) {
            /** @var \Illuminate\Support\Collection<int, Tenant> $alle */
            $alle = Tenant::query()->get();
            $ausgeliefert = $alle->filter(fn (Tenant $tenant): bool => $tenant->isDelivered());

            $this->line(sprintf(
                '%d von %d ausgelieferten Portalen warten noch auf Texte oder ein Paket.',
                $offen,
                $ausgeliefert->count(),
            ));

            /** @var \Illuminate\Support\Collection<int, Tenant> $sortiert */
            $sortiert = Tenant::query()->orderBy('id')->get();

            foreach ($sortiert as $tenant) {
                if ($tenant->isDelivered()) {
                    continue;
                }

                $this->line(sprintf(
                    'NICHT AUSGELIEFERT %s: %s',
                    (string) $tenant->getAttribute('domain'),
                    $tenant->notDeliveredNote() ?? 'ohne Begruendung — bitte am Tenant nachtragen',
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $paket
     * @return array<int, string>
     */
    private function fehlendeTexte(array $paket): array
    {
        return array_values(array_filter(
            self::PFLICHTTEXTE,
            static fn (string $schluessel): bool => ! isset($paket[$schluessel]),
        ));
    }
}
