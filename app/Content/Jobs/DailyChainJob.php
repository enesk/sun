<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Models\Central\ContentAlert;
use App\Content\Orchestration\ContentDailyOrchestrator;
use App\Content\Orchestration\SlotWatchdog;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Eine Stufe der Tageskette fuer genau einen Mandanten (#22).
 *
 * Der Scheduler reiht je Uhrzeit einen dieser Jobs pro Mandant ein. Das ist
 * die Stelle, an der die Mandanten voneinander unabhaengig werden: faellt
 * ein Portal aus, scheitert nur sein Job, bekommt einen Alarm und die
 * uebrigen 19 laufen weiter.
 *
 * Der Job selbst arbeitet nichts ab, er stoesst an — die Arbeit steckt in
 * den Fachjobs auf ihren eigenen Queues.
 */
class DailyChainJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Ein Fehlversuch kostet einen Slot, kein Queue-Retry: die Nacharbeit
     * macht der Wachhund mit einem Reserve-Kandidaten.
     */
    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public string $stage,
        public ?string $forDate = null,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.discovery', 'content-discovery'));
    }

    public function uniqueId(): string
    {
        return "content-daily-chain:{$this->tenantId}:{$this->stage}:".($this->forDate ?? 'today');
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(ContentDailyOrchestrator $orchestrator, SlotWatchdog $watchdog): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $date = $this->date();

        if ($this->stage === self::stageWatchdog()) {
            $watchdog->check($tenant, $date);

            return;
        }

        $message = $orchestrator->runStage($tenant, $this->stage, $date);

        Log::info('Tageskette: Stufe angestossen.', [
            'tenant_id' => $this->tenantId,
            'stage' => $this->stage,
            'date' => $date->toDateString(),
            'message' => $message,
        ]);
    }

    /**
     * Der Ausfall einer Stufe ist ein Alarm, kein stiller Fehler — und er
     * ruft den Wachhund, damit der Tag noch zu retten ist.
     */
    public function failed(Throwable $exception): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        ContentAlert::raise(
            ContentAlert::KEY_CHAIN_FAILED,
            __('Die Stufe „:stage" ist für :portal abgebrochen.', [
                'stage' => $this->stage,
                'portal' => (string) ($tenant?->name ?? $this->tenantId),
            ]),
            $this->tenantId,
            ContentAlert::LEVEL_CRITICAL,
            ['stage' => $this->stage, 'exception' => $exception->getMessage()],
            $this->date(),
        );

        if ($tenant === null || $this->stage === self::stageWatchdog()) {
            return;
        }

        try {
            app(SlotWatchdog::class)->check($tenant, $this->date());
        } catch (Throwable $watchdogException) {
            Log::error('Tageskette: Wachhund nach Fehlschlag nicht erreichbar.', [
                'tenant_id' => $this->tenantId,
                'stage' => $this->stage,
                'exception' => $watchdogException->getMessage(),
            ]);
        }
    }

    /**
     * Der Wachhund ist keine Stufe der Kette, laeuft aber ueber denselben
     * Job — eine Einreihstelle je Mandant.
     */
    public static function stageWatchdog(): string
    {
        return 'watchdog';
    }

    private function date(): CarbonImmutable
    {
        return $this->forDate !== null
            ? CarbonImmutable::parse($this->forDate)->startOfDay()
            : CarbonImmutable::today();
    }
}
