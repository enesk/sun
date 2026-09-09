<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\DraftStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Services\PublishScheduler;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Einplanung freigegebener Entwuerfe (#21).
 *
 * Letzter Job der Tageskette: er sucht dem Entwurf einen Slot im
 * Veroeffentlichungsfenster des Mandanten (PublishScheduler) und reiht den
 * PublishDraftJob mit genau der Verzoegerung ein, die bis dahin bleibt.
 *
 * Der Job plant nur ein — veroeffentlicht wird ausschliesslich im
 * PublishDraftJob. Damit gibt es einen einzigen Weg in `posts`, egal ob der
 * Zeitpunkt ueber die Queue-Verzoegerung oder ueber die
 * Scheduler-Sicherung (content:publish:due) erreicht wird.
 *
 * Ohne `draftId` werden alle freigegebenen Entwuerfe des Mandanten geplant;
 * so ruft ihn der Tages-Orchestrator (#22) auf.
 */
class ScheduleAndPublishJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $tenantId,
        public ?int $draftId = null,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.publish', 'content-publish'));
    }

    public function uniqueId(): string
    {
        return "content-schedule-publish:{$this->tenantId}:".($this->draftId ?? 'all');
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(PublishScheduler $scheduler): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $scheduler): void {
            foreach ($this->drafts() as $draft) {
                $this->schedule($tenant, $draft, $scheduler);
            }
        });
    }

    /**
     * @return iterable<int, ArticleDraft>
     */
    private function drafts(): iterable
    {
        $query = ArticleDraft::query()
            ->notWithdrawn()
            ->whereIn('status', [DraftStatus::APPROVED->value, DraftStatus::SCHEDULED->value])
            ->orderBy('id');

        if ($this->draftId !== null) {
            return $query->whereKey($this->draftId)->get();
        }

        // Bereits eingeplante Entwuerfe brauchen keinen zweiten Slot; sie
        // holt bei einem verpassten Zeitpunkt die Sicherung ab.
        return $query->where('status', DraftStatus::APPROVED->value)->limit(20)->get();
    }

    private function schedule(Tenant $tenant, ArticleDraft $draft, PublishScheduler $scheduler): void
    {
        if ($draft->status === DraftStatus::SCHEDULED && $draft->scheduled_for !== null) {
            $this->dispatchPublish($draft, $draft->scheduled_for);

            return;
        }

        $slot = PublishScheduler::toCarbon($scheduler->nextFreeSlot($tenant, $draft));

        $draft->forceFill(['scheduled_for' => $slot])->save();

        if ($draft->status === DraftStatus::APPROVED && ! $draft->transitionTo(DraftStatus::SCHEDULED)) {
            Log::warning('Entwurf liess sich nicht auf „eingeplant" setzen.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'status' => $draft->status->value,
            ]);

            return;
        }

        Log::info('Ratgeber eingeplant.', [
            'tenant_id' => $this->tenantId,
            'draft_id' => $draft->getKey(),
            'scheduled_for' => $slot->toDateTimeString(),
        ]);

        $this->dispatchPublish($draft, $slot);
    }

    /**
     * Reiht die Veroeffentlichung mit Verzoegerung bis zum Slot ein. Liegt
     * der Zeitpunkt in der Vergangenheit (nachtraeglich freigegebener
     * Entwurf), laeuft sie sofort.
     */
    private function dispatchPublish(ArticleDraft $draft, \DateTimeInterface $slot): void
    {
        $moment = Carbon::parse($slot);
        $job = PublishDraftJob::dispatch($this->tenantId, (int) $draft->getKey());

        if ($moment->isFuture()) {
            $job->delay($moment);
        }
    }
}
