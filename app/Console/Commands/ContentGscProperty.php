<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Models\TenantContentSetting;
use App\Content\Services\SearchConsoleProperty;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Search-Console-Property je Portal pflegen (#107).
 *
 * Das Content-Panel kennt das Feld seit #116, aber nur je Portal und nur mit
 * angemeldetem Administrator. Fuer zweiundzwanzig Portale ist das der
 * falsche Weg: die Property ist in aller Regel schlicht die Domain des
 * Portals, und genau diese Ableitung soll ein Befehl in einem Durchgang
 * schreiben koennen — auch nach einem Domainwechsel, wo der Seeder nicht mehr
 * greift, weil er vorhandene Zeilen ueberspringt.
 *
 *   php artisan content:gsc:property                      Zustand aller Portale
 *   php artisan content:gsc:property --fill               leere Felder aus der Portaldomain fuellen
 *   php artisan content:gsc:property --fill --force       auch abweichende Werte auf die Domain ziehen
 *   php artisan content:gsc:property --tenant=43 --set=sc-domain:beispiel.de
 *   php artisan content:gsc:property --fill --dry-run     nur zeigen, was sich aendern wuerde
 *   php artisan content:gsc:property --check              Zugriffstest bei Google, Ergebnis ins Panel
 *
 * Geschrieben wird nur, was die Formpruefung von SearchConsoleProperty
 * besteht: eine Testdomain wie `sanitaer.test` laesst sich in der Search
 * Console nie verifizieren und stellt den Go-Live-Check nur faelschlich auf
 * Gruen. Aendert sich der Wert, faellt der gespeicherte Zugriffsstand weg —
 * er gehoert zur alten Property.
 */
class ContentGscProperty extends Command
{
    protected $signature = 'content:gsc:property
        {--tenant= : Portal (ID, UUID oder Domain), sonst alle}
        {--set= : Property fuer die gewaehlten Portale setzen, leerer Wert loescht sie}
        {--fill : Property aus der Domain des Portals ableiten (sc-domain:<domain>)}
        {--force : Bei --fill auch vorhandene, abweichende Werte ueberschreiben}
        {--check : Zugriff bei Google pruefen und den Befund festhalten}
        {--dry-run : Nichts schreiben, nur zeigen}';

    protected $description = 'Pflegt tenant_content_settings.gsc_property je Portal und zeigt den Zugriffsstand';

    /** @var array<int, array<int, string>> */
    private array $rows = [];

    public function handle(SearchConsoleProperty $service): int
    {
        $tenants = $this->tenants();

        if ($tenants === []) {
            $this->error('Kein Portal gefunden.');

            return self::FAILURE;
        }

        $set = $this->option('set');
        $fill = (bool) $this->option('fill');

        if ($set !== null && $fill) {
            $this->error('--set und --fill schliessen einander aus.');

            return self::FAILURE;
        }

        if ($set !== null && $this->option('tenant') === null) {
            $this->error('--set braucht --tenant: eine Property gilt fuer genau ein Portal.');

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($tenants as $tenant) {
            $failed += $this->handleTenant($service, $tenant, $set, $fill) ? 0 : 1;
        }

        $this->table(['ID', 'Portal', 'Domain', 'Property', 'Befund'], $this->rows);

        if ($this->option('dry-run')) {
            $this->warn('Probelauf: nichts geschrieben.');
        }

        if ($failed > 0) {
            $this->error(sprintf('%d Portal(e) ohne brauchbare Property.', $failed));

            return self::FAILURE;
        }

        $this->info(sprintf('%d Portal(e) geprueft, alle mit gueltiger Property.', count($tenants)));

        return self::SUCCESS;
    }

    /**
     * Ein Portal: erst schreiben, was verlangt ist, dann den Zustand melden.
     * Rueckgabe false heisst: dieses Portal hat keine brauchbare Property.
     */
    private function handleTenant(SearchConsoleProperty $service, Tenant $tenant, ?string $set, bool $fill): bool
    {
        $domain = trim((string) $tenant->domain);
        $current = $this->propertyOf($tenant);
        $wanted = $current;
        $note = null;

        if ($set !== null) {
            $wanted = trim($set) === '' ? null : trim($set);
        }

        if ($fill) {
            $derived = $this->derive($domain);

            if ($derived === null) {
                $note = 'Portal ohne Domain — keine Property ableitbar';
            } elseif ($current === null || $current === '') {
                $wanted = $derived;
            } elseif ($current !== $derived && $this->option('force')) {
                $wanted = $derived;
            } elseif ($current !== $derived) {
                $note = "weicht von {$derived} ab — mit --force ueberschreiben";
            }
        }

        if ($wanted !== null && ($problem = $service->validate($wanted)) !== null) {
            $this->row($tenant, $domain, $wanted, (string) $problem);

            return false;
        }

        if ($wanted !== $current) {
            // Angezeigt wird der gewuenschte Wert, auch im Probelauf: die
            // Spalte soll zeigen, worueber der Befund spricht.
            $note = $this->write($tenant, $wanted, $current);
            $current = $wanted;
        }

        if ($wanted === null || $wanted === '') {
            $this->row($tenant, $domain, '—', $note ?? 'Keine Property — dieses Portal liefert keine Metriken');

            return false;
        }

        if ($this->option('check') && ! $this->option('dry-run')) {
            $state = $service->check($tenant);
            $this->row($tenant, $domain, (string) $current, $note ?? ($state['label'].($state['detail'] === null ? '' : ': '.$state['detail'])));

            return true;
        }

        $state = $service->stateOf($tenant);
        $warning = $service->domainWarning($wanted, $domain);

        $this->row($tenant, $domain, (string) $current, $note ?? ($warning !== null ? $warning : $state['label']));

        return true;
    }

    /**
     * Property schreiben. Der gespeicherte Zugriffsstand gehoert zur alten
     * Property und faellt deshalb mit weg — genauso macht es die
     * Einstellungsseite des Content-Panels.
     */
    private function write(Tenant $tenant, ?string $property, ?string $previous): string
    {
        $from = $previous === null || $previous === '' ? '—' : $previous;
        $to = $property === null || $property === '' ? '—' : $property;

        if ($this->option('dry-run')) {
            return "wuerde {$from} auf {$to} setzen";
        }

        $tenant->run(static function () use ($property): void {
            TenantContentSetting::current()->forceFill([
                'gsc_property' => $property,
                'gsc_check_status' => null,
                'gsc_checked_at' => null,
                'gsc_check_detail' => null,
            ])->save();
        });

        return "{$from} auf {$to} gesetzt";
    }

    /**
     * Domain-Property aus der Portaldomain. `www.` faellt weg: eine
     * Domain-Property deckt alle Subdomains und beide Protokolle ohnehin ab.
     */
    private function derive(string $domain): ?string
    {
        $host = mb_strtolower(trim($domain));
        $host = (string) preg_replace('/^https?:\/\//', '', $host);
        $host = (string) preg_replace('/^www\./', '', trim(explode('/', $host)[0], '.'));

        return $host === '' ? null : 'sc-domain:'.$host;
    }

    private function propertyOf(Tenant $tenant): ?string
    {
        try {
            /** @var string|null $property */
            $property = $tenant->run(static fn (): ?string => TenantContentSetting::query()->value('gsc_property'));

            return $property === null || trim($property) === '' ? null : trim($property);
        } catch (Throwable $exception) {
            $this->warn(sprintf('[%s] Einstellungen nicht lesbar: %s', (string) $tenant->name, $exception->getMessage()));

            return null;
        }
    }

    /**
     * @return array<int, Tenant>
     */
    private function tenants(): array
    {
        $needle = $this->option('tenant');

        /** @var array<int, Tenant> $tenants */
        $tenants = Tenant::all()->all();

        if ($needle === null) {
            return $tenants;
        }

        return array_values(array_filter($tenants, static fn (Tenant $tenant): bool => (string) $tenant->getKey() === (string) $needle
            || (string) $tenant->uuid === (string) $needle
            || (string) $tenant->domain === (string) $needle));
    }

    private function row(Tenant $tenant, string $domain, string $property, string $note): void
    {
        $this->rows[] = [
            (string) $tenant->getKey(),
            (string) $tenant->name,
            $domain === '' ? '—' : $domain,
            $property,
            $note,
        ];
    }
}
