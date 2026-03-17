<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\AdSlotImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkAdSlotImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly array $importData,
        public readonly Tenant $tenant,
        public readonly string $mode = 'add',
        public readonly string $conflictStrategy = 'skip',
    ) {}

    public function handle(AdSlotImportService $importService): void
    {
        $result = $importService->import(
            $this->importData,
            $this->tenant,
            $this->mode,
            $this->conflictStrategy,
        );

        Log::info("Ad-Slot Import für Tenant \"{$this->tenant->name}\" abgeschlossen", [
            'tenant_uuid' => $this->tenant->uuid,
            'mode' => $this->mode,
            'conflict_strategy' => $this->conflictStrategy,
            'imported' => $result->imported,
            'skipped' => $result->skipped,
            'updated' => $result->updated,
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function shouldRetry(\Throwable $exception): bool
    {
        return $exception instanceof QueryException;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Ad-Slot Import für Tenant \"{$this->tenant->name}\" endgültig fehlgeschlagen nach {$this->tries} Versuchen", [
            'tenant_uuid' => $this->tenant->uuid,
            'mode' => $this->mode,
            'conflict_strategy' => $this->conflictStrategy,
            'error' => $exception->getMessage(),
        ]);
    }
}
