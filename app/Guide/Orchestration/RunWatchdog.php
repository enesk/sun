<?php

declare(strict_types=1);

namespace App\Guide\Orchestration;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\TopicChainFactory;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\TopicRun;
use Illuminate\Support\Carbon;

/**
 * Erkennt haengende Laeufe eines Tenants und setzt sie neu an (#13).
 * Erwartet einen initialisierten Tenant-Kontext.
 *
 * Haengend: laenger als guide.schedule.stuck_after_minutes in einem
 * Zwischenstatus (probing, researching, writing, checking), gemessen an
 * updated_at — jeder Statuswechsel speichert den Lauf. Die Schwelle liegt
 * ueber dem Job-Timeout, ein arbeitender Job ist dann laengst beendet.
 *
 * Neu ansetzen heisst: den Job des aktuellen Status erneut einreihen
 * (TopicChainFactory), der Lauf bleibt derselbe. Ist die Unique-Sperre des
 * Jobs noch belegt, wartet er nur (zurueckgestellt wegen Budget, 429 oder
 * Parallelitaetsgrenze) und zaehlt nicht als haengend. Jeder Neuansatz
 * zaehlt den Alarm `run_stuck` hoch; nach guide.orchestrator.watchdog
 * .max_restarts Neuansaetzen endet der Lauf beim naechsten Haengen mit
 * failed und einem kritischen `run_stuck` (Sofort-Mail, #38 G1).
 *
 * Zusaetzlich: Alarm `failure_rate`, wenn mehr als
 * guide.orchestrator.failure_alert_ratio der heutigen Laeufe fehlschlugen.
 */
final class RunWatchdog
{
    public function __construct(
        private readonly TopicChainFactory $chains,
    ) {}

    /**
     * @return array{restarted: int, failed: int, waiting: int}
     */
    public function check(int $tenantId): array
    {
        $result = ['restarted' => 0, 'failed' => 0, 'waiting' => 0];
        $threshold = Carbon::now()->subMinutes(max(1, (int) config('guide.schedule.stuck_after_minutes', 20)));
        $maxRestarts = max(0, (int) config('guide.orchestrator.watchdog.max_restarts', 2));
        $forDate = Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();

        $stuck = TopicRun::query()
            ->inFlight()
            ->where('updated_at', '<', $threshold)
            ->orderBy('id')
            ->get();

        foreach ($stuck as $run) {
            $dedupeKey = "run_stuck:{$tenantId}:{$run->getKey()}";
            $restarts = (int) (GuideAlert::query()->where('dedupe_key', $dedupeKey)->open()->value('occurrences') ?? 0);

            if ($restarts >= $maxRestarts) {
                TopicChainFactory::failRun(
                    $tenantId,
                    (int) $run->getKey(),
                    "Lauf hing nach {$restarts} Neuansaetzen erneut in {$run->status->value}.",
                    self::class,
                );
                GuideAlert::query()->where('dedupe_key', $dedupeKey)->update(['status' => GuideAlert::STATUS_RESOLVED, 'resolved_at' => now()]);

                // Neustarts ausgeschoepft: jetzt kritisch, mit Sofort-Mail (#38 G1).
                GuideAlert::raise(
                    "{$dedupeKey}:failed",
                    GuideAlert::KEY_RUN_STUCK,
                    GuideAlert::LEVEL_CRITICAL,
                    "Ratgeber-Lauf {$run->getKey()} (Thema {$run->guide_topic_id}) hing nach {$restarts} Neustarts erneut in {$run->status->value} und wurde als fehlgeschlagen beendet.",
                    [
                        'tenant_id' => $tenantId,
                        'guide_topic_id' => $run->guide_topic_id,
                        'guide_topic_run_id' => $run->getKey(),
                        'for_date' => $forDate,
                        'context_json' => ['status' => $run->status->value, 'restarts' => $restarts],
                    ],
                );
                $result['failed']++;

                continue;
            }

            if (! $this->chains->dispatch($tenantId, $run)) {
                $result['waiting']++;

                continue;
            }

            // Frische Frist bis zur naechsten Pruefung.
            $run->touch();

            GuideAlert::raise(
                $dedupeKey,
                GuideAlert::KEY_RUN_STUCK,
                GuideAlert::LEVEL_WARNING,
                "Ratgeber-Lauf {$run->getKey()} (Thema {$run->guide_topic_id}) hing in {$run->status->value} und wurde neu angesetzt.",
                [
                    'tenant_id' => $tenantId,
                    'guide_topic_id' => $run->guide_topic_id,
                    'guide_topic_run_id' => $run->getKey(),
                    'for_date' => $forDate,
                    'context_json' => ['status' => $run->status->value, 'stuck_since' => $run->updated_at?->toIso8601String()],
                ],
            );
            $result['restarted']++;
        }

        $this->checkFailureRate($tenantId, $forDate);

        return $result;
    }

    /**
     * Ein Alarm je Tenant und Tag, sobald der Anteil fehlgeschlagener Laeufe
     * die Schwelle uebersteigt.
     */
    public function checkFailureRate(int $tenantId, string $date): void
    {
        $counts = TopicRun::query()
            ->whereDate('run_date', $date)
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as failed', [RunStatus::FAILED->value])
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $failed = (int) ($counts?->getAttribute('failed') ?? 0);
        $ratio = (float) config('guide.orchestrator.failure_alert_ratio', 0.10);

        if ($total === 0 || $failed / $total <= $ratio) {
            return;
        }

        $percent = (int) round($failed / $total * 100);

        GuideAlert::raise(
            "failure_rate:{$tenantId}:{$date}",
            GuideAlert::KEY_FAILURE_RATE,
            GuideAlert::LEVEL_CRITICAL,
            "{$failed} von {$total} Ratgeber-Laeufen fehlgeschlagen ({$percent} %).",
            [
                'tenant_id' => $tenantId,
                'for_date' => $date,
                'context_json' => ['failed' => $failed, 'total' => $total, 'ratio' => round($failed / $total, 4)],
            ],
        );
    }
}
