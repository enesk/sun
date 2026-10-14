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

        foreach (Tenant::query()->orderBy('id')->get() as $tenant) {
            $theme = (string) ($themes->getTenantTheme($tenant) ?: 'default');
            $branch = BranchConfig::resolve($tenant);
            $paket = $branch === null ? [] : (array) config("branches.{$branch}", []);

            $status = match (true) {
                $branch === null => 'kein Paket',
                ($paket['_texte_fehlen'] ?? false) === true => 'Texte fehlen',
                $this->fehlendeTexte($paket) !== [] => 'unvollstaendig: '.implode(', ', $this->fehlendeTexte($paket)),
                default => 'bereit',
            };

            if ($theme !== 'sun-v2' && $status !== 'bereit') {
                $offen++;
            }

            if ($this->option('bereit') && ! ($theme !== 'sun-v2' && $status === 'bereit')) {
                continue;
            }

            $zeilen[] = [
                (string) $tenant->getAttribute('id'),
                (string) $tenant->getAttribute('domain'),
                $theme,
                $branch ?? '—',
                $status,
                (string) count((array) ($paket['services'] ?? [])),
            ];
        }

        $this->table(['ID', 'Domain', 'Theme', 'Paket', 'Texte', 'Kacheln'], $zeilen);

        if (! $this->option('bereit')) {
            $this->line(sprintf('%d Portal(e) warten noch auf Texte oder ein Paket.', $offen));
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
