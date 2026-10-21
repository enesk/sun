<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

use App\Companies\Deletion\Models\DeletionLogEntry;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Models\RequestEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Schreibt das Protokoll der Loeschpipeline (#8) — in `deletion_log` der
 * Tenant-DB und, wenn der Lauf zu einer Anfrage gehoert, zusaetzlich in
 * `request_events` der Central-DB.
 *
 * Zwei Orte mit Absicht: `deletion_log` ist die technische Spur am Portal
 * (jeder Schritt, Dauer, Fehlertext) und bleibt dort, auch wenn es nie eine
 * Anfrage gab (Artisan, Panel). `request_events` ist die fachliche Spur des
 * Vorgangs ueber alle Portale hinweg und wird in der Inbox (#7) angezeigt.
 *
 * Das Protokoll darf den Lauf nie aufhalten: scheitert das Schreiben, geht
 * eine Logzeile raus und die Pipeline laeuft weiter. Andernfalls koennte ein
 * fehlendes Protokoll dazu fuehren, dass eine Loeschung nicht stattfindet —
 * genau das falsche Ergebnis.
 */
class DeletionLogger
{
    public function step(
        DeletionContext $context,
        string $step,
        StepResult $result,
        CarbonImmutable $startedAt,
        CarbonImmutable $finishedAt,
    ): void {
        if ($context->dryRun) {
            return;
        }

        $payload = $result->payload;

        $this->writeTenantLog($context, [
            'run_id' => $context->runId,
            'company_id' => $context->company->getKey(),
            'company_name' => $context->company->name,
            'company_path' => $context->primaryPath(),
            'request_id' => $context->requestId(),
            'step' => $step,
            'status' => $result->status->value,
            'message' => $result->message,
            'payload' => $payload === [] ? null : $payload,
            'duration_ms' => (int) round(($finishedAt->getTimestampMs() - $startedAt->getTimestampMs())),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'created_at' => $finishedAt,
        ]);

        $this->writeRequestEvent($context, [
            'step' => $step,
            'status' => $result->status->value,
            'message' => $result->message,
            'run_id' => $context->runId,
        ] + $payload);
    }

    /**
     * Abschlusszeile des Laufs — das, was in der Inbox als „erledigt" steht.
     *
     * @param  array<string, mixed>  $summary
     */
    public function finished(DeletionContext $context, array $summary): void
    {
        if ($context->dryRun) {
            return;
        }

        $now = CarbonImmutable::now();

        $this->writeTenantLog($context, [
            'run_id' => $context->runId,
            'company_id' => $context->company->getKey(),
            'company_name' => $context->company->name,
            'company_path' => $context->primaryPath(),
            'request_id' => $context->requestId(),
            'step' => 'run',
            'status' => ($summary['failed'] ?? 0) > 0 ? StepStatus::FAILED->value : StepStatus::OK->value,
            'message' => $summary['message'] ?? null,
            'payload' => $summary,
            'duration_ms' => $summary['duration_ms'] ?? null,
            'started_at' => $summary['started_at'] ?? null,
            'finished_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function writeTenantLog(DeletionContext $context, array $attributes): void
    {
        try {
            if (! Schema::hasTable('deletion_log')) {
                Log::warning('Loeschpipeline: deletion_log fehlt, tenants:migrate ausstehend.', [
                    'tenant_id' => $context->tenant->getKey(),
                ]);

                return;
            }

            DeletionLogEntry::query()->create($attributes);
        } catch (Throwable $exception) {
            Log::error('Loeschpipeline: Protokollzeile nicht geschrieben.', [
                'tenant_id' => $context->tenant->getKey(),
                'company_id' => $context->company->getKey(),
                'step' => $attributes['step'] ?? null,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Die Anfrage liegt in der Central-DB. Innerhalb von `Tenant::run()` ist
     * die Standardverbindung die Tenant-DB — `ProfileRequest` und
     * `RequestEvent` tragen deshalb `CentralConnection` und greifen
     * ausdruecklich auf `central` zu.
     *
     * @param  array<string, mixed>  $payload
     */
    private function writeRequestEvent(DeletionContext $context, array $payload): void
    {
        $request = $context->request;

        if ($request === null) {
            return;
        }

        try {
            $request->recordEvent(RequestEventType::NOTE, $payload, RequestEvent::ACTOR_SYSTEM);
        } catch (Throwable $exception) {
            Log::error('Loeschpipeline: request_event nicht geschrieben.', [
                'request_id' => $request->getKey(),
                'step' => $payload['step'] ?? null,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
