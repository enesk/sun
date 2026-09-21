<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\TopicRun;
use App\Guide\Quality\QualityGate;
use App\Models\Tenant;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Stoesst die Kette eines Laufs an bzw. setzt sie fort (#13).
 *
 * Der Einstieg haengt am Status, nicht am Anlass: ein neuer Lauf beginnt
 * mit der Probe (update) oder der Tiefenrecherche (create), ein
 * unterbrochener Lauf mit dem Job seines aktuellen Zwischenstatus. So legt
 * ein erneuter Tageslauf, `guide:run --force` oder der Watchdog nie einen
 * zweiten Lauf an, sondern setzt den bestehenden fort.
 *
 * Verdrahtung: Bus::chain() mit ->catch(), das den Lauf auf failed setzt,
 * wenn der Job endgueltig scheitert (Timeout, retryUntil abgelaufen). Die
 * Folgeglieder stoesst weiterhin jeder Job selbst bzw. sein Listener an
 * (docs/guide-system.md §2) — die Kette verzweigt nach der Probe und nach
 * dem Qualitaetsgate, das laesst sich nicht vorab als feste Liste bauen.
 * Haengt ein spaeteres Glied, faengt es der RunWatchdog.
 *
 * Doppelte Jobs verhindert die Unique-Sperre des Jobs (ShouldBeUnique):
 * Ist sie belegt, wartet oder arbeitet der Job bereits, und es wird nichts
 * eingereiht. Aufruf im Tenant-Kontext, damit Sperre und Job-Payload am
 * selben Mandanten haengen wie bei den Listenern.
 */
class TopicChainFactory
{
    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * Job fuer den aktuellen Stand des Laufs; null, wenn nichts anzustossen
     * ist (terminal oder wartet in review auf das Dashboard).
     */
    public function jobFor(int $tenantId, TopicRun $run): ?object
    {
        $runId = (int) $run->getKey();

        return match ($run->status) {
            RunStatus::QUEUED => $run->mode === RunMode::UPDATE
                ? new FreshnessProbeJob($tenantId, $runId)
                : new DeepResearchJob($tenantId, $runId),
            RunStatus::PROBING => new FreshnessProbeJob($tenantId, $runId),
            RunStatus::RESEARCHING => new DeepResearchJob($tenantId, $runId),
            RunStatus::WRITING => $this->writingJob($tenantId, $run),
            RunStatus::CHECKING => $this->checkingJob($tenantId, $run),
            RunStatus::REVIEW, RunStatus::PUBLISHED, RunStatus::UNCHANGED, RunStatus::FAILED => null,
        };
    }

    /**
     * Reiht den passenden Job ein, optional verzoegert (Staffelung).
     *
     * @return bool true = eingereiht; false = nichts zu tun oder der Job
     *              wartet bzw. arbeitet bereits
     */
    public function dispatch(int $tenantId, TopicRun $run, int $delaySeconds = 0): bool
    {
        $job = $this->jobFor($tenantId, $run);

        if ($job === null) {
            return false;
        }

        if ($delaySeconds > 0) {
            $job->delay($delaySeconds);
        }

        // Vor dem Einreihen belegen: PendingChain prueft ShouldBeUnique nicht
        // selbst. Freigegeben wird die Sperre wie gewohnt vom Worker.
        if (! (new UniqueLock($this->cache))->acquire($job)) {
            return false;
        }

        $runId = (int) $run->getKey();

        Bus::chain([$job])
            ->catch(static function (Throwable $exception) use ($tenantId, $runId): void {
                TopicChainFactory::failRun($tenantId, $runId, $exception->getMessage(), $exception::class);
            })
            ->dispatch();

        return true;
    }

    /**
     * Schliesst einen Lauf als failed ab (Kette endgueltig gescheitert oder
     * Watchdog aufgegeben): Fehlschlag am Thema (#9, ggf. Pausierung mit
     * Alarm) und Alarm `run_failed`. Laeuft auch ausserhalb des
     * Tenant-Kontexts.
     */
    public static function failRun(int $tenantId, int $runId, string $reason, string $source): void
    {
        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(static function () use ($tenantId, $runId, $reason, $source): void {
            $run = TopicRun::query()->with('topic')->find($runId);

            if ($run === null || ! $run->status->canTransitionTo(RunStatus::FAILED)) {
                return;
            }

            $run->forceFill([
                'status' => RunStatus::FAILED,
                'error' => mb_substr("{$source}: {$reason}", 0, 2000),
                'finished_at' => Carbon::now(),
            ])->save();

            $forDate = Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();

            if ($run->topic?->recordFailure()) {
                GuideAlert::raise(
                    "topic_paused:{$tenantId}:{$run->guide_topic_id}",
                    GuideAlert::KEY_TOPIC_PAUSED,
                    GuideAlert::LEVEL_WARNING,
                    "Ratgeber-Thema {$run->guide_topic_id} nach {$run->topic->consecutive_failures} Fehlschlaegen in Folge pausiert: ".mb_substr((string) $run->topic->question, 0, 200),
                    [
                        'tenant_id' => $tenantId,
                        'guide_topic_id' => $run->guide_topic_id,
                        'guide_topic_run_id' => $runId,
                        'for_date' => $forDate,
                        'context_json' => ['consecutive_failures' => $run->topic->consecutive_failures, 'status' => $run->topic->status->value],
                    ],
                );
            }

            GuideAlert::raise(
                "run_failed:{$tenantId}:{$runId}",
                GuideAlert::KEY_RUN_FAILED,
                GuideAlert::LEVEL_WARNING,
                "Ratgeber-Lauf {$runId} (Thema {$run->guide_topic_id}) fehlgeschlagen: ".mb_substr($reason, 0, 300),
                [
                    'tenant_id' => $tenantId,
                    'guide_topic_id' => $run->guide_topic_id,
                    'guide_topic_run_id' => $runId,
                    'for_date' => $forDate,
                    'context_json' => ['job' => class_basename($source), 'exception' => $source],
                ],
            );
        });
    }

    /**
     * writing nach einem Fix-Entscheid des Gates (#11) ist der
     * Nachbesserungsdurchlauf der gepruefte Fassung, sonst Schreiben bzw.
     * Aktualisieren je Modus.
     */
    private function writingJob(int $tenantId, TopicRun $run): object
    {
        $report = (array) ($run->quality_report_json ?? []);
        $runId = (int) $run->getKey();

        if (($report['decision'] ?? null) === QualityGate::DECISION_FIX && isset($report['version_id'])) {
            return new FixSectionsJob($tenantId, $runId, (int) $report['version_id']);
        }

        return $run->mode === RunMode::UPDATE
            ? new UpdateSectionsJob($tenantId, $runId)
            : new WriteArticleJob($tenantId, $runId);
    }

    /**
     * checking mit Freigabe des Gates fuer die juengste Fassung wartet auf den
     * Publisher; sonst laeuft das Gate fuer die juengste Fassung (erneut).
     */
    private function checkingJob(int $tenantId, TopicRun $run): ?object
    {
        /** @var ArticleVersion|null $version */
        $version = $run->versions()->latest('id')->first();

        if ($version === null) {
            return null;
        }

        $report = (array) ($run->quality_report_json ?? []);
        $runId = (int) $run->getKey();
        $versionId = (int) $version->getKey();

        if (($report['decision'] ?? null) === QualityGate::DECISION_PUBLISH && (int) ($report['version_id'] ?? 0) === $versionId) {
            return new PublishArticleJob($tenantId, $runId, $versionId);
        }

        return new QualityCheckJob($tenantId, $runId, $versionId);
    }
}
