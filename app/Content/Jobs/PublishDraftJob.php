<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Models\ArticleDraft;
use App\Content\Services\Publisher;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Der eigentliche Livegang eines Entwurfs (#21).
 *
 * Der Job wird zweimal angestossen: vom ScheduleAndPublishJob mit
 * Verzoegerung bis zum Slot und, falls diese Verzoegerung verloren ging
 * (Neustart der Queue, Wartung), von der Sicherung `content:publish:due`.
 * Deshalb ist er absichtlich idempotent — ein bereits veroeffentlichter
 * Entwurf laeuft im Publisher ins Leere.
 */
class PublishDraftJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Der Livegang selbst ist eine Datenbankoperation; scheitert sie, hilft
     * ein zweiter Versuch. Alles Externe (IndexNow, Sitemap) faengt der
     * Publisher bereits ab.
     */
    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public int $draftId,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.publish', 'content-publish'));
    }

    public function uniqueId(): string
    {
        return "content-publish-draft:{$this->tenantId}:{$this->draftId}";
    }

    /**
     * Die Sperre gilt nur bis zur Ausfuehrung. Sie ist kurz genug, dass ein
     * neuer Anlauf am Folgetag nicht blockiert wird.
     */
    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(Publisher $publisher): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $publisher): void {
            $draft = ArticleDraft::query()->find($this->draftId);

            if ($draft === null) {
                Log::warning('Veroeffentlichung ohne Entwurf.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $this->draftId,
                ]);

                return;
            }

            $publisher->publish($tenant, $draft);
        });
    }
}
