<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Sources\SourceRegistry;
use App\Content\Sources\SourceRunner;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fuehrt genau einen Quell-Connector fuer genau einen Mandanten aus (#7).
 *
 * Ein Job je Connector und Mandant: faellt einer aus, laufen die uebrigen
 * weiter. Wiederholungen macht der Provider-Layer, nicht die Queue
 * ($tries = 1); der SourceRunner haelt den Fehler in provider_states fest.
 */
class RunSourceConnectorJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public string $connectorKey,
    ) {
        $this->timeout = (int) config('content.sources.job_timeout', 300);
        $this->onQueue((string) config('content.sources.queue', 'content-sources'));
    }

    public function uniqueId(): string
    {
        return "content-source:{$this->tenantId}:{$this->connectorKey}";
    }

    public function uniqueFor(): int
    {
        return (int) config('content.sources.job_timeout', 300);
    }

    public function handle(SourceRegistry $registry, SourceRunner $runner): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            Log::warning('Quell-Connector uebersprungen: Mandant nicht gefunden.', [
                'tenant_id' => $this->tenantId,
                'connector' => $this->connectorKey,
            ]);

            return;
        }

        if (! $registry->has($this->connectorKey)) {
            Log::warning('Quell-Connector uebersprungen: nicht registriert.', [
                'connector' => $this->connectorKey,
            ]);

            return;
        }

        $result = $runner->run($tenant, $registry->get($this->connectorKey));

        Log::info('Quell-Connector gelaufen.', [
            'connector' => $this->connectorKey,
            'tenant_id' => $this->tenantId,
            'result' => $result->summary(),
        ]);
    }
}
