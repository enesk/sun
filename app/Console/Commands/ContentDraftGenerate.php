<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\DraftStatus;
use App\Content\Jobs\GenerateDraftJob;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Artikelentwuerfe erzeugen (#14).
 *
 * Ohne Parameter arbeitet der Befehl die fuer heute ausgewaehlten Themen aller
 * Mandanten ab, hoechstens `articles_per_day` je Mandant. Mit --sync laeuft
 * die Erzeugung sofort im Vordergrund und zeigt am Ende, was entstanden ist —
 * das ist der Weg fuer die Staging-Abnahme.
 *
 * Der Tages-Orchestrator (#22) ruft spaeter dieselben Jobs; dieser Befehl ist
 * der Einstieg fuer Abnahme, Nacharbeit und Fehlersuche.
 */
class ContentDraftGenerate extends Command
{
    protected $signature = 'content:draft:generate
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--topic= : Nur diesen Themenkandidaten (setzt --tenant voraus)}
        {--date= : Auswahltag der Themen (Y-m-d), Vorgabe: heute}
        {--limit= : Hoechstens so viele Entwuerfe je Mandant}
        {--sync : Sofort ausfuehren statt in die Queue zu stellen}';

    protected $description = 'Erzeugt Artikelentwuerfe aus den ausgewaehlten Themen';

    public function handle(): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            // Vor dem Go-Live ist kein Portal freigeschaltet (#26). Das ist
            // kein Fehler, sonst meldete der Scheduler jeden Tag Alarm.
            if ($this->option('tenant') === null) {
                $this->warn('Kein Portal ist für die Content-Pipeline freigeschaltet (content:rollout).');

                return self::SUCCESS;
            }

            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $topicOption = $this->option('topic');

        if ($topicOption !== null && $tenants->count() !== 1) {
            $this->error('--topic braucht genau einen Mandanten (--tenant).');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');
        $date = $this->option('date') !== null
            ? CarbonImmutable::parse((string) $this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $topics = $this->topics($tenant, $date, $topicOption === null ? null : (int) $topicOption);

            if ($topics === []) {
                $this->warn("[{$tenant->name}] kein ausgewaehltes Thema fuer {$date->toDateString()}.");

                continue;
            }

            foreach ($topics as $slot => $topicId) {
                if ($sync) {
                    GenerateDraftJob::dispatchSync((int) $tenant->getKey(), null, $topicId, $slot + 1);

                    continue;
                }

                GenerateDraftJob::dispatch((int) $tenant->getKey(), null, $topicId, $slot + 1);
            }

            $this->line("[{$tenant->name}] ".count($topics).($sync ? ' erzeugt.' : ' eingereiht.'));

            if ($sync) {
                $this->report($tenant, $topics);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Die Themen, aus denen Entwuerfe entstehen sollen: die Tagesauswahl,
     * begrenzt auf das Tagesziel des Mandanten.
     *
     * @return array<int, int>
     */
    private function topics(Tenant $tenant, CarbonImmutable $date, ?int $topicId): array
    {
        return $tenant->run(function () use ($date, $topicId): array {
            if ($topicId !== null) {
                return TopicCandidate::query()->whereKey($topicId)->exists() ? [$topicId] : [];
            }

            $limit = $this->option('limit') !== null
                ? max(1, (int) $this->option('limit'))
                : max(1, (int) (TenantContentSetting::current()->articles_per_day ?? 2));

            return TopicCandidate::query()
                ->selectedFor($date)
                ->orderByDesc('total_score')
                ->limit($limit)
                ->pluck('id')
                ->map('intval')
                ->all();
        });
    }

    /**
     * @param  array<int, int>  $topicIds
     */
    private function report(Tenant $tenant, array $topicIds): void
    {
        $tenant->run(function () use ($topicIds): void {
            $drafts = ArticleDraft::query()
                ->with('topicCandidate:id,primary_keyword')
                ->whereIn('topic_candidate_id', $topicIds)
                ->orderByDesc('id')
                ->get();

            if ($drafts->isEmpty()) {
                $this->warn('Kein Entwurf entstanden.');

                return;
            }

            $this->table(
                ['Status', 'Titel', 'Region', 'Woerter', 'Abschnitte', 'Links', 'Quellen', 'USD'],
                $drafts->map(fn (ArticleDraft $draft): array => [
                    $draft->status->label(),
                    Str::limit((string) $draft->title, 40),
                    $draft->region_scope.($draft->region_code !== null ? " {$draft->region_code}" : ''),
                    (string) (($draft->outline_json['word_count'] ?? null) ?? '-'),
                    (string) count($draft->outline_json['sections'] ?? []),
                    (string) count($draft->outline_json['internal_links'] ?? []),
                    (string) $draft->sources()->count(),
                    number_format((float) $draft->generation_cost_usd, 4),
                ])->all(),
            );

            foreach ($drafts->where('status', DraftStatus::FAILED) as $draft) {
                $generation = (array) (($draft->quality_report_json ?? [])['generation'] ?? []);
                $reason = (string) ($generation['failed_reason'] ?? 'unbekannt');
                $this->error("  {$draft->title}: {$reason}");
            }
        });
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            // Ohne --tenant arbeitet der Befehl nur auf freigeschalteten
            // Portalen (#26). Ein einzeln benanntes Portal laeuft weiter,
            // damit sich ein Portal vor dem Go-Live pruefen laesst.
            return app(TenantRollout::class)->activeTenants();
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
