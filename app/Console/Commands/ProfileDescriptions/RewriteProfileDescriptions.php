<?php

declare(strict_types=1);

namespace App\Console\Commands\ProfileDescriptions;

use App\Console\Commands\ProfileDescriptions\Concerns\ResolvesPortal;
use App\Models\Portal\Company;
use App\Models\Portal\ProfileDescriptionRewrite;
use App\Models\Tenant;
use App\Services\ProfileDescriptions\ClaudeCliFailed;
use App\Services\ProfileDescriptions\ClaudeCliRunner;
use App\Services\ProfileDescriptions\DescriptionApplier;
use App\Services\ProfileDescriptions\DescriptionValidator;
use App\Services\ProfileDescriptions\InvalidDescription;
use App\Services\ProfileDescriptions\ProfileDescriptionPrompt;
use App\Services\ProfileDescriptions\ProfileFactsBuilder;
use App\Services\ProfileDescriptions\UsageLimitReached;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Schreibt die Beschreibungen der Firmenprofile eines Portals neu (Claude-CLI).
 *
 * Laeuft auf einer Maschine mit installierter und angemeldeter Claude-CLI
 * (CLAUDE_CLI_BINARY) und Zugriff auf die Datenbank des Portals.
 *
 *   php artisan profiles:rewrite-descriptions --tenant=elektrikerportal --ids=12,34 --dry-run
 *   php artisan profiles:rewrite-descriptions --tenant=elektrikerportal
 *   php artisan profiles:rewrite-descriptions --tenant=elektrikerportal --retry-failed
 *
 * Nutzungslimit: der Befehl setzt den laufenden Batch zurueck auf 'pending'
 * und endet mit Exit-Code 75. Fortsetzen = denselben Befehl spaeter erneut
 * starten; er macht beim naechsten offenen Profil weiter. Profile, die nach
 * einem Abbruch auf 'processing' haengen, gehen beim Start zurueck auf 'pending'.
 *
 * Neue Texte stehen zunaechst nur in profile_description_rewrites. Ins Profil
 * kommen sie mit --apply oder spaeter mit profiles:apply-descriptions,
 * zurueck mit profiles:restore-descriptions.
 */
class RewriteProfileDescriptions extends Command
{
    use ResolvesPortal;

    public const EXIT_USAGE_LIMIT = 75;

    protected $signature = 'profiles:rewrite-descriptions
        {--tenant= : Portal (ID, UUID, Domain oder Domain ohne Endung), Pflicht}
        {--batch-size= : Profile je CLI-Aufruf (Vorgabe aus der Config)}
        {--limit= : Hoechstens so viele Profile in diesem Lauf}
        {--ids= : Nur diese Firmen-IDs, kommagetrennt}
        {--model= : Modell (Vorgabe aus der Config)}
        {--retry-failed : Fehlgeschlagene Profile erneut versuchen}
        {--include-owned : Auch Profile mit Inhaber-Konto neu schreiben}
        {--dry-run : Texte erzeugen und ausgeben, nichts schreiben}
        {--apply : Gueltige Texte direkt ins Profil uebernehmen}
        {--sleep= : Pause in Sekunden zwischen zwei Batches}';

    protected $description = 'Schreibt Firmenprofil-Beschreibungen eines Portals ueber die Claude-CLI neu';

    private int $stopAfterFailedBatches = 3;

    /** @var array<string, int> */
    private array $run = ['done' => 0, 'failed' => 0, 'skipped' => 0, 'applied' => 0];

    public function __construct(
        private readonly ProfileFactsBuilder $facts,
        private readonly ProfileDescriptionPrompt $prompt,
        private readonly ClaudeCliRunner $cli,
        private readonly DescriptionValidator $validator,
        private readonly DescriptionApplier $applier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('--dry-run und --apply schliessen sich aus.');

            return self::FAILURE;
        }

        $tenant = $this->resolvePortal();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $this->info("{$tenant->name} ({$tenant->domain})");

        return (int) $tenant->run(fn (): int => $this->runForTenant($tenant));
    }

    private function runForTenant(Tenant $tenant): int
    {
        try {
            $this->cli->binary();
        } catch (ClaudeCliFailed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $lock = Cache::lock('profile-descriptions:'.$tenant->getTenantKey(), 60 * 60 * 24);

        if (! $lock->get()) {
            $this->error('Fuer dieses Portal laeuft bereits ein Lauf.');

            return self::FAILURE;
        }

        try {
            return $this->option('dry-run') ? $this->dryRun($tenant) : $this->rewrite($tenant);
        } finally {
            $lock->release();
        }
    }

    private function rewrite(Tenant $tenant): int
    {
        $ids = $this->idsOption();

        $released = ProfileDescriptionRewrite::query()
            ->where('status', ProfileDescriptionRewrite::STATUS_PROCESSING)
            ->update(['status' => ProfileDescriptionRewrite::STATUS_PENDING]);

        if ($released > 0) {
            $this->warn("{$released} haengende Profile zurueck auf 'pending'.");
        }

        $this->seedRows($ids);

        $limit = $this->limit();
        $total = min($limit ?? PHP_INT_MAX, $this->candidates($ids)->count());

        if ($total === 0) {
            $this->info('Keine offenen Profile.');
            $this->summary();

            return self::SUCCESS;
        }

        $this->log('Lauf gestartet', ['total' => $total, 'model' => $this->model()]);

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $processed = 0;
        $failedBatches = 0;
        $system = $this->prompt->system($tenant);

        while ($processed < $total) {
            $rows = $this->candidates($ids)
                ->with(['company' => fn ($query) => $query->with(ProfileFactsBuilder::relations())])
                ->limit(min($this->batchSize(), $total - $processed))
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $this->markProcessing($rows);

            try {
                $answers = $this->cli->run($system, $this->prompt->input($this->batchFacts($rows)), $this->model());
                $failedBatches = 0;
            } catch (UsageLimitReached $exception) {
                $this->release($rows);
                $bar->clear();
                $this->log('Nutzungslimit erreicht, Lauf beendet', ['message' => $exception->getMessage()], 'warning');
                $this->newLine();
                $this->warn('Nutzungslimit der Claude-CLI erreicht: '.$exception->getMessage());
                $this->line('Der Batch ist zurueck auf pending. Denselben Befehl spaeter erneut starten.');
                $this->summary();

                return self::EXIT_USAGE_LIMIT;
            } catch (ClaudeCliFailed $exception) {
                $this->log('Batch fehlgeschlagen', ['message' => $exception->getMessage()], 'error');

                if (! $exception->answerUnusable) {
                    $this->release($rows);
                    $bar->clear();
                    $this->newLine();
                    $this->error("Claude-CLI fehlgeschlagen, Batch zurueck auf pending: {$exception->getMessage()}");
                    $this->summary();

                    return self::FAILURE;
                }

                $rows->each(fn (ProfileDescriptionRewrite $row) => $this->markFailed($row, $exception->getMessage()));
                $bar->advance($rows->count());
                $processed += $rows->count();

                if (++$failedBatches >= $this->stopAfterFailedBatches) {
                    $bar->clear();
                    $this->newLine();
                    $this->error("{$failedBatches} unbrauchbare Antworten in Folge, Lauf beendet.");
                    $this->summary();

                    return self::FAILURE;
                }

                $this->pause();

                continue;
            }

            $this->store($rows, $answers);
            $bar->advance($rows->count());
            $processed += $rows->count();

            if ($processed < $total) {
                $this->pause();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->log('Lauf beendet', $this->run);
        $this->summary();

        return self::SUCCESS;
    }

    /**
     * Erzeugt Texte fuer --ids bzw. die naechsten offenen Profile und gibt sie
     * mit den Eingabedaten aus. Schreibt nichts, auch keine Statuszeilen.
     */
    private function dryRun(Tenant $tenant): int
    {
        $ids = $this->idsOption();
        $query = Company::query()->with(ProfileFactsBuilder::relations())->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $query->whereNotIn('id', ProfileDescriptionRewrite::query()
                ->where('status', ProfileDescriptionRewrite::STATUS_DONE)
                ->select('company_id'));

            if (! $this->option('include-owned') && config('profile_descriptions.skip_owned')) {
                $query->whereNull('user_id');
            }
        }

        $companies = $query->limit($this->limit() ?? $this->batchSize())->get();

        if ($companies->isEmpty()) {
            $this->info('Keine passenden Profile.');

            return self::SUCCESS;
        }

        $system = $this->prompt->system($tenant);

        foreach ($companies->chunk($this->batchSize()) as $batch) {
            $facts = $batch->map(fn (Company $company): array => $this->facts->build($company))->values()->all();

            try {
                $answers = $this->cli->run($system, $this->prompt->input($facts), $this->model());
            } catch (UsageLimitReached|ClaudeCliFailed $exception) {
                $this->error($exception->getMessage());

                return $exception instanceof UsageLimitReached ? self::EXIT_USAGE_LIMIT : self::FAILURE;
            }

            $byId = collect($answers)->groupBy('id');

            foreach ($facts as $input) {
                $this->newLine();
                $this->line("<fg=cyan>#{$input['id']} {$input['name']}</>");
                $this->line(json_encode($input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

                try {
                    $text = $this->validated($byId->get($input['id']), $input);
                    $words = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
                    $this->line("<fg=green>OK, {$words} Woerter</>");
                    $this->line($text);
                } catch (InvalidDescription $exception) {
                    $this->line("<fg=red>Ungueltig: {$exception->getMessage()}</>");
                    $raw = $byId->get($input['id'])?->first()['description'] ?? '';

                    if ($raw !== '') {
                        $this->line($raw);
                    }
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Legt fuer jede Firma ohne Zeile eine 'pending'-Zeile an.
     *
     * @param  list<int>  $ids
     */
    private function seedRows(array $ids): void
    {
        $table = (new ProfileDescriptionRewrite)->getTable();
        $now = now();

        $missing = Company::query()
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('id', $ids))
            ->whereNotIn('id', ProfileDescriptionRewrite::query()->select('company_id'))
            ->selectRaw('id, ?, 0, ?, ?', [ProfileDescriptionRewrite::STATUS_PENDING, $now, $now]);

        $inserted = DB::connection((new ProfileDescriptionRewrite)->getConnectionName())
            ->table($table)
            ->insertUsing(['company_id', 'status', 'attempts', 'created_at', 'updated_at'], $missing->toBase());

        if ($inserted > 0) {
            $this->line("{$inserted} Profile neu vorgemerkt.");
        }
    }

    /**
     * Offene Zeilen in fester Reihenfolge.
     *
     * @param  list<int>  $ids
     * @return Builder<ProfileDescriptionRewrite>
     */
    private function candidates(array $ids): Builder
    {
        $status = $this->option('retry-failed')
            ? ProfileDescriptionRewrite::STATUS_FAILED
            : ProfileDescriptionRewrite::STATUS_PENDING;

        return ProfileDescriptionRewrite::query()
            ->where('status', $status)
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('company_id', $ids))
            ->when(
                ! $this->option('include-owned') && config('profile_descriptions.skip_owned'),
                fn (Builder $query) => $query->whereHas('company', fn (Builder $company) => $company->whereNull('user_id')),
            )
            ->orderBy('company_id');
    }

    /**
     * Status 'processing' und Original sichern, solange noch nichts uebernommen ist.
     *
     * @param  Collection<int, ProfileDescriptionRewrite>  $rows
     */
    private function markProcessing(Collection $rows): void
    {
        foreach ($rows as $row) {
            $attributes = ['status' => ProfileDescriptionRewrite::STATUS_PROCESSING];

            if ($row->applied_at === null && $row->company !== null) {
                $attributes['original_description'] = $row->company->description;
                $attributes['original_source'] = $row->company->description_source;
            }

            $row->update($attributes);
        }
    }

    /**
     * @param  EloquentCollection<int, ProfileDescriptionRewrite>  $rows
     */
    private function release(EloquentCollection $rows): void
    {
        ProfileDescriptionRewrite::query()
            ->whereIn('id', $rows->modelKeys())
            ->update(['status' => ProfileDescriptionRewrite::STATUS_PENDING]);
    }

    /**
     * @param  Collection<int, ProfileDescriptionRewrite>  $rows
     * @return list<array<string, mixed>>
     */
    private function batchFacts(Collection $rows): array
    {
        return $rows
            ->filter(fn (ProfileDescriptionRewrite $row): bool => $row->company !== null)
            ->map(fn (ProfileDescriptionRewrite $row): array => $this->facts->build($row->company))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ProfileDescriptionRewrite>  $rows
     * @param  list<array{id: int, description: string}>  $answers
     */
    private function store(Collection $rows, array $answers): void
    {
        $byId = collect($answers)->groupBy('id');
        $batchIds = $rows->pluck('company_id')->all();
        $foreign = $byId->keys()->diff($batchIds);

        if ($foreign->isNotEmpty()) {
            $this->log('Antwort enthaelt fremde IDs', ['ids' => $foreign->values()->all()], 'warning');
        }

        foreach ($rows as $row) {
            if ($row->company === null) {
                $row->update(['status' => ProfileDescriptionRewrite::STATUS_SKIPPED, 'last_error' => 'Firma existiert nicht mehr']);
                $this->run['skipped']++;

                continue;
            }

            try {
                $text = $this->validated($byId->get($row->company_id), $this->facts->build($row->company));
            } catch (InvalidDescription $exception) {
                $this->markFailed($row, $exception->getMessage());

                continue;
            }

            $row->update([
                'status' => ProfileDescriptionRewrite::STATUS_DONE,
                'generated_description' => $text,
                'model' => $this->model(),
                'prompt_version' => $this->prompt->version(),
                'last_error' => null,
                'generated_at' => now(),
            ]);
            $this->run['done']++;

            if ($this->option('apply')) {
                $reason = $this->applier->apply($row->fresh(['company']));

                $reason === null
                    ? $this->run['applied']++
                    : $this->log('Nicht uebernommen', ['company_id' => $row->company_id, 'reason' => $reason], 'warning');
            }
        }
    }

    /**
     * @param  Collection<int, array{id: int, description: string}>|null  $answers
     * @param  array<string, mixed>  $facts
     *
     * @throws InvalidDescription
     */
    private function validated(?Collection $answers, array $facts): string
    {
        if ($answers === null || $answers->isEmpty()) {
            throw new InvalidDescription('fehlt in der Antwort');
        }

        if ($answers->count() > 1) {
            throw new InvalidDescription('ID mehrfach in der Antwort');
        }

        return $this->validator->check($answers->first()['description'], $facts);
    }

    private function markFailed(ProfileDescriptionRewrite $row, string $reason): void
    {
        $attempts = $row->attempts + 1;
        $skipped = $attempts >= (int) config('profile_descriptions.max_attempts', 3);

        $row->update([
            'status' => $skipped ? ProfileDescriptionRewrite::STATUS_SKIPPED : ProfileDescriptionRewrite::STATUS_FAILED,
            'attempts' => $attempts,
            'last_error' => mb_substr($reason, 0, 1000),
        ]);

        $this->run[$skipped ? 'skipped' : 'failed']++;
        $this->log('Profil ungueltig', ['company_id' => $row->company_id, 'attempts' => $attempts, 'reason' => $reason], 'warning');
    }

    private function summary(): void
    {
        $counts = ProfileDescriptionRewrite::query()
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $owned = Company::query()->whereNotNull('user_id')->count();

        $this->table(['', 'dieser Lauf', 'gesamt'], [
            ['done', $this->run['done'], (int) ($counts[ProfileDescriptionRewrite::STATUS_DONE] ?? 0)],
            ['failed', $this->run['failed'], (int) ($counts[ProfileDescriptionRewrite::STATUS_FAILED] ?? 0)],
            ['skipped', $this->run['skipped'], (int) ($counts[ProfileDescriptionRewrite::STATUS_SKIPPED] ?? 0)],
            ['uebernommen', $this->run['applied'], ProfileDescriptionRewrite::query()->whereNotNull('applied_at')->count()],
            ['verbleibend (pending)', '', (int) ($counts[ProfileDescriptionRewrite::STATUS_PENDING] ?? 0)],
            ['Profile mit Inhaber (ohne --include-owned ausgelassen)', '', $owned],
        ]);
    }

    private function pause(): void
    {
        $seconds = (int) ($this->option('sleep') ?? config('profile_descriptions.sleep', 0));

        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    private function model(): string
    {
        return (string) ($this->option('model') ?: config('profile_descriptions.model'));
    }

    private function batchSize(): int
    {
        return max(1, (int) ($this->option('batch-size') ?: config('profile_descriptions.batch_size', 25)));
    }

    private function limit(): ?int
    {
        $limit = $this->option('limit');

        return $limit === null || $limit === '' ? null : max(0, (int) $limit);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('profile-descriptions')->{$level}($message, ['tenant' => tenant()?->getTenantKey(), ...$context]);
    }
}
