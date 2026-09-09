<?php

declare(strict_types=1);

namespace App\Content\Llm;

use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Models\Central\LlmUsageLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Embeddings ueber Voyage AI (#6).
 *
 * Grundlage der Duplikats- und Kannibalisierungspruefung (#12) und der
 * Fingerprints (#21). Beim Indexieren gilt input_type 'document', bei einer
 * Suchanfrage 'query' — Voyage bettet beide Seiten unterschiedlich ein.
 *
 * Kosten werden wie beim LlmClient je Aufruf in llm_usage_logs geschrieben
 * und laufen ueber denselben BudgetGuard.
 */
class EmbeddingClient
{
    public const PROVIDER = 'voyage';

    public const INPUT_DOCUMENT = 'document';

    public const INPUT_QUERY = 'query';

    public function __construct(
        private readonly BudgetGuard $budget,
    ) {}

    /**
     * Ein Vektor fuer einen Text.
     *
     * @return array<int, float>
     *
     * @throws BudgetExceededException
     */
    public function embed(string $text, string $inputType = self::INPUT_DOCUMENT, ?LlmContext $context = null): array
    {
        $vectors = $this->embedMany([$text], $inputType, $context);

        if ($vectors === []) {
            throw new RuntimeException('Voyage hat keinen Vektor geliefert.');
        }

        return $vectors[0];
    }

    /**
     * Mehrere Texte in einem Aufruf; wird in Bloecke der konfigurierten
     * Batchgroesse zerlegt. Die Reihenfolge der Rueckgabe entspricht der
     * Eingabe.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     *
     * @throws BudgetExceededException
     */
    public function embedMany(array $texts, string $inputType = self::INPUT_DOCUMENT, ?LlmContext $context = null): array
    {
        $texts = array_values(array_filter(
            array_map(fn ($text) => is_string($text) ? trim($text) : '', $texts),
            fn (string $text) => $text !== '',
        ));

        if ($texts === []) {
            return [];
        }

        $context = ($context ?? LlmContext::current())->withOperation($context?->operation ?? 'embedding');

        // Toter Zugang (#104): erschoepftes Guthaben, abgelehnter Schluessel.
        ProviderAccountGuard::assertUsable(self::PROVIDER);

        $this->budget->check(
            self::PROVIDER,
            $context->tenantId,
            $context->referenceType,
            $context->referenceId,
        );

        $vectors = [];

        foreach (array_chunk($texts, max(1, (int) $this->option('batch_size', 128))) as $chunk) {
            foreach ($this->request($chunk, $inputType, $context) as $vector) {
                $vectors[] = $vector;
            }
        }

        return $vectors;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    private function request(array $texts, string $inputType, LlmContext $context): array
    {
        $payload = [
            'model' => (string) $this->option('model', 'voyage-3.5'),
            'input' => $texts,
            'input_type' => $inputType,
        ];

        $dimensions = (int) $this->option('dimensions', 0);

        if ($dimensions > 0) {
            $payload['output_dimension'] = $dimensions;
        }

        $startedAt = microtime(true);

        try {
            $response = $this->http()->post('/embeddings', $payload);
        } catch (\Throwable $exception) {
            $this->log($context, 0, $this->elapsed($startedAt), false, $exception->getMessage(), count($texts));

            throw $exception;
        }

        $durationMs = $this->elapsed($startedAt);

        if ($response->failed()) {
            $this->log($context, 0, $durationMs, false, $this->errorMessage($response), count($texts));

            $reason = ProviderAccountGuard::classify($response);

            if ($reason !== null) {
                throw ProviderAccountGuard::reportFailure(self::PROVIDER, $reason, $this->errorMessage($response));
            }

            $response->throw();
        }

        ProviderAccountGuard::reportSuccess(self::PROVIDER);

        $body = (array) $response->json();
        $tokens = (int) ($body['usage']['total_tokens'] ?? 0);

        $this->log($context, $tokens, $durationMs, true, null, count($texts));

        return $this->vectors($body);
    }

    /**
     * Voyage liefert die Vektoren mit 'index'; die Reihenfolge der Eingabe wird
     * darueber wiederhergestellt statt sich auf die Antwortreihenfolge zu
     * verlassen.
     *
     * @param  array<string, mixed>  $body
     * @return array<int, array<int, float>>
     */
    private function vectors(array $body): array
    {
        $indexed = [];

        foreach ((array) ($body['data'] ?? []) as $position => $entry) {
            if (! is_array($entry) || ! is_array($entry['embedding'] ?? null)) {
                continue;
            }

            $index = (int) ($entry['index'] ?? $position);
            $indexed[$index] = array_map(static fn ($value) => (float) $value, $entry['embedding']);
        }

        ksort($indexed);

        return array_values($indexed);
    }

    private function http(): PendingRequest
    {
        $apiKey = (string) $this->option('api_key', '');

        if ($apiKey === '') {
            throw new InvalidArgumentException('VOYAGE_API_KEY ist nicht gesetzt.');
        }

        $retry = (array) config('content.resilience.retry', []);

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://api.voyageai.com/v1'), '/'))
            ->withToken($apiKey)
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

    private function log(
        LlmContext $context,
        int $tokens,
        int $durationMs,
        bool $successful,
        ?string $error,
        int $requests,
    ): void {
        $cost = $this->costFor($tokens);

        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => (string) $this->option('model', 'voyage-3.5'),
            'operation' => Str::limit($context->operation ?? 'embedding', 64, ''),
            'reference_type' => $context->referenceType,
            'reference_id' => $context->referenceId,
            'input_tokens' => $tokens,
            'output_tokens' => 0,
            'requests' => max(1, $requests),
            'cost_usd' => $cost,
            'duration_ms' => $durationMs,
            'was_successful' => $successful,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        $this->budget->record(self::PROVIDER, $cost);

        Log::debug('Voyage-Aufruf abgeschlossen.', [
            'tenant_id' => $context->tenantId,
            'tokens' => $tokens,
            'cost_usd' => $cost,
            'duration_ms' => $durationMs,
        ]);
    }

    public function costFor(int $tokens): float
    {
        $perMillion = (float) $this->option('pricing.input_per_mtok', 0.0);

        return round($tokens * $perMillion / 1_000_000, 6);
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.voyage.{$key}", $default);
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function errorMessage(Response $response): string
    {
        $body = (array) $response->json();
        $message = $body['detail'] ?? $body['error']['message'] ?? $response->body();

        return "HTTP {$response->status()}: ".(is_string($message) ? $message : json_encode($message));
    }
}
