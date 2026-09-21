<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleMetricRaw;
use App\Content\Models\TenantContentSetting;
use App\Content\Providers\SearchConsoleClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Search-Console-Rohzeilen eines Portals nach article_metrics_raw (#23).
 *
 * Bis zum Rueckbau der Themenfindung hat der Gap-Connector diese Zeilen als
 * Nebenprodukt abgelegt; seitdem holt der Metrik-Collector sie selbst, bevor
 * er verdichtet. Abruf wie bisher: Dimensionen query + page, Fenster
 * `providers.search_console.lookback_days`, `lag_days` Verzoegerung wegen der
 * Datenlatenz. Ein Snapshot je Fensterende; ein zweiter Lauf am selben Tag
 * aktualisiert ihn.
 *
 * Erwartet einen initialisierten Tenant-Kontext.
 */
class SearchConsoleRawFetcher
{
    public function __construct(private readonly SearchConsoleClient $client) {}

    /**
     * Anzahl geschriebener Zeilen; 0 ohne Property oder Service-Account.
     */
    public function fetch(int $tenantId): int
    {
        $property = trim((string) TenantContentSetting::current()->gsc_property);

        if ($property === '') {
            Log::info('Search Console: keine Property hinterlegt, Portal wird uebersprungen.', ['tenant_id' => $tenantId]);

            return 0;
        }

        if (! $this->client->isConfigured()) {
            Log::warning('Search Console: kein Service-Account-Schluessel hinterlegt.', ['tenant_id' => $tenantId]);

            return 0;
        }

        $windowEnd = CarbonImmutable::today()->subDays((int) config('content.providers.search_console.lag_days', 3));
        $windowDays = (int) config('content.providers.search_console.lookback_days', 28);
        $windowStart = $windowEnd->subDays($windowDays - 1);

        $rows = $this->client->searchAnalytics($property, $windowStart, $windowEnd, ['query', 'page']);
        $written = $this->store($rows, $property, $windowEnd, $windowDays);

        $this->prune($windowEnd);

        return $written;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function store(array $rows, string $property, CarbonImmutable $windowEnd, int $windowDays): int
    {
        $prefix = (string) config('content.metrics.article_path_prefix', '/ratgeber/');
        $now = now();
        $records = [];

        foreach ($rows as $row) {
            $keys = $row['keys'] ?? [];
            $query = is_string($keys[0] ?? null) ? trim($keys[0]) : '';
            $page = is_string($keys[1] ?? null) ? trim($keys[1]) : '';

            if ($query === '' || $page === '') {
                continue;
            }

            $path = $this->path($page);

            $records[] = [
                'window_end' => $windowEnd->toDateString(),
                'window_days' => $windowDays,
                'property' => Str::limit($property, 255, ''),
                'page' => Str::limit($page, 2048, ''),
                'page_path' => Str::limit($path, 512, ''),
                'page_hash' => ArticleMetricRaw::hash($page),
                'query' => Str::limit($query, 512, ''),
                'query_hash' => ArticleMetricRaw::hash($query),
                'impressions' => (int) ($row['impressions'] ?? 0),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'ctr' => round((float) ($row['ctr'] ?? 0), 4),
                'position' => round((float) ($row['position'] ?? 0), 2),
                'is_article' => str_starts_with($path, $prefix),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            ArticleMetricRaw::query()->upsert(
                $chunk,
                ['window_end', 'page_hash', 'query_hash'],
                ['page', 'page_path', 'query', 'property', 'window_days', 'impressions', 'clicks', 'ctr', 'position', 'is_article', 'updated_at'],
            );
        }

        return count($records);
    }

    private function prune(CarbonImmutable $windowEnd): void
    {
        $days = (int) config('content.metrics.raw_retention_days', 120);

        if ($days <= 0) {
            return;
        }

        ArticleMetricRaw::query()
            ->where('window_end', '<', $windowEnd->subDays($days)->toDateString())
            ->delete();
    }

    private function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }
}
