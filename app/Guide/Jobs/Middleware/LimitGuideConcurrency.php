<?php

declare(strict_types=1);

namespace App\Guide\Jobs\Middleware;

use Closure;
use Illuminate\Contracts\Redis\LimiterTimeoutException;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Redis;

/**
 * Parallelitaetsgrenze der Kettenglieder, die das Modell aufrufen (#13):
 * hoechstens guide.concurrency.per_tenant Jobs je Tenant und
 * guide.concurrency.total ueber alle Tenants gleichzeitig (Redis-Funnel).
 *
 * Ohne freien Platz wird der Job nach guide.concurrency.wait_seconds
 * (plus Jitter) zurueckgestellt; das kostet keinen Versuch, weil die Jobs
 * ueber retryUntil() statt $tries begrenzt sind. Ein Platz verfaellt nach
 * Job-Timeout + 60 s von selbst, falls ein Worker hart abstirbt.
 *
 * Synchron ausgefuehrte Jobs (`guide:run --force`) laufen ohne Funnel: ein
 * release() haette dort keinen Wiederholungsversuch zur Folge.
 */
class LimitGuideConcurrency
{
    public function __construct(
        private readonly int $tenantId,
        private readonly int $timeoutSeconds,
    ) {}

    public function handle(object $job, Closure $next): void
    {
        if (! (bool) config('guide.concurrency.enabled', true) || ! property_exists($job, 'job') || $job->job === null || $job->job instanceof SyncJob) {
            $next($job);

            return;
        }

        $config = (array) config('guide.concurrency', []);
        $redis = Redis::connection((string) ($config['redis_connection'] ?? 'default'));
        $releaseAfter = $this->timeoutSeconds + 60;

        try {
            $redis->funnel("guide:concurrency:tenant:{$this->tenantId}")
                ->limit(max(1, (int) ($config['per_tenant'] ?? 3)))
                ->releaseAfter($releaseAfter)
                ->block(0)
                ->then(function () use ($redis, $config, $releaseAfter, $job, $next): void {
                    $redis->funnel('guide:concurrency:total')
                        ->limit(max(1, (int) ($config['total'] ?? 12)))
                        ->releaseAfter($releaseAfter)
                        ->block(0)
                        ->then(fn () => $next($job));
                });
        } catch (LimiterTimeoutException) {
            $job->release($this->waitSeconds($config));
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function waitSeconds(array $config): int
    {
        $wait = max(1, (int) ($config['wait_seconds'] ?? 30));

        return $wait + random_int(0, $wait);
    }
}
