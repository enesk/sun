<?php

declare(strict_types=1);

namespace App\Content\Providers;

use App\Content\Llm\BudgetGuard;
use App\Content\Llm\LlmContext;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\Central\ProviderState;
use App\Content\Providers\Exceptions\ProviderQuotaException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Zugang zur DataForSEO-API (#8, spaeter #10).
 *
 * Drei Dinge passieren hier und nirgends sonst:
 *
 *  1. Kontingent. Vor jeder Anfrage wird geprueft, wieviele Anfragen der
 *     Provider heute schon gestellt hat (provider_states.requests_today) —
 *     einmal gegen die Gesamtgrenze und einmal gegen die Grenze des
 *     einzelnen Endpunkts. Zusaetzlich laeuft der BudgetGuard mit, der die
 *     USD-Grenzen aus llm_usage_logs prueft. Ist ein Kontingent erschoepft,
 *     wird der Provider bis 00:00 auf STATUS_PAUSED gesetzt und eine
 *     ProviderQuotaException geworfen.
 *
 *  2. Zwischenspeicher. Antworten werden unter einem Schluessel aus Pfad und
 *     Anfrage abgelegt (Standard 24 h). Ein Wiederholungslauf am selben Tag
 *     kostet damit nichts und zaehlt nicht gegen das Kontingent.
 *     Hinweis: der Dateicache haengt am mandantenspezifischen storage_path,
 *     der Zwischenspeicher wirkt also je Mandant. Das genuegt, weil die
 *     Anfragen ohnehin aus den Branchen-Keywords des Mandanten entstehen.
 *
 *  3. Kosten-Logging. Jeder Aufruf landet mit seinem Pauschalpreis in
 *     llm_usage_logs und damit in der Kostenansicht (#25).
 */
final class DataForSeoClient
{
    public const PROVIDER = 'dataforseo';

    /** Endpunktnamen im Kosten-Log und im Endpunkt-Kontingent. */
    public const OPERATION_TRENDS = 'trends_explore';

    public const OPERATION_SERP = 'serp_organic';

    public const OPERATION_VOLUME = 'keyword_volume';

    public const OPERATION_AUTOCOMPLETE = 'autocomplete';

    /**
     * Konfigurationszweig je Endpunkt. Darunter stehen jeweils path,
     * cache_seconds und max_requests_per_day; die Zuordnung steht hier
     * einmal, damit remainingRequests() kein zweites Mal danach sucht.
     *
     * @var array<string, string>
     */
    private const OPERATION_CONFIG = [
        self::OPERATION_TRENDS => 'trends',
        self::OPERATION_SERP => 'serp',
        self::OPERATION_VOLUME => 'search_volume',
        self::OPERATION_AUTOCOMPLETE => 'autocomplete',
    ];

    private const CACHE_PREFIX = 'content:dataforseo:';

    /** DataForSEO meldet Erfolg mit 20000, angenommene Tasks mit 20100. */
    private const STATUS_OK = 20000;

    public function __construct(
        private readonly BudgetGuard $budget,
    ) {}

    public function isConfigured(): bool
    {
        return $this->credential('login') !== '' && $this->credential('password') !== '';
    }

    /**
     * Google-Trends-Explore fuer bis zu fuenf Keywords in einer Region.
     *
     * @param  array<int, string>  $keywords  hoechstens keywords_per_request
     * @return array<int, array<string, mixed>> die 'result'-Liste der Task
     *
     * @throws ProviderQuotaException
     */
    public function trendsExplore(array $keywords, int $locationCode, ?LlmContext $context = null): array
    {
        $keywords = array_values(array_filter(
            array_map(fn ($keyword) => is_string($keyword) ? trim($keyword) : '', $keywords),
            fn (string $keyword) => $keyword !== '',
        ));

        $limit = max(1, (int) $this->option('trends.keywords_per_request', 5));

        if ($keywords === []) {
            return [];
        }

        if (count($keywords) > $limit) {
            throw new InvalidArgumentException(
                "Google Trends Explore nimmt hoechstens {$limit} Keywords je Anfrage entgegen."
            );
        }

        return $this->post(
            path: (string) $this->option('trends.path', 'keywords_data/google_trends/explore/live'),
            task: [
                'keywords' => $keywords,
                'location_code' => $locationCode,
                'language_code' => (string) $this->option('language_code', 'de'),
                'time_range' => (string) $this->option('trends.time_range', 'past_90_days'),
                'item_types' => array_values((array) $this->option('trends.item_types', [
                    'google_trends_graph',
                    'google_trends_queries_list',
                ])),
            ],
            operation: self::OPERATION_TRENDS,
            costUsd: (float) $this->option('pricing.trends_per_request', 0.0),
            context: $context,
            cacheSeconds: (int) $this->option('trends.cache_seconds', 86400),
            operationQuota: (int) $this->option('trends.max_requests_per_day', 0),
        );
    }

    /**
     * Organische Suchergebnisse inklusive People Also Ask und Related
     * Searches (#10). Ein Task = ein Keyword in einer Region.
     *
     * @return array<int, array<string, mixed>> die 'result'-Liste der Task
     *
     * @throws ProviderQuotaException
     */
    public function serpOrganic(string $keyword, int $locationCode, ?LlmContext $context = null): array
    {
        return $this->post(
            path: (string) $this->option('serp.path', 'serp/google/organic/live/advanced'),
            task: [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_code' => (string) $this->option('language_code', 'de'),
                'device' => (string) $this->option('serp.device', 'desktop'),
                'os' => (string) $this->option('serp.os', 'windows'),
                'depth' => max(10, (int) $this->option('serp.depth', 20)),
                // Klicktiefe 1 liefert die Folgefragen, die Google beim
                // Aufklappen einer PAA-Frage nachlaedt.
                'people_also_ask_click_depth' => (int) $this->option('serp.people_also_ask_click_depth', 1),
            ],
            operation: self::OPERATION_SERP,
            costUsd: (float) $this->option('pricing.serp_advanced_per_request', 0.0),
            context: $context,
            cacheSeconds: (int) $this->option('serp.cache_seconds', 1209600),
            operationQuota: (int) $this->option('serp.max_requests_per_day', 0),
        );
    }

    /**
     * Monatliches Suchvolumen, CPC und Wettbewerb aus Google Ads (#10).
     * Der Endpunkt nimmt mehrere Keywords in einer Anfrage entgegen.
     *
     * @param  array<int, string>  $keywords
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderQuotaException
     */
    public function searchVolume(array $keywords, int $locationCode, ?LlmContext $context = null): array
    {
        $keywords = array_values(array_unique(array_filter(
            array_map(static fn ($keyword) => is_string($keyword) ? trim($keyword) : '', $keywords),
            static fn (string $keyword) => $keyword !== '',
        )));

        if ($keywords === []) {
            return [];
        }

        $limit = max(1, (int) $this->option('search_volume.keywords_per_request', 700));

        if (count($keywords) > $limit) {
            throw new InvalidArgumentException(
                "Der Suchvolumen-Endpunkt nimmt hoechstens {$limit} Keywords je Anfrage entgegen."
            );
        }

        return $this->post(
            path: (string) $this->option('search_volume.path', 'keywords_data/google_ads/search_volume/live'),
            task: [
                'keywords' => $keywords,
                'location_code' => $locationCode,
                'language_code' => (string) $this->option('language_code', 'de'),
                'search_partners' => false,
                'sort_by' => 'search_volume',
            ],
            operation: self::OPERATION_VOLUME,
            costUsd: (float) $this->option('pricing.keyword_volume_per_request', 0.0),
            context: $context,
            cacheSeconds: (int) $this->option('search_volume.cache_seconds', 1209600),
            operationQuota: (int) $this->option('search_volume.max_requests_per_day', 0),
        );
    }

    /**
     * Vorschlaege der Google-Autovervollstaendigung zu einem Keyword (#10).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderQuotaException
     */
    public function autocomplete(string $keyword, int $locationCode, ?LlmContext $context = null): array
    {
        return $this->post(
            path: (string) $this->option('autocomplete.path', 'serp/google/autocomplete/live/advanced'),
            task: [
                'keyword' => $keyword,
                'location_code' => $locationCode,
                'language_code' => (string) $this->option('language_code', 'de'),
                // 'gws-wiz-serp' liefert dieselbe Liste wie das Suchfeld auf
                // einer Ergebnisseite, nicht die der Startseite.
                'client' => (string) $this->option('autocomplete.client', 'gws-wiz-serp'),
            ],
            operation: self::OPERATION_AUTOCOMPLETE,
            costUsd: (float) $this->option('pricing.autocomplete_per_request', 0.0),
            context: $context,
            cacheSeconds: (int) $this->option('autocomplete.cache_seconds', 1209600),
            operationQuota: (int) $this->option('autocomplete.max_requests_per_day', 0),
        );
    }

    /**
     * Ein Task an einen DataForSEO-Endpunkt. Die API erwartet immer eine
     * Liste von Tasks; wir stellen genau einen und geben dessen Ergebnis
     * zurueck.
     *
     * @param  array<string, mixed>  $task
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderQuotaException
     */
    public function post(
        string $path,
        array $task,
        string $operation,
        float $costUsd,
        ?LlmContext $context = null,
        int $cacheSeconds = 0,
        int $operationQuota = 0,
    ): array {
        $context = ($context ?? LlmContext::current())->withOperation($operation);
        $cacheKey = $this->cacheKey($path, $task);

        if ($cacheSeconds > 0) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return $cached;
            }
        }

        $this->assertQuota($operation, $operationQuota, $context->tenantId);

        $startedAt = microtime(true);

        try {
            $response = $this->http()->post('/'.ltrim($path, '/'), [$task]);
        } catch (\Throwable $exception) {
            $this->record($context, $costUsd, $this->elapsed($startedAt), false, $exception->getMessage());

            throw $exception;
        }

        $durationMs = $this->elapsed($startedAt);

        if ($response->failed()) {
            $this->record($context, $costUsd, $durationMs, false, $this->errorMessage($response));

            $response->throw();
        }

        try {
            $result = $this->result((array) $response->json());
        } catch (RuntimeException $exception) {
            $this->record($context, $costUsd, $durationMs, false, $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $costUsd, $durationMs, true, null);

        if ($cacheSeconds > 0) {
            Cache::put($cacheKey, $result, $cacheSeconds);
        }

        return $result;
    }

    /**
     * Wieviele Anfragen der Provider heute noch stellen darf. Der Connector
     * schneidet seine Regionenliste damit zu, statt mitten im Lauf in die
     * Kontingentgrenze zu laufen.
     */
    public function remainingRequests(?string $operation = null): int
    {
        $state = $this->budget->stateFor(self::PROVIDER);

        $total = (int) $this->option('max_requests_per_day', 0);
        $remaining = $total > 0 ? max(0, $total - $state->requests_today) : PHP_INT_MAX;

        if ($operation === null) {
            return $remaining;
        }

        $branch = self::OPERATION_CONFIG[$operation] ?? null;
        $quota = $branch === null ? 0 : (int) $this->option("{$branch}.max_requests_per_day", 0);

        if ($quota <= 0) {
            return $remaining;
        }

        return min($remaining, max(0, $quota - $this->operationCount($state, $operation)));
    }

    /**
     * @throws ProviderQuotaException
     */
    private function assertQuota(string $operation, int $operationQuota, ?int $tenantId): void
    {
        // stateFor() erledigt den Tagesrollover und gibt eine abgelaufene
        // Tagespause wieder frei.
        $state = $this->budget->stateFor(self::PROVIDER);

        $total = (int) $this->option('max_requests_per_day', 0);

        if ($total > 0 && $state->requests_today >= $total) {
            $this->pause($state, ProviderQuotaException::forScope(self::PROVIDER, 'provider', $state->requests_today, $total));
        }

        if ($operationQuota > 0) {
            $used = $this->operationCount($state, $operation);

            if ($used >= $operationQuota) {
                $this->pause($state, ProviderQuotaException::forScope(self::PROVIDER, $operation, $used, $operationQuota));
            }
        }

        // USD-Grenzen aus config('content.budget'); wirft BudgetExceededException.
        $this->budget->check(self::PROVIDER, $tenantId);
    }

    /**
     * @throws ProviderQuotaException
     */
    private function pause(ProviderState $state, ProviderQuotaException $exception): never
    {
        if (! $state->isPaused()) {
            $meta = $state->meta_json ?? [];
            $meta['quota_paused_at'] = now()->toIso8601String();
            $meta['quota_scope'] = $exception->scope;

            $state->forceFill([
                'status' => ProviderState::STATUS_PAUSED,
                'last_error' => Str::limit($exception->getMessage(), 500, ''),
                // Freigabe um 00:00, genau wie bei der Budgetpause.
                'circuit_open_until' => now()->addDay()->startOfDay(),
                'meta_json' => $meta,
            ])->save();

            Log::warning('DataForSEO-Kontingent erschoepft.', [
                'scope' => $exception->scope,
                'used' => $exception->used,
                'limit' => $exception->limit,
            ]);
        }

        throw $exception;
    }

    /**
     * Zaehler je Endpunkt. requests_today in provider_states zaehlt alle
     * DataForSEO-Anfragen zusammen; die Aufteilung auf die Endpunkte steht
     * daneben in meta_json und rollt mit dem Datum um.
     */
    private function operationCount(ProviderState $state, string $operation): int
    {
        $counter = (array) (($state->meta_json ?? [])['operation_requests'] ?? []);

        if (($counter['date'] ?? null) !== now()->toDateString()) {
            return 0;
        }

        return (int) (($counter['counts'] ?? [])[$operation] ?? 0);
    }

    /**
     * Anfrage zaehlen und mit ihrem Pauschalpreis protokollieren — auch dann,
     * wenn sie gescheitert ist: DataForSEO rechnet fehlgeschlagene Tasks mit
     * ab, sobald sie den Endpunkt erreicht haben.
     */
    private function record(LlmContext $context, float $costUsd, int $durationMs, bool $successful, ?string $error): void
    {
        $operation = Str::limit($context->operation ?? 'dataforseo', 64, '');

        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => 'dataforseo',
            'operation' => $operation,
            'reference_type' => $context->referenceType,
            'reference_id' => $context->referenceId,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'requests' => 1,
            'cost_usd' => $costUsd,
            'duration_ms' => $durationMs,
            'was_successful' => $successful,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        $this->budget->record(self::PROVIDER, $costUsd);
        $this->countOperation($operation);
    }

    private function countOperation(string $operation): void
    {
        $state = ProviderState::forProvider(self::PROVIDER);
        $meta = $state->meta_json ?? [];
        $today = now()->toDateString();

        $counter = (array) ($meta['operation_requests'] ?? []);

        if (($counter['date'] ?? null) !== $today) {
            $counter = ['date' => $today, 'counts' => []];
        }

        $counts = (array) ($counter['counts'] ?? []);
        $counts[$operation] = (int) ($counts[$operation] ?? 0) + 1;
        $counter['counts'] = $counts;
        $meta['operation_requests'] = $counter;

        $state->forceFill(['meta_json' => $meta])->save();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<int, array<string, mixed>>
     */
    private function result(array $body): array
    {
        $status = (int) ($body['status_code'] ?? 0);

        if ($status !== self::STATUS_OK) {
            throw new RuntimeException(
                'DataForSEO antwortet mit Status '.$status.': '.(string) ($body['status_message'] ?? 'unbekannt')
            );
        }

        $task = (array) (($body['tasks'] ?? [])[0] ?? []);
        $taskStatus = (int) ($task['status_code'] ?? 0);

        if ($taskStatus !== self::STATUS_OK) {
            throw new RuntimeException(
                'DataForSEO-Task scheitert mit Status '.$taskStatus.': '.(string) ($task['status_message'] ?? 'unbekannt')
            );
        }

        return array_values(array_filter(
            (array) ($task['result'] ?? []),
            static fn ($entry) => is_array($entry),
        ));
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function cacheKey(string $path, array $task): string
    {
        return self::CACHE_PREFIX.sha1($path.'|'.json_encode($task, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function http(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new InvalidArgumentException('DATAFORSEO_LOGIN und DATAFORSEO_PASSWORD sind nicht gesetzt.');
        }

        $retry = (array) config('content.resilience.retry', []);

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://api.dataforseo.com/v3'), '/'))
            ->withBasicAuth($this->credential('login'), $this->credential('password'))
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 60))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                (int) ($retry['base_delay_ms'] ?? 2000),
                fn (\Throwable $exception) => ! $exception instanceof \Illuminate\Http\Client\RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    private function errorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json();
        $message = is_array($body) ? ($body['status_message'] ?? null) : null;

        return 'HTTP '.$response->status().': '.(is_string($message) ? $message : Str::limit($response->body(), 200, ''));
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function credential(string $key): string
    {
        return trim((string) $this->option($key, ''));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.dataforseo.{$key}", $default);
    }
}
