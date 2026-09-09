<?php

declare(strict_types=1);

namespace App\Content\Orchestration;

use App\Content\Enums\DraftStatus;
use App\Content\Jobs\GenerateDraftJob;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\ContentAlert;
use App\Content\Models\TenantContentSetting;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wachhund ueber den Tages-Slots (#22).
 *
 * Laeuft alle 30 Minuten und stellt je Mandant genau eine Frage: traegt
 * jeder Slot des Tages einen Entwurf, der noch werden kann? Fehlt einer,
 * zieht der Wachhund den naechsten Reserve-Kandidaten. Nach
 * `content.pipeline.watchdog.max_attempts` Versuchen fuer denselben Slot
 * gibt er auf und meldet einen Alarm — weitere Anlaeufe wuerden nur Budget
 * verbrennen.
 *
 * Er greift erst nach dem Generatorlauf (plus Karenz): davor ist ein leerer
 * Slot der Normalzustand und kein Missstand.
 */
final class SlotWatchdog
{
    public function __construct(private readonly TenantRollout $rollout) {}

    /**
     * Alle freigeschalteten Mandanten pruefen (#26). Ein Ausfall eines
     * Mandanten stoppt die uebrigen nicht.
     *
     * @return array<int, array<string, mixed>> je Mandant ein Ergebnis
     */
    public function sweep(?CarbonImmutable $date = null, bool $sync = false, bool $force = false): array
    {
        $results = [];

        foreach ($this->rollout->activeTenants() as $tenant) {
            try {
                $results[] = $this->check($tenant, $date, $sync, $force);
            } catch (Throwable $exception) {
                Log::error('Slot-Wachhund: Mandant uebersprungen.', [
                    'tenant_id' => $tenant->getKey(),
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Ein Mandant.
     *
     * @return array{tenant: string, checked: bool, covered: array<int, int>, retried: array<int, int>, exhausted: array<int, int>}
     */
    public function check(Tenant $tenant, ?CarbonImmutable $date = null, bool $sync = false, bool $force = false): array
    {
        $date = ($date ?? CarbonImmutable::today())->startOfDay();
        $tenantId = (int) $tenant->getKey();
        $result = [
            'tenant' => (string) $tenant->name,
            'checked' => false,
            'covered' => [],
            'retried' => [],
            'exhausted' => [],
        ];

        if (! $force && ! $this->windowOpen($date)) {
            return $result;
        }

        // Ein nicht freigeschaltetes Portal (#26) hat keine Slots zu decken.
        if (! $this->rollout->isActive($tenant)) {
            return $result;
        }

        $result['checked'] = true;
        $maxAttempts = max(1, (int) config('content.pipeline.watchdog.max_attempts', 3));

        /** @var array{target: int, covered: array<int, int>, attempts: array<int, int>} $state */
        $state = $tenant->run(function () use ($date): array {
            $settings = TenantContentSetting::current();

            return [
                'target' => max(1, (int) ($settings->articles_per_day ?? config('content.targets.articles_per_tenant_per_day', 2))),
                'covered' => self::coveredSlots($date),
                'attempts' => self::attemptsPerSlot($date),
            ];
        });

        $result['covered'] = $state['covered'];

        for ($slot = 1; $slot <= $state['target']; $slot++) {
            if (in_array($slot, $state['covered'], true)) {
                ContentAlert::settle(ContentAlert::KEY_SLOT_EXHAUSTED, $tenantId, $date, $slot);
                ContentAlert::settle(ContentAlert::KEY_NO_TOPICS, $tenantId, $date, $slot);

                continue;
            }

            $attempts = $state['attempts'][$slot] ?? 0;

            if ($attempts >= $maxAttempts) {
                $result['exhausted'][] = $slot;
                $this->exhausted($tenant, $date, $slot, $attempts);

                continue;
            }

            $topicId = $tenant->run(static fn (): ?int => ContentDailyOrchestrator::claimReserveTopic($date));

            if ($topicId === null) {
                $result['exhausted'][] = $slot;
                $this->exhausted($tenant, $date, $slot, $attempts, noReserve: true);

                continue;
            }

            $sync
                ? GenerateDraftJob::dispatchSync($tenantId, null, $topicId, $slot, $attempts + 1)
                : GenerateDraftJob::dispatch($tenantId, null, $topicId, $slot, $attempts + 1);

            $result['retried'][] = $slot;

            Log::info('Slot-Wachhund: Reserve-Kandidat gezogen.', [
                'tenant_id' => $tenantId,
                'date' => $date->toDateString(),
                'slot' => $slot,
                'attempt' => $attempts + 1,
                'topic_id' => $topicId,
            ]);
        }

        return $result;
    }

    /**
     * Der Slot ist verloren: Alarm fuer Uebersicht, Tagesbericht und Mail.
     */
    private function exhausted(Tenant $tenant, CarbonImmutable $date, int $slot, int $attempts, bool $noReserve = false): void
    {
        ContentAlert::raise(
            ContentAlert::KEY_SLOT_EXHAUSTED,
            $noReserve
                ? __('Für :portal fehlt der :slot. Artikel des Tages — es gibt keinen Reserve-Kandidaten mehr.', [
                    'portal' => (string) $tenant->name,
                    'slot' => $slot,
                ])
                : __('Für :portal ist der :slot. Artikel des Tages nach :count Versuchen nicht zustande gekommen.', [
                    'portal' => (string) $tenant->name,
                    'slot' => $slot,
                    'count' => $attempts,
                ]),
            (int) $tenant->getKey(),
            ContentAlert::LEVEL_CRITICAL,
            ['slot' => $slot, 'attempts' => $attempts, 'no_reserve' => $noReserve],
            $date,
            $slot,
        );
    }

    /**
     * Vor dem Generatorlauf plus Karenz ist ein leerer Slot normal. Fuer
     * vergangene Tage (Nachholen) ist das Fenster immer offen.
     */
    private function windowOpen(CarbonImmutable $date): bool
    {
        if ($date->lessThan(CarbonImmutable::today())) {
            return true;
        }

        if ($date->greaterThan(CarbonImmutable::today())) {
            return false;
        }

        $generateAt = (string) config('content.pipeline.schedule.generate_at', '03:30');
        $grace = max(0, (int) config('content.pipeline.watchdog.grace_minutes', 45));

        return CarbonImmutable::now()->greaterThanOrEqualTo(
            CarbonImmutable::parse($date->toDateString().' '.$generateAt)->addMinutes($grace),
        );
    }

    /**
     * Die Slots, fuer die es heute einen Entwurf gibt, der noch werden kann.
     * Laeuft im Tenant-Kontext.
     *
     * @return array<int, int>
     */
    public static function coveredSlots(CarbonImmutable $date): array
    {
        $covered = [];

        foreach (self::draftsBySlot($date) as $slot => $drafts) {
            foreach ($drafts as $draft) {
                if ($draft->status !== DraftStatus::FAILED) {
                    $covered[] = $slot;

                    break;
                }
            }
        }

        sort($covered);

        return $covered;
    }

    /**
     * Zahl der Anlaeufe je Slot — auch die gescheiterten zaehlen, sonst
     * liefe der Wachhund endlos gegen denselben Slot.
     * Laeuft im Tenant-Kontext.
     *
     * @return array<int, int>
     */
    public static function attemptsPerSlot(CarbonImmutable $date): array
    {
        return array_map('count', self::draftsBySlot($date));
    }

    /**
     * Entwuerfe des Tages nach Slot. Der Slot steht im Qualitaetsbericht
     * (`generation.slot`), den der Generator schreibt; aeltere Entwuerfe
     * ohne Eintrag werden in Anlagereihenfolge zugeordnet.
     * Laeuft im Tenant-Kontext.
     *
     * @return array<int, array<int, ArticleDraft>>
     */
    private static function draftsBySlot(CarbonImmutable $date): array
    {
        $bySlot = [];
        $fallback = 1;

        foreach (ContentDailyOrchestrator::draftsOfDay($date) as $draft) {
            $slot = (int) Arr::get((array) $draft->quality_report_json, 'generation.slot', 0);

            if ($slot < 1) {
                $slot = $fallback++;
            }

            $bySlot[$slot][] = $draft;
        }

        ksort($bySlot);

        return $bySlot;
    }
}
