<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\DraftStatus;
use App\Content\Jobs\QualityCheckJob;
use App\Content\Models\ArticleDraft;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Qualitaetsgate ausfuehren und den Bericht zeigen (#15).
 *
 * Ohne --draft nimmt der Befehl alle Entwuerfe im Status `generated`, also
 * genau die, auf die das Gate ohnehin wartet. Mit --sync laeuft die Pruefung
 * im Vordergrund und der Befehl zeigt danach Note, Regelverstoesse und
 * Entscheidung — das ist der Weg fuer die Staging-Abnahme.
 *
 * Der Tages-Orchestrator (#22) stoesst dieselben Jobs an; dieser Befehl ist
 * der Einstieg fuer Abnahme, Nacharbeit und Fehlersuche.
 */
class ContentQualityCheck extends Command
{
    protected $signature = 'content:quality:check
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--draft= : Nur diesen Entwurf (setzt --tenant voraus)}
        {--limit= : Hoechstens so viele Entwuerfe je Mandant}
        {--sync : Sofort ausfuehren statt in die Queue zu stellen}';

    protected $description = 'Prueft Artikelentwuerfe im Qualitaetsgate und zeigt den Bericht';

    public function handle(): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $draftOption = $this->option('draft');

        if ($draftOption !== null && $tenants->count() !== 1) {
            $this->error('--draft braucht genau einen Mandanten (--tenant).');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $drafts = $this->drafts($tenant, $draftOption === null ? null : (int) $draftOption);

            if ($drafts === []) {
                $this->warn("[{$tenant->name}] kein pruefbarer Entwurf.");

                continue;
            }

            foreach ($drafts as $draftId) {
                $sync
                    ? QualityCheckJob::dispatchSync((int) $tenant->getKey(), $draftId)
                    : QualityCheckJob::dispatch((int) $tenant->getKey(), $draftId);
            }

            $this->line("[{$tenant->name}] ".count($drafts).($sync ? ' geprueft.' : ' eingereiht.'));

            if ($sync) {
                $this->report($tenant, $drafts);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function drafts(Tenant $tenant, ?int $draftId): array
    {
        return $tenant->run(function () use ($draftId): array {
            if ($draftId !== null) {
                return ArticleDraft::query()->whereKey($draftId)->exists() ? [$draftId] : [];
            }

            $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : 25;

            return ArticleDraft::query()
                ->withStatus(DraftStatus::GENERATED)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->map('intval')
                ->all();
        });
    }

    /**
     * @param  array<int, int>  $draftIds
     */
    private function report(Tenant $tenant, array $draftIds): void
    {
        $tenant->run(function () use ($draftIds): void {
            $drafts = ArticleDraft::query()->whereIn('id', $draftIds)->orderBy('id')->get();

            $this->table(
                ['Status', 'Titel', 'Note', 'Schwelle', 'SEO', 'Fakten', 'Rubrik', 'Fix', 'Blocker'],
                $drafts->map(function (ArticleDraft $draft): array {
                    $report = (array) ($draft->quality_report_json ?? []);
                    $quality = (array) ($report['quality'] ?? []);
                    $scores = (array) ($quality['scores'] ?? []);

                    return [
                        $draft->status->label(),
                        Str::limit((string) $draft->title, 36),
                        (string) ($report['final_score'] ?? '-'),
                        (string) ($report['threshold'] ?? '-'),
                        (string) ($scores['seo'] ?? '-'),
                        (string) ($scores['fact'] ?? '-'),
                        (string) ($scores['rubric'] ?? '-'),
                        (string) ($quality['fix_runs'] ?? 0),
                        (string) count((array) ($report['blocking_issues'] ?? [])),
                    ];
                })->all(),
            );

            foreach ($drafts as $draft) {
                $report = (array) ($draft->quality_report_json ?? []);

                foreach ((array) ($report['blocking_issues'] ?? []) as $issue) {
                    $this->error('  '.Str::limit((string) $issue, 160));
                }

                if ((bool) Arr::get($report, 'quality.at_risk', false)) {
                    $this->error("  Tagesziel gefaehrdet: kein weiterer Versuch fuer den Slot ({$draft->title}).");
                }
            }
        });
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            return Tenant::all();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
