<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Models\TenantContentSetting;
use App\Content\Services\PerformanceAggregator;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Woechentliche Lernschleife (#23).
 *
 * Aggregiert ueber den PerformanceAggregator, was bei diesem Mandanten
 * bisher Klicks gebracht hat, und legt das Ergebnis in
 * tenant_content_settings.cluster_performance_json ab. Von dort liest der
 * TopicScorer (#12) seinen Teilscore `performance`.
 *
 * Der Job rechnet und schreibt, er entscheidet nichts: welches Thema am Ende
 * oben steht, bleibt Sache des Scorings und seiner Gewichte.
 */
class LearningJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public bool $force = true,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.metrics', 'content-metrics'));
    }

    public function uniqueId(): string
    {
        return "content-learning:{$this->tenantId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(PerformanceAggregator $aggregator): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $aggregator): void {
            $performance = $aggregator->aggregate($tenant, $this->force);

            TenantContentSetting::current()
                ->forceFill(['cluster_performance_json' => $performance])
                ->save();

            Log::info('Lernschleife: Performance je Cluster und Region aktualisiert.', [
                'tenant_id' => $this->tenantId,
                'articles' => $performance['articles'] ?? 0,
                'clusters' => count((array) ($performance['clusters'] ?? [])),
                'regions' => count((array) ($performance['regions'] ?? [])),
            ]);
        });
    }
}
