<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Support\Tenancy\TenantTerms;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Setzt die Branchenbegriffe (#2) bei Bestandstenants (#15).
 *
 * Die Werte stammen ausschliesslich aus database/data/tenant_terms.php
 * (Domain => Begriffe) — geraten oder aus der Domain abgeleitet wird nichts.
 * Tenants ohne Eintrag werden gemeldet und uebersprungen.
 *
 * Regeln:
 *
 * 1. Vorgabe ist der Trockenlauf; geschrieben wird nur mit --apply.
 * 2. Ein Tenant mit bereits gespeicherten terms (mindestens ein nicht leerer
 *    Wert) bleibt unberuehrt, ausser mit --force.
 * 3. Die Mapping-Datei wird vollstaendig gegen TenantTerms::rules() geprueft;
 *    ist ein Eintrag ungueltig, schreibt das Command gar nichts.
 */
class SeedTenantTerms extends Command
{
    protected $signature = 'tenants:seed-terms
        {--dry-run : Nur anzeigen, nichts schreiben (Vorgabe)}
        {--apply : Begriffe tatsaechlich schreiben}
        {--force : Bereits gepflegte Begriffe ueberschreiben}
        {--tenant=* : Auf einzelne Tenants einschraenken (ID, UUID, Name oder Domain)}';

    protected $description = 'Setzt die Branchenbegriffe aller Tenants aus database/data/tenant_terms.php';

    private const RESULT_WRITTEN = 'gesetzt';

    private const RESULT_OVERWRITTEN = 'überschrieben';

    private const RESULT_PRESENT = 'bereits gepflegt';

    private const RESULT_UNMAPPED = 'kein Mapping';

    /**
     * @var array<string, string>
     */
    private const PENDING_LABELS = [
        self::RESULT_WRITTEN => 'würde gesetzt',
        self::RESULT_OVERWRITTEN => 'würde überschrieben',
    ];

    /**
     * Satz zur Sichtkontrolle, deckt alle Keys ab.
     */
    private const EXAMPLE_SENTENCE = 'Sie suchen :branche_akk? :portal zeigt geprüfte :betrieb_plural und alle '
        .':branche_plural Ihrer Region (Singular: :branche, :betrieb).';

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('--apply und --dry-run schliessen sich aus.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $force = (bool) $this->option('force');

        $mapping = $this->mapping();

        if ($mapping === null) {
            return self::FAILURE;
        }

        if (! $apply) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Zum Schreiben: --apply');
            $this->newLine();
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine passenden Tenants gefunden.');

            return self::FAILURE;
        }

        $rows = [];
        $counts = array_fill_keys([
            self::RESULT_WRITTEN,
            self::RESULT_OVERWRITTEN,
            self::RESULT_PRESENT,
            self::RESULT_UNMAPPED,
        ], 0);

        foreach ($tenants as $tenant) {
            $domain = $this->normalizeDomain((string) $tenant->domain);
            $stored = $this->storedTerms($tenant);
            $terms = $mapping[$domain] ?? null;

            if ($terms === null) {
                $counts[self::RESULT_UNMAPPED]++;
                $rows[] = $this->row($tenant, $domain, $tenant->terms, self::RESULT_UNMAPPED);

                continue;
            }

            if ($stored !== null && ! $force) {
                $counts[self::RESULT_PRESENT]++;
                $rows[] = $this->row($tenant, $domain, $tenant->terms, self::RESULT_PRESENT);

                continue;
            }

            $result = $stored === null ? self::RESULT_WRITTEN : self::RESULT_OVERWRITTEN;

            if ($apply) {
                $tenant->setAttribute(TenantTerms::ATTRIBUTE, $terms);
                $tenant->save();
            }

            $counts[$result]++;
            $rows[] = $this->row($tenant, $domain, $terms, $apply ? $result : self::PENDING_LABELS[$result]);
        }

        $this->table(['ID', 'Tenant', 'Domain', 'Begriffe', 'Beispielsatz', 'Ergebnis'], $rows);

        $this->newLine();
        $this->line('Zusammenfassung:');
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        $pending = $counts[self::RESULT_WRITTEN] + $counts[self::RESULT_OVERWRITTEN];

        if (! $apply && $pending > 0) {
            $this->newLine();
            $this->comment("Mit --apply werden {$pending} Tenants gepflegt.");
        }

        if (! $force && $counts[self::RESULT_PRESENT] > 0) {
            $this->line('Bereits gepflegte Begriffe ersetzt nur --force.');
        }

        return self::SUCCESS;
    }

    /**
     * Mapping laden und pruefen. Null, wenn die Datei fehlt oder ein Eintrag
     * ungueltig ist.
     *
     * @return array<string, array<string, string>>|null
     */
    private function mapping(): ?array
    {
        $path = database_path('data/tenant_terms.php');

        if (! is_file($path)) {
            $this->error("Mapping-Datei fehlt: {$path}");

            return null;
        }

        $raw = require $path;

        if (! is_array($raw)) {
            $this->error('Mapping-Datei muss ein Array liefern.');

            return null;
        }

        $mapping = [];
        $errors = [];

        foreach ($raw as $domain => $terms) {
            $validator = Validator::make([TenantTerms::ATTRIBUTE => $terms], TenantTerms::rules());

            if ($validator->fails()) {
                $errors[] = "{$domain}: ".implode(' ', $validator->errors()->all());

                continue;
            }

            $mapping[$this->normalizeDomain((string) $domain)] = TenantTerms::resolve($terms);
        }

        if ($errors !== []) {
            $this->error('Mapping-Datei enthält ungültige Einträge, es wird nichts geschrieben:');
            foreach ($errors as $error) {
                $this->line("  {$error}");
            }

            return null;
        }

        return $mapping;
    }

    /**
     * Rohwert aus der data-Spalte — der Accessor liefert immer Defaults und
     * taugt deshalb nicht fuer die Frage, ob gepflegt ist.
     *
     * @return array<string, mixed>|null
     */
    private function storedTerms(Tenant $tenant): ?array
    {
        $value = $tenant->getAttributes()[TenantTerms::ATTRIBUTE] ?? null;

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return null;
        }

        $filled = array_filter($value, static fn (mixed $v): bool => is_scalar($v) && trim((string) $v) !== '');

        return $filled === [] ? null : $value;
    }

    /**
     * @param  array<string, string>  $terms
     * @return array<int, string>
     */
    private function row(Tenant $tenant, string $domain, array $terms, string $result): array
    {
        $replace = [];
        foreach ($terms as $key => $value) {
            $replace[":{$key}"] = $value;
        }

        // Laengere Keys zuerst, sonst frisst :branche den Anfang von :branche_akk.
        uksort($replace, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return [
            (string) $tenant->id,
            (string) $tenant->name,
            $domain === '' ? '—' : $domain,
            implode(' / ', $terms),
            $result === self::RESULT_UNMAPPED ? '—' : strtr(self::EXAMPLE_SENTENCE, $replace),
            $result,
        ];
    }

    private function normalizeDomain(string $domain): string
    {
        $host = mb_strtolower(trim($domain));

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        /** @var array<int, string> $filter */
        $filter = array_filter((array) $this->option('tenant'), static fn ($v): bool => (string) $v !== '');

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($filter === []) {
            return $tenants;
        }

        return $tenants->filter(function (Tenant $tenant) use ($filter): bool {
            foreach ($filter as $needle) {
                if ((string) $tenant->id === (string) $needle
                    || (string) $tenant->uuid === (string) $needle
                    || mb_strtolower((string) $tenant->name) === mb_strtolower((string) $needle)
                    || $this->normalizeDomain((string) $tenant->domain) === $this->normalizeDomain((string) $needle)) {
                    return true;
                }
            }

            return false;
        })->values();
    }
}
