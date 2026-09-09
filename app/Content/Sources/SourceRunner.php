<?php

declare(strict_types=1);

namespace App\Content\Sources;

use App\Content\Enums\SourceFrequency;
use App\Content\Events\ProviderAlarmRaised;
use App\Content\Models\Central\ProviderState;
use App\Content\Models\SourceItem;
use App\Content\Sources\Contracts\SourceConnector;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fuehrt einen Connector fuer einen Mandanten aus, dedupliziert das Ergebnis
 * und schreibt es als source_items (#7).
 *
 * Fehler eines Connectors werden hier abgefangen und in provider_states
 * festgehalten. Der Runner wirft nichts weiter nach oben, damit ein kaputter
 * Connector die uebrigen nicht blockiert.
 */
final class SourceRunner
{
    public function __construct(
        private readonly SourceRegistry $registry,
    ) {}

    public function run(Tenant $tenant, SourceConnector $connector): SourceRunResult
    {
        $tenantId = (int) $tenant->getKey();
        $key = $connector->key();

        // Zuerst die Entscheidung der Redaktion (#55), erst danach der
        // Zustand des Providers: eine abgeschaltete Quelle ist kein Fehler,
        // sie bekommt keinen Fehlerzaehler und keinen Alarm.
        if (! $this->registry->isEnabled($key)) {
            return SourceRunResult::skipped($key, $tenantId, __('In den Einstellungen abgeschaltet'));
        }

        $state = $this->state($key);

        if (! $state->isAvailable()) {
            return SourceRunResult::skipped($key, $tenantId, "Provider-Status '{$state->status}'");
        }

        try {
            $result = $tenant->run(fn () => $this->fetchAndStore($connector, TenantContext::forTenant($tenant), $tenantId));

            $this->recordSuccess($state);
            $this->markRan($key, $tenantId);

            return $result;
        } catch (Throwable $exception) {
            $this->recordFailure($state, $exception, $tenantId);
            $this->markRan($key, $tenantId);

            Log::error('Quell-Connector gescheitert.', [
                'connector' => $key,
                'tenant_id' => $tenantId,
                'exception' => $exception->getMessage(),
            ]);

            return SourceRunResult::failure($key, $tenantId, $exception->getMessage());
        }
    }

    /**
     * Ist der Connector fuer diesen Mandanten gemaess seiner Frequenz faellig?
     */
    public function isDue(Tenant $tenant, SourceConnector $connector, ?SourceFrequency $frequency = null): bool
    {
        $state = ProviderState::query()->where('provider', $connector->key())->first();
        $meta = $this->meta($state);
        $lastRun = $meta['last_run_at'][(string) $tenant->getKey()] ?? null;

        if (! is_string($lastRun)) {
            return true;
        }

        $frequency ??= $this->registry->frequencyOf($connector);

        return CarbonImmutable::parse($lastRun)
            ->addMinutes($frequency->minimumIntervalMinutes())
            ->isPast();
    }

    /**
     * Laeuft bereits im Tenant-Kontext.
     */
    private function fetchAndStore(SourceConnector $connector, TenantContext $context, int $tenantId): SourceRunResult
    {
        $fetchedAt = CarbonImmutable::now();
        $items = $connector->fetch($context);

        $seen = [];
        $stored = 0;
        $duplicates = 0;
        $fetched = 0;

        foreach ($items as $item) {
            if (! $item instanceof SourceItemDto || ! $item->isValid()) {
                continue;
            }

            $fetched++;
            $fingerprint = $item->fingerprint();

            // Doppelte innerhalb desselben Laufs gar nicht erst schreiben.
            if (isset($seen[$fingerprint])) {
                $duplicates++;

                continue;
            }

            $seen[$fingerprint] = true;

            $record = SourceItem::query()->firstOrNew([
                'source_key' => $connector->key(),
                'fingerprint' => $fingerprint,
            ]);

            $existed = $record->exists;

            $record->fill($item->toAttributes($connector->key(), $fetchedAt));
            $record->save();

            $existed ? $duplicates++ : $stored++;
        }

        return SourceRunResult::success($connector->key(), $tenantId, $fetched, $stored, $duplicates);
    }

    private function state(string $provider): ProviderState
    {
        return ProviderState::query()->firstOrCreate(
            ['provider' => $provider],
            ['status' => ProviderState::STATUS_OK],
        );
    }

    private function recordSuccess(ProviderState $state): void
    {
        $meta = $this->meta($state);
        unset($meta['alarm_raised_at']);

        $state->forceFill([
            'status' => ProviderState::STATUS_OK,
            'consecutive_failures' => 0,
            'last_success_at' => now(),
            'last_error' => null,
            'circuit_open_until' => null,
            'meta_json' => $meta,
        ])->save();
    }

    private function recordFailure(ProviderState $state, Throwable $exception, int $tenantId): void
    {
        // Frisch lesen: derselbe Connector kann parallel fuer mehrere
        // Mandanten laufen, der Fehlerzaehler muss trotzdem stimmen.
        $state->refresh();

        $failures = $state->consecutive_failures + 1;
        $alarmThreshold = (int) config('content.sources.alarm_after_failures', 3);
        $openThreshold = (int) config('content.resilience.circuit_breaker.failure_threshold', 5);

        $meta = $this->meta($state);
        $meta['last_failed_tenant_id'] = $tenantId;

        $raisesAlarm = $failures >= $alarmThreshold && ! isset($meta['alarm_raised_at']);

        if ($raisesAlarm) {
            $meta['alarm_raised_at'] = now()->toIso8601String();
        }

        $state->forceFill([
            'status' => $failures >= $openThreshold ? ProviderState::STATUS_OPEN : ProviderState::STATUS_DEGRADED,
            'consecutive_failures' => $failures,
            'last_failure_at' => now(),
            'last_error' => Str::limit($exception->getMessage(), 500, ''),
            'circuit_open_until' => $failures >= $openThreshold
                ? now()->addSeconds((int) config('content.resilience.circuit_breaker.open_seconds', 900))
                : null,
            'meta_json' => $meta,
        ])->save();

        if ($raisesAlarm) {
            ProviderAlarmRaised::dispatch($state->provider, $failures, $state->last_error, $tenantId);
        }
    }

    /**
     * Haelt den letzten Lauf je Mandant in provider_states.meta_json fest.
     *
     * Bewusst in der Central-DB und nicht im Cache: der Dateicache haengt in
     * diesem Projekt am mandantenspezifischen storage_path und waere damit
     * je nach Kontext ein anderer. Der Schreibzugriff laeuft gesperrt, weil
     * mehrere Tenant-Jobs desselben Connectors parallel laufen koennen.
     */
    private function markRan(string $connectorKey, int $tenantId): void
    {
        DB::connection($this->connection())->transaction(function () use ($connectorKey, $tenantId): void {
            $state = ProviderState::query()
                ->where('provider', $connectorKey)
                ->lockForUpdate()
                ->first();

            if ($state === null) {
                return;
            }

            $meta = $this->meta($state);
            $meta['last_run_at'][(string) $tenantId] = now()->toIso8601String();

            $state->forceFill(['meta_json' => $meta])->save();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(?ProviderState $state): array
    {
        return is_array($state?->meta_json) ? $state->meta_json : [];
    }

    private function connection(): string
    {
        return (new ProviderState)->getConnectionName() ?? config('database.default');
    }
}
