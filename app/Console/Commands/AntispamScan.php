<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\BotScorer;
use App\AntiSpam\Support\ScanHit;
use App\AntiSpam\Support\ScanReport;
use App\AntiSpam\Support\ScanResult;
use App\AntiSpam\Support\TenantSelection;
use App\AntiSpam\SuspectedBotScanner;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Bestandsbereinigung: verdaechtige Konten und Eintraege bewerten (#10).
 *
 *   php artisan antispam:scan --tenant='*' --dry-run   # nur anzeigen
 *   php artisan antispam:scan --tenant='*' --details   # anzeigen und markieren
 *   php artisan antispam:scan --tenant=28 --threshold=50 --dry-run
 *
 * Die Regeln stehen in config/antispam.php (`suspected_bots.detectors`) und
 * sind in docs/turnstile.md §7 begruendet. Bewertet wird additiv, markiert ab
 * der Schwelle (Vorgabe 70 von 100).
 *
 * Markieren heisst: `suspected_bot_at`, `suspected_bot_score` und
 * `suspected_bot_reasons_json` setzen, Eintraege stilllegen (`is_active = 0`,
 * dieselbe Stellung wie "pending"), Konten sperren (`is_blocked = 1`).
 * Geloescht wird hier nichts und niemals hart — das Soft-Delete kommt nach 14
 * Tagen durch `antispam:expire-quarantine` oder von Hand im Admin unter
 * "Verdächtige Accounts/Einträge".
 *
 * Was der Lauf nicht anfasst: importierte Google-Betriebe, zahlende oder
 * verifizierte Betriebe, Administratoren, Konten mit Abonnement oder Bestellung
 * und alles, was im Admin einmal freigegeben wurde. Begruendung in
 * {@see SuspectedBotScanner}.
 *
 * Ohne `--dry-run` schreibt der Lauf. Das ist Absicht und folgt dem Ticket;
 * vor dem ersten Produktivlauf gehoert eine Sicherung der Tabellen `users` und
 * `companies` angelegt (docs/turnstile.md §16.5).
 */
class AntispamScan extends Command
{
    protected $signature = 'antispam:scan
        {--tenant=* : Portale (ID, UUID, Name oder Domain); "*" oder ohne Angabe = alle}
        {--dry-run : Nur anzeigen, nichts markieren}
        {--threshold= : Schwelle 1..100 statt antispam.suspected_bots.threshold}
        {--details : Jeden Treffer einzeln auflisten}';

    protected $description = 'Bewertet Konten und Firmeneinträge gegen die Bot-Erkennungsregeln und markiert Treffer in Quarantäne';

    public function handle(SuspectedBotScanner $scanner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $threshold = $this->threshold();

        if ($threshold === null) {
            return self::FAILURE;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->components->warn('Keine passenden Portale gefunden.');

            return self::FAILURE;
        }

        $this->components->info(
            $dryRun
                ? "Trockenlauf — es wird nichts geschrieben. Schwelle: {$threshold}."
                : "Schreibender Lauf. Schwelle: {$threshold}."
        );
        $this->newLine();

        /** @var list<ScanResult> $results */
        $results = [];

        foreach ($tenants as $tenant) {
            $result = $scanner->scan($tenant, $threshold, ! $dryRun);
            $results[] = $result;

            if ($result->error !== null) {
                $this->components->error("{$tenant->name}: {$result->error}");
            }
        }

        if ($this->option('details')) {
            $this->details($results);
        }

        $this->summary($results, $dryRun);

        $path = ScanReport::write($dryRun ? 'scan-dry-run' : 'scan', [
            'threshold' => $threshold,
            'weights' => app(BotScorer::class)->weights(),
            'portals' => array_map(static fn (ScanResult $r): array => $r->toReport(), $results),
        ]);

        $this->components->info("Bericht: {$path}");

        $failed = array_filter($results, static fn (ScanResult $r): bool => $r->error !== null);

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<ScanResult>  $results
     */
    private function details(array $results): void
    {
        $rows = [];

        foreach ($results as $result) {
            foreach ($result->hits as $hit) {
                $rows[] = [
                    $result->tenantName,
                    $hit->kind->label(),
                    $hit->id,
                    $this->shorten($hit->name),
                    $this->shorten($hit->email),
                    $hit->score->score,
                    $hit->createdAt?->format('d.m.Y') ?? '—',
                    $hit->score->summary(),
                ];
            }
        }

        if ($rows === []) {
            return;
        }

        $this->table(['Portal', 'Art', 'ID', 'Name', 'E-Mail', 'Score', 'Angelegt', 'Gründe'], $rows);
        $this->newLine();
    }

    /**
     * @param  list<ScanResult>  $results
     */
    private function summary(array $results, bool $dryRun): void
    {
        $rows = [];

        foreach ($results as $result) {
            $rows[] = [
                $result->tenantId,
                $result->tenantName,
                $result->error !== null ? '—' : "{$result->accountHits()} / {$result->accountsScanned}",
                $result->error !== null ? '—' : "{$result->listingHits()} / {$result->listingsScanned}",
                $result->protectedSkipped,
                $this->outcome($result, $dryRun),
            ];
        }

        $this->table(
            ['ID', 'Portal', 'Konten (Treffer/geprüft)', 'Einträge (Treffer/geprüft)', 'geschützt', 'Ergebnis'],
            $rows,
        );

        $accounts = array_sum(array_map(static fn (ScanResult $r): int => $r->accountHits(), $results));
        $listings = array_sum(array_map(static fn (ScanResult $r): int => $r->listingHits(), $results));

        $this->components->info(
            $dryRun
                ? "Zusammen {$accounts} Konten und {$listings} Einträge würden markiert."
                : "Zusammen {$accounts} Konten und {$listings} Einträge markiert."
        );
    }

    private function outcome(ScanResult $result, bool $dryRun): string
    {
        if ($result->error !== null) {
            return 'Fehler';
        }

        $hits = $result->accountHits() + $result->listingHits();

        if ($hits === 0) {
            return 'keine Treffer';
        }

        if ($dryRun) {
            return 'würde markiert';
        }

        $marked = count(array_filter($result->hits, static fn (ScanHit $hit): bool => $hit->marked));

        return "{$marked} markiert";
    }

    private function threshold(): ?int
    {
        $option = $this->option('threshold');

        if ($option === null || $option === '') {
            return BotScorer::threshold();
        }

        if (! is_numeric($option) || (int) $option < 1 || (int) $option > BotScorer::MAX_SCORE) {
            $this->components->error('--threshold erwartet eine Zahl von 1 bis '.BotScorer::MAX_SCORE.'.');

            return null;
        }

        return (int) $option;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        return TenantSelection::resolve((array) $this->option('tenant'));
    }

    private function shorten(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        return mb_strimwidth(trim($value), 0, 32, '…');
    }
}
