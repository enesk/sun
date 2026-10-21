<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Companies\Deletion\DeleteCompanyAction;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Console\Command;
use Throwable;

/**
 * Loeschpipeline von der Kommandozeile (#8).
 *
 *   php artisan company:delete 12 4711 --dry-run
 *   php artisan company:delete 12 4711 --reason="Mail vom 20.10." --sitemap=sync
 *   php artisan company:delete 12 4711 --request=88
 *
 * Derselbe Weg wie Panel und Auto-Regel — es gibt nur eine Action. Ohne
 * `--dry-run` loescht der Aufruf sofort und fragt bei nicht-interaktivem
 * Lauf nicht nach.
 */
class CompanyDelete extends Command
{
    protected $signature = 'company:delete
        {tenant : ID des Portals (tenants.id)}
        {company : ID des Betriebs in der Tenant-DB}
        {--request= : ID der Anfrage in profile_requests (Central-DB), an der protokolliert wird}
        {--reason= : Grund, landet in companies.deleted_reason}
        {--sitemap= : queue (Standard), sync oder off}
        {--notify : Bestaetigungsmail senden, auch ohne --request wirkungslos ohne Adresse}
        {--dry-run : Nur die geplanten Schritte zeigen, nichts aendern}';

    protected $description = 'Löscht ein Firmenprofil vollständig über die Löschpipeline (Profil, Bilder, Bewertungen, Leads, 410, Sitemap, IndexNow, Caches)';

    public function handle(DeleteCompanyAction $action): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Portal #{$this->argument('tenant')} nicht gefunden.");

            return self::FAILURE;
        }

        $companyId = (int) $this->argument('company');
        $request = $this->resolveRequest();

        if ($request === false) {
            return self::FAILURE;
        }

        $options = array_filter([
            'request' => $request,
            'reason' => $this->option('reason') ?: 'Artisan company:delete',
            'sitemap_mode' => $this->option('sitemap') ?: null,
            'notify' => $this->option('notify') ? true : null,
        ], fn ($value): bool => $value !== null);

        try {
            return $this->option('dry-run')
                ? $this->showPlan($action, $tenant, $companyId, $options)
                : $this->performDeletion($action, $tenant, $companyId, $options);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function showPlan(DeleteCompanyAction $action, Tenant $tenant, int $companyId, array $options): int
    {
        $name = $tenant->run(fn (): ?string => Company::withTrashed()->find($companyId)?->name);

        $this->info("Trockenlauf — Portal {$tenant->name} (#{$tenant->getKey()}), Betrieb #{$companyId} \"{$name}\":");
        $this->newLine();

        $rows = [];

        foreach ($action->plan($tenant, $companyId, $options) as $index => $step) {
            $rows[] = [$index + 1, $step['key'], $step['plan']];
        }

        $this->table(['#', 'Schritt', 'Würde tun'], $rows);
        $this->newLine();
        $this->warn('Nichts geändert. Ohne --dry-run läuft die Löschung sofort.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function performDeletion(DeleteCompanyAction $action, Tenant $tenant, int $companyId, array $options): int
    {
        $result = $action->forTenantCompany($tenant, $companyId, $options);

        $this->newLine();
        $this->info("Lauf {$result->runId} — \"{$result->companyName}\" (#{$result->companyId}):");

        $rows = [];

        foreach ($result->steps as $key => $step) {
            $rows[] = [$key, $step->status->label(), $step->message ?? ''];
        }

        $this->table(['Schritt', 'Ergebnis', 'Meldung'], $rows);

        $summary = $result->summary();
        $this->line(sprintf(
            '%d Schritte: %d erledigt, %d übersprungen, %d fehlgeschlagen (%d ms).',
            $summary['steps'],
            $summary['ok'],
            $summary['skipped'],
            $summary['failed'],
            $summary['duration_ms'],
        ));
        $this->line("Protokoll: deletion_log, run_id = {$result->runId}");

        if (! $result->ok()) {
            $this->error('Mindestens ein Schritt ist fehlgeschlagen. Der Aufruf ist wiederholbar.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return ProfileRequest|null|false false = Abbruch
     */
    private function resolveRequest(): ProfileRequest|null|false
    {
        $id = $this->option('request');

        if (! $id) {
            return null;
        }

        $request = ProfileRequest::find($id);

        if ($request === null) {
            $this->error("Anfrage #{$id} nicht gefunden (Central-DB).");

            return false;
        }

        return $request;
    }
}
