<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Jobs\GenerateDraftJob;
use App\Content\Jobs\PublishDraftJob;
use App\Content\Jobs\ScheduleAndPublishJob;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\PublishScheduler;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Sicherung der zeitgesteuerten Veroeffentlichung (#21).
 *
 * Laeuft alle fuenf Minuten und macht drei Dinge je Mandant:
 *
 *  1. Faellige Entwuerfe veroeffentlichen. Der Normalfall ist die
 *     Queue-Verzoegerung des ScheduleAndPublishJob; geht sie verloren
 *     (Neustart, Wartung, ausgefallener Worker), greift dieser Lauf.
 *  2. Freigegebene Entwuerfe ohne Slot einplanen.
 *  3. Reserve. Ist das Tagesziel bis 30 Minuten vor Fensterende nicht
 *     gedeckt, wird der beste Reserve-Kandidat des Tages generiert — solange
 *     Budget und Kandidaten reichen. Ohne Kandidat bleibt es bei einer
 *     Warnung im Log, aus der der Orchestrator (#22) seinen Alarm zieht.
 */
class ContentPublishDue extends Command
{
    protected $signature = 'content:publish:due
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--sync : Jobs sofort ausfuehren statt einzureihen}
        {--no-reserve : Keinen Reserve-Kandidaten nachziehen}';

    protected $description = 'Veroeffentlicht faellige Ratgeber, plant freigegebene ein und zieht Reserve nach';

    public function handle(PublishScheduler $scheduler): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            // Vor dem Go-Live ist kein Portal freigeschaltet (#26). Das ist
            // kein Fehler, sonst meldete der Scheduler jeden Tag Alarm.
            if ($this->option('tenant') === null) {
                $this->warn('Kein Portal ist für die Content-Pipeline freigeschaltet (content:rollout).');

                return self::SUCCESS;
            }

            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            [$due, $toSchedule, $reserve] = $tenant->run(function () use ($tenant, $scheduler): array {
                $settings = TenantContentSetting::current();

                return [
                    ArticleDraft::query()->dueForPublishing()->orderBy('scheduled_for')->pluck('id')->map('intval')->all(),
                    ArticleDraft::query()
                        ->notWithdrawn()
                        ->withStatus(DraftStatus::APPROVED)
                        ->orderBy('id')
                        ->pluck('id')
                        ->map('intval')
                        ->all(),
                    $this->reserveTopicId($tenant, $scheduler, $settings),
                ];
            });

            foreach ($due as $draftId) {
                $sync
                    ? PublishDraftJob::dispatchSync((int) $tenant->getKey(), $draftId)
                    : PublishDraftJob::dispatch((int) $tenant->getKey(), $draftId);
            }

            foreach ($toSchedule as $draftId) {
                $sync
                    ? ScheduleAndPublishJob::dispatchSync((int) $tenant->getKey(), $draftId)
                    : ScheduleAndPublishJob::dispatch((int) $tenant->getKey(), $draftId);
            }

            if ($reserve !== null) {
                $sync
                    ? GenerateDraftJob::dispatchSync((int) $tenant->getKey(), null, $reserve)
                    : GenerateDraftJob::dispatch((int) $tenant->getKey(), null, $reserve);
            }

            if ($due !== [] || $toSchedule !== [] || $reserve !== null) {
                $this->line(sprintf(
                    '[%s] faellig: %d, einzuplanen: %d, Reserve: %s',
                    $tenant->name,
                    count($due),
                    count($toSchedule),
                    $reserve === null ? '-' : (string) $reserve,
                ));
            }
        }

        return self::SUCCESS;
    }

    /**
     * Der Reserve-Kandidat, der einen unbelegten Slot noch retten kann.
     * Rueckgabe null, wenn die Frist nicht erreicht ist, das Tagesziel schon
     * gedeckt ist oder es keinen Kandidaten gibt.
     *
     * Laeuft im Tenant-Kontext.
     */
    private function reserveTopicId(Tenant $tenant, PublishScheduler $scheduler, TenantContentSetting $settings): ?int
    {
        if ($this->option('no-reserve') || ! $scheduler->needsReserve($tenant, null, $settings)) {
            return null;
        }

        // Ein Entwurf, der gerade entsteht oder auf Freigabe wartet, deckt
        // den Slot bereits — dann braucht es keinen zweiten Anlauf.
        $inFlight = ArticleDraft::query()
            ->notWithdrawn()
            ->whereIn('status', [
                DraftStatus::GENERATING->value,
                DraftStatus::GENERATED->value,
                DraftStatus::CHECKING->value,
                DraftStatus::APPROVED->value,
            ])
            ->whereDate('created_at', today())
            ->exists();

        if ($inFlight) {
            return null;
        }

        $topic = TopicCandidate::query()->reserveFor(today())->first();

        if ($topic === null) {
            $this->warn("[{$tenant->name}] Tagesziel nicht gedeckt und kein Reserve-Kandidat vorhanden.");

            return null;
        }

        $topic->forceFill([
            'status' => TopicStatus::SELECTED->value,
            'selected_for_date' => today()->toDateString(),
        ])->save();

        return (int) $topic->getKey();
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            // Ohne --tenant arbeitet der Befehl nur auf freigeschalteten
            // Portalen (#26). Ein einzeln benanntes Portal laeuft weiter,
            // damit sich ein Portal vor dem Go-Live pruefen laesst.
            return app(TenantRollout::class)->activeTenants();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
