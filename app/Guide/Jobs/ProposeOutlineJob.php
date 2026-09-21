<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\TopicStatus;
use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Topic;
use App\Guide\Writing\OutlineService;
use App\Guide\Writing\WritingContext;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Gliederungsvorschlag fuer ein Thema ohne Ueberschriften (#10). Angestossen
 * von ProposeOutlineOnTopicCreated fuer importierte Themen im Status
 * outline_pending; der erste Lauf eines draft-Themas schlaegt die Gliederung
 * dagegen im WriteArticleJob vor, dann schon mit Fakten-Set.
 *
 * Mit guide.auto_lock_outline wird der Vorschlag sofort gesperrt und das
 * Thema aktiv, sonst wartet er auf die Bestaetigung im Dashboard
 * ("Gliederung bestaetigen", #15).
 *
 * Gehoert zu keinem Lauf: Kosten laufen mit Bezug auf das Thema in
 * llm_usage_logs, ein Fehler erzeugt einen guide_alert outline_failed.
 * Tages-/Tenant-Budget erschoepft -> release() und spaeter erneut.
 */
class ProposeOutlineJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $budgetReleaseSeconds = 3600;

    public function __construct(
        public int|string $tenantId,
        public int $topicId,
    ) {
        $this->onQueue((string) config('guide.queues.write', 'guide-write'));
    }

    public function retryUntil(): Carbon
    {
        return Carbon::now()->addHours(26);
    }

    public function uniqueId(): string
    {
        return static::class.":{$this->tenantId}:{$this->topicId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(OutlineService $outlines): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($outlines): void {
            $topic = Topic::query()->with('category')->find($this->topicId);

            if ($topic === null
                || $topic->isOutlineLocked()
                || ($topic->outline_json ?? []) !== []
                || ! in_array($topic->status, [TopicStatus::DRAFT, TopicStatus::OUTLINE_PENDING], true)) {
                return;
            }

            try {
                $outline = $outlines->propose(WritingContext::for($topic));
                $outlines->store($topic, $outline, $outlines->autoLock(), $this->tenantId);
            } catch (BudgetExceededException) {
                // Alarm hat der BudgetGuard gesetzt.
                $this->release($this->budgetReleaseSeconds);
            } catch (Throwable $exception) {
                report($exception);

                GuideAlert::raise(
                    "outline_failed:{$this->tenantId}:{$this->topicId}",
                    GuideAlert::KEY_OUTLINE_FAILED,
                    GuideAlert::LEVEL_WARNING,
                    "Gliederungsvorschlag fuer Ratgeber-Thema {$this->topicId} fehlgeschlagen: ".mb_substr($exception->getMessage(), 0, 300),
                    [
                        'tenant_id' => $this->tenantId,
                        'guide_topic_id' => $this->topicId,
                        'for_date' => Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString(),
                        'context_json' => ['job' => class_basename(static::class), 'exception' => $exception::class],
                    ],
                );
            }
        });
    }
}
