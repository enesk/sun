<?php

declare(strict_types=1);

namespace App\Guide\Providers;

use App\Guide\Llm\BudgetGuard;
use App\Guide\Llm\ContentBudgetGuard;
use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Models\Central\LlmUsageLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Bildgenerierung ueber fal.ai (Flux) fuer das Ratgebersystem (#20).
 *
 * Uebernommen aus dem alten Content-FalClient (#16, entfernt mit #35), aber an Budget und
 * Protokoll des Ratgebersystems gebunden: vor dem Aufruf prueft der Guide-
 * BudgetGuard die Grenzen aus config('guide.budget'), jeder Aufruf landet mit
 * seinem Pauschalpreis in llm_usage_logs (provider 'images', operation
 * 'guide.hero_image') und zaehlt damit in dieselben Tagessummen.
 *
 * fal.ai antwortet synchron mit einer Bild-URL; die Datei liegt dort nur kurz
 * und wird deshalb im selben Aufruf geladen.
 */
final class FalClient
{
    public const PROVIDER = 'images';

    public const OPERATION = BudgetGuard::OPERATION_PREFIX.'hero_image';

    /** Groesse, ab der eine Antwort nicht mehr plausibel ein Bild ist. */
    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly BudgetGuard $budget,
        private readonly ContentBudgetGuard $providerBudget,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * Erzeugt ein Bild und liefert die Rohbytes.
     *
     * @throws BudgetExceededException bevor Kosten entstehen
     * @throws RuntimeException wenn fal.ai nicht antwortet oder kein Bild liefert
     */
    public function generate(string $prompt, LlmCallContext $context): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FAL_API_KEY ist nicht gesetzt.');
        }

        $this->budget->check($context);

        $model = trim((string) $this->option('model', 'fal-ai/flux/schnell'), '/');
        $costUsd = (float) $this->option('per_image_usd', 0.0);
        $startedAt = microtime(true);

        try {
            $response = $this->http()->post("/{$model}", [
                'prompt' => $prompt,
                'image_size' => (string) $this->option('image_size', 'landscape_16_9'),
                'num_images' => 1,
                'num_inference_steps' => max(1, (int) $this->option('num_inference_steps', 4)),
                'output_format' => 'jpeg',
                'enable_safety_checker' => true,
            ]);

            if ($response->failed()) {
                throw new RuntimeException("fal.ai antwortet mit HTTP {$response->status()}: ".Str::limit($response->body(), 200, ''));
            }

            $binary = $this->download($this->imageUrl((array) $response->json()));
        } catch (Throwable $exception) {
            // fal rechnet gestartete Laeufe auch bei Abbruch ab.
            $this->record($context, $model, $costUsd, $startedAt, $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $model, $costUsd, $startedAt);

        return $binary;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function imageUrl(array $body): string
    {
        $url = (string) (((array) (($body['images'] ?? [])[0] ?? []))['url'] ?? '');

        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('fal.ai liefert keine Bild-URL.');
        }

        return $url;
    }

    private function download(string $url): string
    {
        $response = Http::timeout((int) $this->option('timeout', 120))->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Bild von fal.ai nicht ladbar: HTTP {$response->status()}");
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Bild von fal.ai ist leer oder unplausibel gross.');
        }

        return $binary;
    }

    private function record(LlmCallContext $context, string $model, float $costUsd, float $startedAt, ?string $error = null): void
    {
        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => Str::limit($model, 64, ''),
            'operation' => self::OPERATION,
            'reference_type' => $context->runId === null ? null : LlmCallContext::REFERENCE_RUN,
            'reference_id' => $context->runId,
            'guide_topic_id' => $context->topicId,
            'template_key' => null,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'requests' => 1,
            'cost_usd' => $costUsd,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'was_successful' => $error === null,
            'error_message' => $error === null ? null : Str::limit($error, 500, ''),
        ]);

        // Anzeigezaehler in provider_states (Quellen-Monitor).
        $this->providerBudget->record(self::PROVIDER, $costUsd);
    }

    private function http(): PendingRequest
    {
        $retry = (array) config('guide.resilience.retry', []);

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://fal.run'), '/'))
            ->withHeaders(['Authorization' => "Key {$this->apiKey()}"])
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 120))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                (int) ($retry['base_delay_ms'] ?? 2000),
                fn (Throwable $exception): bool => ! $exception instanceof RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    private function apiKey(): string
    {
        return trim((string) $this->option('api_key', ''));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("guide.images.fal.{$key}", $default);
    }
}
