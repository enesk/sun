<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\DeepResearchJob;
use App\Guide\Jobs\FreshnessProbeJob;
use App\Guide\Models\Fact;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fuehrt Freshness-Probe oder Tiefenrecherche fuer ein Thema einzeln aus
 * (#8), synchron und ohne Folgeschritte der Kette. Der Lauf wird wie im
 * Tageslauf als guide_topic_runs-Zeile des heutigen Tages gefuehrt.
 *
 *   php artisan guide:research 12 --tenant=7            Probe
 *   php artisan guide:research waermepumpe-kosten --tenant=sanitaerfinder.com --deep
 *   php artisan guide:research 12 --tenant=7 --deep --replace
 *
 * Je Thema und Tag gibt es genau einen Lauf. --replace loescht einen
 * heutigen Lauf, der nicht im Schreiben, in der Pruefung oder
 * veroeffentlicht ist, und legt ihn neu an (Kosten bleiben in llm_usage_logs).
 */
class GuideResearch extends Command
{
    protected $signature = 'guide:research
        {topic : ID oder Slug des Themas}
        {--tenant= : Portal (ID, UUID oder Domain)}
        {--deep : Tiefenrecherche statt Probe}
        {--replace : heutigen Lauf des Themas ersetzen}';

    protected $description = 'Fuehrt die Aktualitaetsprobe oder die Tiefenrecherche eines Ratgeber-Themas aus und zeigt Fakten und Quellen';

    /** Statuswerte, die --replace nie loescht. */
    private const PROTECTED_STATUSES = [RunStatus::WRITING, RunStatus::CHECKING, RunStatus::REVIEW, RunStatus::PUBLISHED];

    public function handle(): int
    {
        $tenant = $this->tenant();

        if ($tenant === null) {
            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            return $this->research($tenant);
        } finally {
            tenancy()->end();
        }
    }

    private function research(Tenant $tenant): int
    {
        $topic = $this->topic((string) $this->argument('topic'));

        if ($topic === null) {
            $this->error("Thema nicht gefunden: {$this->argument('topic')}");

            return self::FAILURE;
        }

        $deep = (bool) $this->option('deep');

        if (! $deep && $topic->currentFacts()->doesntExist()) {
            $this->error('Das Thema hat noch kein Fakten-Set; die Probe entfaellt. Zuerst mit --deep recherchieren.');

            return self::FAILURE;
        }

        $run = $this->todaysRun($topic, $deep);

        if ($run === null) {
            return self::FAILURE;
        }

        $this->line(sprintf(
            'Portal <info>%s</info> · Thema <info>#%d %s</info> · Lauf <info>#%d</info> (%s) · zuletzt geprueft: %s',
            (string) $tenant->name,
            (int) $topic->getKey(),
            (string) $topic->question,
            (int) $run->getKey(),
            $run->mode->value,
            $topic->last_checked_at?->toDateTimeString() ?? 'nie',
        ));
        $this->newLine();

        $deep
            ? DeepResearchJob::dispatchSync((int) $tenant->getKey(), (int) $run->getKey(), false)
            : FreshnessProbeJob::dispatchSync((int) $tenant->getKey(), (int) $run->getKey(), false);

        $run->refresh();
        $topic->refresh();

        $deep ? $this->printResearch($run) : $this->printProbe($run);

        $this->printFacts($topic);

        $this->newLine();
        $this->line(sprintf(
            'Lauf-Status: <info>%s</info> · Kosten des Laufs: %.4f USD · facts_hash: %s · naechste Pruefung: %s',
            $run->status->value,
            (float) $run->cost_usd,
            $topic->facts_hash ?? '–',
            $topic->next_due_at?->toDateTimeString() ?? '–',
        ));

        if ($run->status === RunStatus::FAILED) {
            $this->error((string) $run->error);

            return self::FAILURE;
        }

        // Bei Budget-Stopp stellt der Job den Lauf zurueck (release), ohne Ergebnis.
        if ($deep ? $run->research_json === null : $run->status === RunStatus::PROBING) {
            $this->warn('Der Lauf wurde zurueckgestellt (Budget erschoepft?), siehe guide_alerts.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function todaysRun(Topic $topic, bool $deep): ?TopicRun
    {
        $today = Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();
        $run = $topic->runs()->whereDate('run_date', $today)->first();
        $usable = $deep ? [RunStatus::QUEUED, RunStatus::RESEARCHING] : [RunStatus::QUEUED];

        if ($run !== null && in_array($run->status, $usable, true) && ($deep || $run->mode === RunMode::UPDATE)) {
            return $run;
        }

        if ($run !== null) {
            if (! $this->option('replace') || in_array($run->status, self::PROTECTED_STATUSES, true)) {
                $this->error(sprintf(
                    'Fuer heute gibt es schon Lauf #%d (Status %s, Modus %s).%s',
                    (int) $run->getKey(),
                    $run->status->value,
                    $run->mode->value,
                    in_array($run->status, self::PROTECTED_STATUSES, true) ? '' : ' Mit --replace ersetzen.',
                ));

                return null;
            }

            $run->delete();
        }

        return $topic->runs()->create([
            'run_date' => $today,
            'status' => RunStatus::QUEUED,
            // Die Probe gibt es nur im update-Modus; ohne Artikel ist die Recherche eine Neuanlage.
            'mode' => ! $deep || $topic->article_id !== null ? RunMode::UPDATE : RunMode::CREATE,
        ]);
    }

    private function printProbe(TopicRun $run): void
    {
        $probe = (array) ($run->probe_json ?? []);

        if ($probe === []) {
            return;
        }

        $this->info('Probe');
        $this->line(sprintf(
            'changed: %s · confidence: %.2f · Entscheidung: %s',
            ($probe['changed'] ?? false) ? 'ja' : 'nein',
            (float) ($probe['confidence'] ?? 0),
            (string) ($probe['decision'] ?? '–'),
        ));
        $this->line('Begruendung: '.(string) ($probe['reason'] ?? ''));

        if (! empty($probe['candidate_changes'])) {
            $this->table(
                ['Schluessel', 'Alt', 'Neu', 'Quelle', 'Stufe', 'Datum'],
                array_map(fn (array $change): array => [
                    (string) ($change['key'] ?? ''),
                    mb_strimwidth((string) ($change['old_value'] ?? '–'), 0, 30, '…'),
                    mb_strimwidth((string) ($change['new_value'] ?? ''), 0, 30, '…'),
                    mb_strimwidth((string) ($change['source_url'] ?? ''), 0, 60, '…'),
                    (string) ($change['trust_level'] ?? '–'),
                    (string) ($change['published_at'] ?? '–'),
                ], (array) $probe['candidate_changes']),
            );
        }

        $this->printMeta((array) ($probe['meta'] ?? []));
    }

    private function printResearch(TopicRun $run): void
    {
        $research = (array) ($run->research_json ?? []);

        if ($research === []) {
            return;
        }

        $this->info('Tiefenrecherche');
        $this->line(sprintf(
            'neu: %d · geaendert: %d · bestaetigt: %d · nicht erneut gefunden: %d · verworfen: %d · Konflikte: %d',
            count((array) ($research['created_keys'] ?? [])),
            count((array) ($research['changed'] ?? [])),
            count((array) ($research['confirmed_keys'] ?? [])),
            count((array) ($research['unconfirmed_keys'] ?? [])),
            count((array) ($research['rejected'] ?? [])),
            count((array) ($research['conflicts'] ?? [])),
        ));

        foreach ((array) ($research['changed'] ?? []) as $change) {
            $this->line("  geaendert {$change['key']}: {$change['old_value']} -> {$change['new_value']}");
        }

        foreach ((array) ($research['conflicts'] ?? []) as $conflict) {
            $this->warn(sprintf(
                '  Konflikt %s (%s): gewaehlt %s aus %s',
                (string) $conflict['key'],
                (string) $conflict['rule'],
                (string) $conflict['winner']['value'],
                (string) $conflict['winner']['source_url'],
            ));
        }

        foreach ((array) ($research['rejected'] ?? []) as $rejected) {
            $this->line(sprintf('  verworfen %s: %s (%s)', (string) ($rejected['key'] ?? ''), (string) $rejected['reason'], (string) $rejected['source_url']));
        }

        foreach ((array) ($research['open_points'] ?? []) as $point) {
            $this->line("  offen: {$point}");
        }

        $this->printMeta((array) ($research['meta'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function printMeta(array $meta): void
    {
        if ($meta === []) {
            return;
        }

        $this->line(sprintf(
            'Template %s v%d · Suchen: %d (%s) · Treffer: %d · Kosten: %.4f USD%s',
            (string) ($meta['template_key'] ?? ''),
            (int) ($meta['template_version'] ?? 0),
            (int) ($meta['search_count'] ?? 0),
            (string) ($meta['search_filter'] ?? ''),
            (int) ($meta['citations'] ?? 0),
            (float) ($meta['cost_usd'] ?? 0),
            empty($meta['search_errors']) ? '' : ' · Suchfehler: '.implode(', ', (array) $meta['search_errors']),
        ));
        $this->newLine();
    }

    private function printFacts(Topic $topic): void
    {
        $facts = $topic->currentFacts()->with('source')->orderBy('key')->get();

        $this->info("Fakten-Set ({$facts->count()})");
        $this->table(
            ['Schluessel', 'Wert', 'gilt ab', 'Stufe', 'Quelle', 'abgerufen', 'alt'],
            $facts->map(fn (Fact $fact): array => [
                (string) $fact->key,
                mb_strimwidth(trim($fact->value.' '.($fact->unit ?? '')), 0, 40, '…'),
                $fact->valid_from?->toDateString() ?? '–',
                $fact->source?->trust_level->value ?? '–',
                mb_strimwidth((string) ($fact->source?->url ?? '–'), 0, 60, '…'),
                $fact->source?->retrieved_at?->toDateString() ?? '–',
                $fact->stale_source ? 'ja' : '',
            ])->all(),
        );

        $sources = $topic->sources()->whereHas('facts', fn ($query) => $query->where('is_current', true))->orderByDesc('published_at')->get();

        $this->info("Quellen ({$sources->count()})");
        $this->table(
            ['Stufe', 'Herausgeber', 'Titel', 'URL', 'veroeffentlicht', 'abgerufen'],
            $sources->map(fn ($source): array => [
                $source->trust_level->value,
                mb_strimwidth((string) ($source->publisher ?? '–'), 0, 30, '…'),
                mb_strimwidth((string) ($source->title ?? '–'), 0, 40, '…'),
                mb_strimwidth((string) $source->url, 0, 60, '…'),
                $source->published_at?->toDateString() ?? '–',
                $source->retrieved_at?->toDateString() ?? '–',
            ])->all(),
        );
    }

    private function topic(string $needle): ?Topic
    {
        if (ctype_digit($needle)) {
            return Topic::query()->find((int) $needle);
        }

        return Topic::query()->where('slug', $needle)->first();
    }

    private function tenant(): ?Tenant
    {
        $needle = trim((string) $this->option('tenant'));

        if ($needle === '') {
            $this->error('--tenant angeben (ID, UUID oder Domain).');

            return null;
        }

        $tenant = ctype_digit($needle)
            ? Tenant::query()->find((int) $needle)
            : Tenant::query()->where('uuid', $needle)->orWhere('domain', $needle)->first();

        if ($tenant === null) {
            $this->error("Portal nicht gefunden: {$needle}");
        }

        return $tenant;
    }
}
