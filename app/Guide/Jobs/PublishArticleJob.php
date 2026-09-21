<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\Concerns\HandlesGuideRun;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\TopicRun;
use App\Guide\Publishing\GuidePublisher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

/**
 * Letztes Kettenglied (#12): veroeffentlicht die vom Qualitaetsgate (#11)
 * oder in der Pruef-Queue (#16) freigegebene Fassung ueber den
 * GuidePublisher. Lauf checking|review -> published.
 *
 * Wiederholbar: Ein Lauf, der schon published ist, bleibt unberuehrt. Ein
 * Fehler beim Schreiben rollt die Transaktion zurueck, der Lauf geht auf
 * failed (HandlesGuideRun), die bisherige Fassung bleibt online.
 *
 * Nach einer Veroeffentlichung ohne Titelbild (erste Veroeffentlichung des
 * Themas) wird GenerateTopicImageJob angestossen (#20); die Veroeffentlichung
 * wartet nicht darauf. Laeufe mit vorhandenem Bild erzeugen keins.
 */
class PublishArticleJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesGuideRun, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $runId,
        public int $versionId,
        public bool $continue = true,
    ) {
        $this->onQueue((string) config('guide.queues.publish', 'guide-publish'));
    }

    /**
     * Kein Modellaufruf, also keine Parallelitaetsgrenze (#13); die Queue
     * guide-publish hat ohnehin nur einen Worker.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Veroeffentlichen setzt eine Freigabe um und laeuft auch bei globaler
     * Pause (#33) zu Ende.
     */
    protected function stopsWhenPaused(): bool
    {
        return false;
    }

    public function handle(GuidePublisher $publisher): void
    {
        $this->withRun(function (TopicRun $run) use ($publisher): void {
            if ($run->status === RunStatus::PUBLISHED) {
                return;
            }

            /** @var ArticleVersion|null $version */
            $version = ArticleVersion::query()
                ->whereKey($this->versionId)
                ->where('guide_topic_run_id', $run->getKey())
                ->first();

            if ($version === null) {
                throw new RuntimeException("Fassung {$this->versionId} gehoert nicht zu Lauf {$run->getKey()}.");
            }

            $log = $publisher->publish($run, $version);

            $this->requestHeroImage($run, $log);
        });
    }

    /**
     * @param  array<string, mixed>  $log  Protokoll aus GuidePublisher::publish()
     */
    private function requestHeroImage(TopicRun $run, array $log): void
    {
        if (($log['action'] ?? null) === GuidePublisher::ACTION_UNCHANGED) {
            return;
        }

        $hasImage = ArticleDetail::query()
            ->where('article_id', (int) ($log['article_id'] ?? 0))
            ->whereNotNull('hero_image_path')
            ->exists();

        if ($hasImage) {
            return;
        }

        GenerateTopicImageJob::dispatch($this->tenantId, (int) $run->guide_topic_id, false, (int) $run->getKey());
    }
}
