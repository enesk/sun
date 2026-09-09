<?php

declare(strict_types=1);

namespace App\Content\Providers;

use App\Content\Llm\BudgetGuard;
use App\Content\Llm\LlmContext;
use App\Content\Models\Central\LlmUsageLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Bildgenerierung ueber fal.ai (Flux), #16.
 *
 * Aufbau wie beim DataForSeoClient: der Client prueft vor dem Aufruf das
 * Budget des Providers 'images' (config('content.budget.provider_share')),
 * protokolliert jeden Aufruf mit seinem Pauschalpreis in llm_usage_logs und
 * gibt am Ende die fertigen Bildbytes zurueck.
 *
 * Zwei Abweichungen, beide beabsichtigt:
 *
 *  - Kein Zwischenspeicher. Zwei Artikel mit demselben Prompt gibt es nicht,
 *    und ein wiederholter Lauf soll ein neues Bild bekommen.
 *  - Kein Kontingent je Endpunkt. Die Menge begrenzt der Aufrufer: ein
 *    Titelbild je freigegebenem Entwurf.
 *
 * fal.ai antwortet auf /fal-ai/flux/schnell synchron mit einer Bild-URL;
 * die Datei wird anschliessend geladen. Sie liegt bei fal nur kurz, deshalb
 * passiert das im selben Aufruf.
 */
final class FalClient
{
    /** Budget- und Log-Schluessel. Siehe config('content.budget.provider_share'). */
    public const PROVIDER = 'images';

    public const OPERATION = 'hero_image';

    /** Groesse, ab der eine Antwort nicht mehr plausibel ein Bild ist. */
    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly BudgetGuard $budget,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    /**
     * Erzeugt ein Bild und liefert die Rohbytes.
     *
     * @throws RuntimeException wenn fal.ai nicht antwortet oder kein Bild liefert
     */
    public function generate(string $prompt, ?LlmContext $context = null): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('FAL_API_KEY ist nicht gesetzt.');
        }

        $context = ($context ?? LlmContext::current())->withOperation(self::OPERATION);
        $costUsd = (float) $this->option('pricing.per_image', 0.0);

        // Wirft BudgetExceededException, bevor Kosten entstehen.
        $this->budget->check(self::PROVIDER, $context->tenantId, $context->referenceType, $context->referenceId);

        $model = trim((string) $this->option('model', 'fal-ai/flux/schnell'), '/');
        $startedAt = microtime(true);

        try {
            $response = $this->http()->post('/'.$model, [
                'prompt' => $prompt,
                'image_size' => (string) $this->option('image_size', 'landscape_16_9'),
                'num_images' => 1,
                'num_inference_steps' => max(1, (int) $this->option('num_inference_steps', 4)),
                // Flux liefert ohne diese Angabe gelegentlich PNG; WebP wird
                // ohnehin neu kodiert, JPEG spart aber den Download.
                'output_format' => 'jpeg',
                'enable_safety_checker' => true,
            ]);

            if ($response->failed()) {
                throw new RuntimeException($this->errorMessage($response->status(), $response->body()));
            }

            $url = $this->imageUrl((array) $response->json());
            $binary = $this->download($url);
        } catch (\Throwable $exception) {
            // fal rechnet auch abgebrochene Laeufe ab, sobald sie gestartet
            // sind — der Aufruf wird deshalb auch im Fehlerfall verbucht.
            $this->record($context, $model, $costUsd, $this->elapsed($startedAt), false, $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $model, $costUsd, $this->elapsed($startedAt), true, null);

        return $binary;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function imageUrl(array $body): string
    {
        $image = (array) (($body['images'] ?? [])[0] ?? []);
        $url = (string) ($image['url'] ?? '');

        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('fal.ai liefert keine Bild-URL.');
        }

        return $url;
    }

    private function download(string $url): string
    {
        $response = Http::timeout((int) $this->option('timeout', 120))->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Bild von fal.ai nicht ladbar: HTTP '.$response->status());
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Bild von fal.ai ist leer oder unplausibel gross.');
        }

        return $binary;
    }

    private function record(
        LlmContext $context,
        string $model,
        float $costUsd,
        int $durationMs,
        bool $successful,
        ?string $error,
    ): void {
        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => Str::limit($model, 64, ''),
            'operation' => Str::limit($context->operation ?? self::OPERATION, 64, ''),
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
    }

    private function http(): PendingRequest
    {
        $retry = (array) config('content.resilience.retry', []);

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://fal.run'), '/'))
            ->withHeaders(['Authorization' => 'Key '.$this->apiKey()])
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 120))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                (int) ($retry['base_delay_ms'] ?? 2000),
                fn (\Throwable $exception) => ! $exception instanceof \Illuminate\Http\Client\RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    private function errorMessage(int $status, string $body): string
    {
        return "fal.ai antwortet mit HTTP {$status}: ".Str::limit($body, 200, '');
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function apiKey(): string
    {
        return trim((string) $this->option('api_key', ''));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.images.fal.{$key}", $default);
    }
}
