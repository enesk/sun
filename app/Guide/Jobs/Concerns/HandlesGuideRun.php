<?php

declare(strict_types=1);

namespace App\Guide\Jobs\Concerns;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\Middleware\LimitGuideConcurrency;
use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\Exceptions\ProviderAccountException;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Central\GuideRunState;
use App\Guide\Models\TopicRun;
use App\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gemeinsamer Rahmen der Kettenglieder (docs/guide-system.md §2): Lauf im
 * Tenant-Kontext laden, Status nur ueber canTransitionTo() setzen, Fehler
 * als failed mit Alarm abschliessen.
 *
 * Wiederholungen macht der Provider-Client; ein erneuter Queue-Versuch
 * entsteht nur ueber release(): bei erschoepftem Tages- oder Tenant-Budget,
 * bei HTTP 429 nach den Wiederholungen des Clients und bei Provider-Ausfall
 * (toter Zugang, 5xx, Verbindungsfehler) — jeweils mit wachsendem Abstand
 * (#13). Deshalb faengt run() jede andere Ausnahme selbst ab.
 *
 * Die Parallelitaetsgrenze je Tenant und gesamt setzt middleware() (#13);
 * Jobs ohne Modellaufruf (PublishArticleJob) ueberschreiben sie.
 */
trait HandlesGuideRun
{
    public int $timeout = 900;

    /** Nach Budget-Stopp neuer Versuch nach dieser Zeit. */
    public int $budgetReleaseSeconds = 3600;

    public function retryUntil(): Carbon
    {
        return Carbon::now()->addHours(26);
    }

    public function uniqueId(): string
    {
        return static::class.":{$this->tenantId}:{$this->runId}";
    }

    /**
     * Eine Stunde ab Start; bei gestaffelt eingereihten Laeufen (#13) zaehlt
     * die Verzoegerung dazu, sonst verfiele die Sperre vor dem Start und
     * der Watchdog oder ein zweiter Tageslauf koennte den Job verdoppeln.
     */
    public function uniqueFor(): int
    {
        return 3600 + (is_int($this->delay) ? max(0, $this->delay) : 0);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new LimitGuideConcurrency($this->tenantId, $this->timeout)];
    }

    /**
     * @param  callable(TopicRun): void  $step
     */
    protected function withRun(callable $step): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($step): void {
            $run = TopicRun::query()->with('topic')->find($this->runId);

            if ($run === null || $run->topic === null) {
                Log::warning('Guide-Lauf nicht gefunden.', ['tenant_id' => $this->tenantId, 'run_id' => $this->runId]);

                return;
            }

            if ($this->stopsWhenPaused() && GuideRunState::isPaused()) {
                $this->endPausedRun($run);

                return;
            }

            try {
                $step($run);
            } catch (BudgetExceededException $exception) {
                if ($exception->scope === BudgetExceededException::SCOPE_RUN || ! $this->releaseOnBudget()) {
                    $this->failRun($run, $exception);

                    return;
                }

                // Alarm hat der BudgetGuard gesetzt; der Lauf holt spaeter nach.
                $this->release($this->budgetReleaseSeconds);
            } catch (Throwable $exception) {
                if ($this->isRateLimited($exception)) {
                    $this->release($this->backoffSeconds());

                    return;
                }

                if ($this->isProviderOutage($exception)) {
                    $this->raiseProviderDown($exception);
                    $this->release($this->backoffSeconds());

                    return;
                }

                $this->failRun($run, $exception);
            }
        });
    }

    /**
     * Global pausiert (#33): das Kettenglied beendet den Lauf, statt
     * weiterzuarbeiten. Ueberschreiben mit false, wenn der Schritt eine
     * menschliche Entscheidung ausfuehrt (Veroeffentlichen nach Freigabe).
     */
    protected function stopsWhenPaused(): bool
    {
        return true;
    }

    /**
     * Beendet den Lauf als failed mit Grund, ohne dem Thema einen
     * Fehlschlag anzurechnen und ohne Alarm: die Pause ist eine Entscheidung,
     * keine Stoerung. Das Thema bleibt faellig und laeuft nach dem Fortsetzen.
     */
    private function endPausedRun(TopicRun $run): void
    {
        if (! $run->status->canTransitionTo(RunStatus::FAILED) || $run->status === RunStatus::REVIEW) {
            return;
        }

        $run->forceFill([
            'status' => RunStatus::FAILED,
            'error' => 'Tageslauf global pausiert – Lauf beendet.',
            'finished_at' => Carbon::now(),
        ])->save();

        Log::info('Guide-Lauf wegen globaler Pause beendet.', ['tenant_id' => $this->tenantId, 'run_id' => $run->getKey()]);
    }

    /**
     * Wachsender Abstand je Queue-Versuch: base * 2^(Versuch-1), hoechstens
     * max, plus Jitter (guide.concurrency.rate_limit_backoff).
     */
    protected function backoffSeconds(): int
    {
        $config = (array) config('guide.concurrency.rate_limit_backoff', []);
        $base = max(1, (int) ($config['base_seconds'] ?? 60));
        $max = max($base, (int) ($config['max_seconds'] ?? 1800));
        $jitter = max(0, (int) ($config['jitter_seconds'] ?? 30));
        $attempt = max(1, $this->attempts());

        return min($max, $base * 2 ** min($attempt - 1, 16)) + random_int(0, $jitter);
    }

    private function isRateLimited(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof RequestException && $current->response->status() === 429) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ausfall des Providers statt Fehler des Laufs: toter Zugang (Guthaben,
     * Schluessel), 5xx oder Verbindungsfehler nach den Wiederholungen des
     * Clients. Das Thema bekommt dafuer keinen Fehlschlag angerechnet.
     */
    private function isProviderOutage(Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ProviderAccountException || $current instanceof ConnectionException) {
                return true;
            }

            if ($current instanceof RequestException && $current->response->status() >= 500) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ein Alarm je Tag, weitere Treffer zaehlen occurrences hoch.
     */
    private function raiseProviderDown(Throwable $exception): void
    {
        report($exception);

        $forDate = Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();

        GuideAlert::raise(
            "provider_down:anthropic:{$forDate}",
            GuideAlert::KEY_PROVIDER_DOWN,
            GuideAlert::LEVEL_CRITICAL,
            'Modell-Provider nicht erreichbar, Ratgeber-Laeufe werden zurueckgestellt: '.mb_substr($exception->getMessage(), 0, 300),
            [
                'for_date' => $forDate,
                'context_json' => ['job' => class_basename(static::class), 'exception' => $exception::class, 'tenant_id' => $this->tenantId],
            ],
        );
    }

    /**
     * Tages- oder Tenant-Budget erschoepft: true = release() und spaeter
     * nachholen (Recherche), false = Lauf failed (Schreib-Jobs, #10 — ein
     * halb geschriebener Artikel wird nicht fortgesetzt, sondern neu gelaufen).
     */
    protected function releaseOnBudget(): bool
    {
        return true;
    }

    /**
     * Setzt den Status, wenn der Wechsel erlaubt ist. Steht der Lauf schon
     * auf dem Ziel (Wiederaufnahme nach release()), bleibt er dort.
     */
    protected function transition(TopicRun $run, RunStatus $target, array $attributes = []): bool
    {
        if ($run->status !== $target && ! $run->status->canTransitionTo($target)) {
            Log::warning('Unzulaessiger Statuswechsel im Guide-Lauf.', [
                'run_id' => $run->getKey(),
                'from' => $run->status->value,
                'to' => $target->value,
            ]);

            return false;
        }

        $run->forceFill(['status' => $target, ...$attributes])->save();

        return true;
    }

    protected function failRun(TopicRun $run, Throwable $exception): void
    {
        report($exception);

        if ($run->status->canTransitionTo(RunStatus::FAILED)) {
            $run->forceFill([
                'status' => RunStatus::FAILED,
                'error' => mb_substr($exception::class.': '.$exception->getMessage(), 0, 2000),
                'finished_at' => Carbon::now(),
            ])->save();
        }

        $forDate = Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString();

        // Naechster Versuch morgen; nach zu vielen Fehlschlaegen pausiert (#9).
        if ($run->topic?->recordFailure()) {
            GuideAlert::raise(
                "topic_paused:{$this->tenantId}:{$run->guide_topic_id}",
                GuideAlert::KEY_TOPIC_PAUSED,
                GuideAlert::LEVEL_WARNING,
                "Ratgeber-Thema {$run->guide_topic_id} nach {$run->topic->consecutive_failures} Fehlschlaegen in Folge pausiert: ".mb_substr((string) $run->topic->question, 0, 200),
                [
                    'tenant_id' => $this->tenantId,
                    'guide_topic_id' => $run->guide_topic_id,
                    'guide_topic_run_id' => $run->getKey(),
                    'for_date' => $forDate,
                    'context_json' => ['consecutive_failures' => $run->topic->consecutive_failures, 'status' => $run->topic->status->value],
                ],
            );
        }

        GuideAlert::raise(
            "run_failed:{$this->tenantId}:{$run->getKey()}",
            'run_failed',
            GuideAlert::LEVEL_WARNING,
            "Ratgeber-Lauf {$run->getKey()} (Thema {$run->guide_topic_id}) fehlgeschlagen: ".mb_substr($exception->getMessage(), 0, 300),
            [
                'tenant_id' => $this->tenantId,
                'guide_topic_id' => $run->guide_topic_id,
                'guide_topic_run_id' => $run->getKey(),
                'for_date' => $forDate,
                'context_json' => ['job' => class_basename(static::class), 'exception' => $exception::class],
            ],
        );
    }
}
