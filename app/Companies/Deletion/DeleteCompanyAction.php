<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Mail\DeletionCompletedMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Der **einzige** Weg, ein Firmenprofil zu loeschen (SUN-RQ #8).
 *
 * Aufgerufen aus dem Panel (/verwaltung, #7), aus der Auto-Regel nach
 * Ablauf der Karenzfrist (#9) und als Artisan-Command
 * (`company:delete {tenant} {company}`). Es gibt bewusst keinen zweiten,
 * kuerzeren Pfad: der alte Loeschweg im Panel hat nur Oeffnungszeiten,
 * Kategorien und Bilder angefasst und Bewertungen, Leads, Statistiken,
 * Sitemap und Index stehen gelassen.
 *
 * Ablauf:
 *  1. Kontextwechsel in das Portal der Anfrage ({@see TenantCompanyResolver}).
 *  2. Pfade und Kategoriezuordnung merken, **bevor** etwas geloescht wird —
 *     danach sind sie nicht mehr ermittelbar.
 *  3. Schritte in der Reihenfolge aus `config('profile-requests.deletion.steps')`.
 *     Jeder Schritt wird einzeln protokolliert; **ein Fehler haelt die
 *     uebrigen nicht auf** (DSGVO: lieber 8 von 9 Datenarten weg als keine).
 *  4. Abschlusszeile im Protokoll, Bestaetigungsmail an den Antragsteller,
 *     und bei einer Anfrage der Statuswechsel nach APPLIED.
 *
 * Keine umfassende Transaktion: die Schritte sprechen Dateien, HTTP und zwei
 * Datenbanken an. Ein Rollback waere ohnehin unmoeglich, sobald die erste
 * Bilddatei weg ist. Stattdessen ist jeder Schritt wiederholbar — ein
 * zweiter Lauf raeumt nach, was beim ersten gescheitert ist.
 */
class DeleteCompanyAction
{
    public function __construct(
        private readonly TenantCompanyResolver $resolver,
        private readonly DeletionLogger $logger,
    ) {}

    /**
     * Loeschung zu einer Anfrage aus der Inbox — der Regelfall (#7, #9).
     *
     * @param  array<string, mixed>  $options  siehe {@see self::forTenantCompany()}
     */
    public function forRequest(ProfileRequest $request, array $options = []): DeletionResult
    {
        if ($request->tenant_id === null || $request->company_id === null) {
            throw new RuntimeException('Die Anfrage hat kein Portal oder keinen Betrieb — Löschung nicht möglich.');
        }

        $tenant = $this->resolver->tenant((int) $request->tenant_id);

        if ($tenant === null) {
            throw new RuntimeException("Portal #{$request->tenant_id} nicht gefunden.");
        }

        return $this->forTenantCompany($tenant, (int) $request->company_id, $options + [
            'request' => $request,
            'reason' => "Anfrage #{$request->getKey()} ({$request->type->value})",
        ]);
    }

    /**
     * Loeschung eines Profils des laufenden Portals — der Weg aus
     * /verwaltung, wo der Tenant-Kontext schon steht.
     *
     * @param  array<string, mixed>  $options
     */
    public function forCompany(Company $company, array $options = []): DeletionResult
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            throw new RuntimeException('Ohne Portal-Kontext: forTenantCompany() mit dem Tenant aufrufen.');
        }

        return $this->forTenantCompany($tenant, (int) $company->getKey(), $options);
    }

    /**
     * Loeschung im Kontext eines Portals — der Weg des Artisan-Commands.
     *
     * @param  array<string, mixed>  $options
     *                                         - request: ProfileRequest|null — Vorgang, an dem protokolliert wird
     *                                         - reason: string|null — landet in `companies.deleted_reason`
     *                                         - dry_run: bool — nichts aendern, nur den Plan liefern
     *                                         - notify: bool — Bestaetigungsmail senden (Standard: ja, wenn es eine Anfrage gibt)
     *                                         - sitemap_mode: 'queue'|'sync'|'off'
     */
    public function forTenantCompany(Tenant $tenant, int $companyId, array $options = []): DeletionResult
    {
        // Bewusst `Tenant::run()` und nicht TenantCompanyResolver::runFor():
        // der Resolver schluckt Fehler, damit die Inbox nicht an einem
        // kaputten Portal haengenbleibt. Eine Loeschung darf dagegen nie
        // still scheitern — der Aufrufer (Panel, Command) soll den Fehler
        // sehen. Der vorher aktive Tenant wird hinterher wiederhergestellt.
        return $tenant->run(function (Tenant $tenant) use ($companyId, $options): DeletionResult {
            return $this->perform($tenant, $this->findCompany($companyId), $options);
        });
    }

    /**
     * Der Plan ohne jede Aenderung — Ausgabe von `--dry-run`.
     *
     * @param  array<string, mixed>  $options
     * @return list<array{key: string, label: string, plan: string}>
     */
    public function plan(Tenant $tenant, int $companyId, array $options = []): array
    {
        return $tenant->run(function (Tenant $tenant) use ($companyId, $options): array {
            $context = $this->context($tenant, $this->findCompany($companyId), ['dry_run' => true] + $options);
            $lines = [];

            foreach ($this->steps() as $step) {
                $lines[] = [
                    'key' => $step->key(),
                    'label' => $step->label(),
                    'plan' => $this->safePlan($step, $context),
                ];
            }

            return $lines;
        });
    }

    private function findCompany(int $companyId): Company
    {
        $company = Company::withTrashed()->with('city', 'categories')->find($companyId);

        if ($company === null) {
            throw new RuntimeException("Betrieb #{$companyId} gibt es in diesem Portal nicht.");
        }

        return $company;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function perform(Tenant $tenant, Company $company, array $options): DeletionResult
    {
        $context = $this->context($tenant, $company, $options);
        $startedAt = CarbonImmutable::now();
        $results = [];

        foreach ($this->steps() as $step) {
            $stepStartedAt = CarbonImmutable::now();

            try {
                $result = $context->dryRun
                    ? StepResult::skipped('Trockenlauf: '.$this->safePlan($step, $context))
                    : $step->run($context);
            } catch (Throwable $exception) {
                // Ein Schritt haelt die anderen nicht auf.
                $result = StepResult::failed(
                    Str::limit($exception->getMessage(), 500, ''),
                    ['exception' => $exception::class],
                );

                Log::error('Loeschpipeline: Schritt fehlgeschlagen.', [
                    'tenant_id' => $tenant->getKey(),
                    'company_id' => $company->getKey(),
                    'step' => $step->key(),
                    'exception' => $exception->getMessage(),
                ]);
            }

            $this->logger->step($context, $step->key(), $result, $stepStartedAt, CarbonImmutable::now());

            $results[$step->key()] = $result;
        }

        $result = new DeletionResult(
            runId: $context->runId,
            companyId: (int) $company->getKey(),
            companyName: (string) $context->note('company_name', $company->name),
            steps: $results,
            dryRun: $context->dryRun,
            durationMs: (int) round(CarbonImmutable::now()->getTimestampMs() - $startedAt->getTimestampMs()),
        );

        $this->logger->finished($context, $result->summary() + [
            'started_at' => $startedAt,
            'message' => $result->ok() ? 'Löschung vollständig.' : 'Löschung mit Fehlern — Schritte einzeln im Protokoll.',
        ]);

        if (! $context->dryRun) {
            $this->notify($context, $result, $options);
            $this->closeRequest($context, $result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function context(Tenant $tenant, Company $company, array $options): DeletionContext
    {
        $request = $options['request'] ?? null;

        $context = new DeletionContext(
            tenant: $tenant,
            company: $company,
            runId: (string) Str::uuid(),
            dryRun: (bool) ($options['dry_run'] ?? false),
            request: $request instanceof ProfileRequest ? $request : null,
            reason: isset($options['reason']) ? Str::limit((string) $options['reason'], 191, '') : null,
        );

        // Was nach dem ersten Schritt nicht mehr zu holen ist.
        $context->remember('paths', app(GoneUrlRegistry::class)->pathsFor($company));
        $context->remember('category_ids', $company->categories->pluck('id')->all());
        $context->remember('company_name', (string) $company->name);

        if (isset($options['sitemap_mode'])) {
            $context->remember('sitemap_mode', (string) $options['sitemap_mode']);
        }

        return $context;
    }

    /**
     * Die Schritte in der Reihenfolge aus der Config. Ein unbekannter Eintrag
     * wird uebersprungen und geloggt — eine halbe Pipeline ist besser als
     * keine, aber nichts darf stillschweigend ausfallen.
     *
     * @return list<DeletionStep>
     */
    public function steps(): array
    {
        $steps = [];

        foreach ((array) config('profile-requests.deletion.steps', []) as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, DeletionStep::class)) {
                Log::error('Loeschpipeline: Schritt aus der Config nicht nutzbar.', ['step' => $class]);

                continue;
            }

            $steps[] = app($class);
        }

        return $steps;
    }

    private function safePlan(DeletionStep $step, DeletionContext $context): string
    {
        try {
            return $step->plan($context);
        } catch (Throwable $exception) {
            return 'Plan nicht ermittelbar: '.Str::limit($exception->getMessage(), 200, '');
        }
    }

    /**
     * Bestaetigung an den Antragsteller. Ohne Anfrage gibt es keine Adresse
     * — eine Loeschung von Hand im Panel verschickt deshalb nichts.
     *
     * @param  array<string, mixed>  $options
     */
    private function notify(DeletionContext $context, DeletionResult $result, array $options): void
    {
        $request = $context->request;
        $notify = (bool) ($options['notify'] ?? ($request !== null));

        if (! $notify || $request === null || $request->requester_email === '') {
            return;
        }

        try {
            Mail::to($request->requester_email)->send(new DeletionCompletedMail(
                request: $request,
                tenant: $context->tenant,
                companyName: $result->companyName,
                steps: $result->completedKeys(),
            ));

            $request->recordEvent(
                RequestEventType::MAIL_SENT,
                ['mail' => 'deletion_completed', 'run_id' => $result->runId],
                RequestEvent::ACTOR_SYSTEM,
            );
        } catch (Throwable $exception) {
            Log::error('Loeschpipeline: Bestaetigungsmail nicht versendet.', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Die Anfrage auf APPLIED setzen — aber nur, wenn der Lauf sauber war.
     * Bleibt ein Schritt offen, bleibt der Vorgang offen; sonst gilt eine
     * halbe Loeschung als erledigt.
     */
    private function closeRequest(DeletionContext $context, DeletionResult $result): void
    {
        $request = $context->request;

        if ($request === null || ! $result->ok() || ! $request->isOpen()) {
            return;
        }

        try {
            $request->apply(null, ['run_id' => $result->runId] + $result->summary());
        } catch (Throwable $exception) {
            Log::error('Loeschpipeline: Anfrage nicht auf erledigt gesetzt.', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
