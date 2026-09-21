<?php

declare(strict_types=1);

namespace App\Guide\Jobs;

use App\Guide\Assets\HeroImageGenerator;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\Topic;
use App\Guide\Support\GuidePageCache;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Titelbild eines Themas (#20).
 *
 * Laeuft genau einmal je Thema: PublishArticleJob stoesst ihn nach der
 * ersten Veroeffentlichung an, solange guide_article_details.hero_image_path
 * leer ist; taegliche Aktualisierungen finden das Bild vor und starten ihn
 * nicht. Der Job selbst prueft das noch einmal, nur $force (Dashboard:
 * "Bild neu erzeugen") erzeugt ein vorhandenes Bild neu.
 *
 * Beiwerk der Kette: die Veroeffentlichung wartet nicht darauf, und ein
 * Fehler setzt keinen Lauf auf failed. Scheitert fal.ai, nimmt der
 * HeroImageGenerator das Branchen-Standardbild.
 */
class GenerateTopicImageJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public int $tenantId,
        public int $topicId,
        public bool $force = false,
        public ?int $runId = null,
    ) {
        $this->onQueue((string) config('guide.queues.assets', 'guide-assets'));
    }

    public function uniqueId(): string
    {
        return static::class.":{$this->tenantId}:{$this->topicId}";
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(HeroImageGenerator $generator): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($generator): void {
            $topic = Topic::query()->find($this->topicId);
            $detail = $topic?->article_id !== null
                ? ArticleDetail::query()->where('article_id', $topic->article_id)->first()
                : null;

            if ($topic === null || $detail === null) {
                Log::info('Titelbild: Thema ohne veroeffentlichten Artikel, nichts zu tun.', [
                    'tenant_id' => $this->tenantId,
                    'topic_id' => $this->topicId,
                ]);

                return;
            }

            if (! $this->force && filled($detail->hero_image_path)) {
                return;
            }

            try {
                $image = $generator->generate($topic, new LlmCallContext(
                    tenantId: $this->tenantId,
                    topicId: (int) $topic->getKey(),
                    runId: $this->runId,
                ));
            } catch (Throwable $exception) {
                Log::error('Titelbild nicht erzeugt.', [
                    'tenant_id' => $this->tenantId,
                    'topic_id' => $this->topicId,
                    'exception' => $exception->getMessage(),
                ]);

                return;
            }

            $detail->forceFill([
                'hero_image_path' => $image['path'],
                'hero_image_alt' => $image['alt'],
                'hero_image_width' => $image['width'],
                'hero_image_height' => $image['height'],
            ])->save();

            GuidePageCache::flush();

            Log::info('Titelbild erzeugt.', [
                'tenant_id' => $this->tenantId,
                'topic_id' => $this->topicId,
                'source' => $image['source'],
                'bytes' => $image['bytes'],
            ]);
        });
    }
}
