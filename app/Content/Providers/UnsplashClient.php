<?php

declare(strict_types=1);

namespace App\Content\Providers;

use App\Content\Llm\BudgetGuard;
use App\Content\Llm\LlmContext;
use App\Content\Models\Central\LlmUsageLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Rueckfallquelle fuer Titelbilder: die Unsplash-Suche (#16).
 *
 * Zwei Dinge sind hier Pflicht und nicht verhandelbar, beide stehen so in den
 * API-Richtlinien von Unsplash:
 *
 *  1. Attribution. Fotograf:in und Unsplash werden genannt, jeweils mit Link
 *     und utm-Parametern. Der fertige Text landet in
 *     article_drafts.hero_image_credit und wird im Artikel ausgegeben.
 *  2. Download-Trigger. Wird ein Bild tatsaechlich verwendet, muss der
 *     `download_location`-Endpunkt des Treffers angestossen werden. Das ist
 *     kein Dateidownload, sondern die Zaehlung beim Anbieter; sie darf nie
 *     den Job scheitern lassen.
 *
 * Die Suche selbst kostet nichts (pricing.per_image = 0.0), wird aber wie
 * jeder Provider-Aufruf in llm_usage_logs protokolliert — sonst fehlt sie
 * spaeter in der Herkunftsauswertung.
 */
final class UnsplashClient
{
    public const PROVIDER = 'images';

    public const OPERATION = 'hero_image_stock';

    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly BudgetGuard $budget,
    ) {}

    public function isConfigured(): bool
    {
        return $this->accessKey() !== '';
    }

    /**
     * Erstes brauchbares Suchergebnis: Rohbytes plus Attribution.
     *
     * @return array{binary: string, credit: string, credit_html: string, photographer: string, url: string, description: ?string}
     *
     * @throws RuntimeException wenn die Suche nichts Verwendbares liefert
     */
    public function search(string $query, ?LlmContext $context = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('UNSPLASH_ACCESS_KEY ist nicht gesetzt.');
        }

        $context = ($context ?? LlmContext::current())->withOperation(self::OPERATION);
        $startedAt = microtime(true);

        try {
            $response = $this->http()->get('/search/photos', [
                'query' => $query,
                'per_page' => max(1, (int) $this->option('per_page', 10)),
                'orientation' => (string) $this->option('orientation', 'landscape'),
                'content_filter' => (string) $this->option('content_filter', 'high'),
            ]);

            if ($response->failed()) {
                throw new RuntimeException('Unsplash antwortet mit HTTP '.$response->status().': '.Str::limit($response->body(), 200, ''));
            }

            $photo = $this->pick((array) $response->json());
            $binary = $this->download((string) ($photo['urls']['raw'] ?? $photo['urls']['full'] ?? ''));
        } catch (\Throwable $exception) {
            $this->record($context, $this->elapsed($startedAt), false, $exception->getMessage());

            throw $exception;
        }

        $this->record($context, $this->elapsed($startedAt), true, null);
        $this->triggerDownload($photo);

        return [
            'binary' => $binary,
            'credit' => $this->credit($photo),
            'credit_html' => $this->creditHtml($photo),
            'photographer' => (string) ($photo['user']['name'] ?? 'Unsplash'),
            'url' => (string) ($photo['links']['html'] ?? 'https://unsplash.com'),
            'description' => $this->description($photo),
        ];
    }

    /**
     * Der erste Treffer mit ausreichender Breite. Ein zu kleines Bild waere
     * nach dem Zuschnitt auf 1200 px unscharf.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function pick(array $body): array
    {
        $minWidth = max(600, (int) config('content.assets.hero.min_source_width', 1200));

        foreach ((array) ($body['results'] ?? []) as $photo) {
            if (! is_array($photo)) {
                continue;
            }

            if ((int) ($photo['width'] ?? 0) < $minWidth) {
                continue;
            }

            if (! is_string($photo['urls']['raw'] ?? null) && ! is_string($photo['urls']['full'] ?? null)) {
                continue;
            }

            return $photo;
        }

        throw new RuntimeException('Unsplash liefert kein ausreichend grosses Querformat.');
    }

    private function download(string $url): string
    {
        if (! str_starts_with($url, 'https://')) {
            throw new RuntimeException('Unsplash-Treffer ohne Bild-URL.');
        }

        // Die raw-URL nimmt Bildparameter entgegen; damit kommt schon in der
        // Zielbreite an, was sonst als 20-Megapixel-Datei geladen wuerde.
        $width = max(600, (int) config('content.assets.hero.min_source_width', 1200));
        $separator = str_contains($url, '?') ? '&' : '?';

        $response = Http::timeout((int) $this->option('timeout', 30))
            ->get($url."{$separator}w={$width}&fm=jpg&q=90&fit=max");

        if ($response->failed()) {
            throw new RuntimeException('Unsplash-Bild nicht ladbar: HTTP '.$response->status());
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Unsplash-Bild ist leer oder unplausibel gross.');
        }

        return $binary;
    }

    /**
     * Pflichtzaehlung beim Anbieter. Scheitert sie, ist das eine Meldung im
     * Log und kein Grund, das Bild nicht zu verwenden.
     *
     * @param  array<string, mixed>  $photo
     */
    private function triggerDownload(array $photo): void
    {
        if (! (bool) $this->option('trigger_download', true)) {
            return;
        }

        $location = (string) ($photo['links']['download_location'] ?? '');

        if (! str_starts_with($location, 'https://')) {
            return;
        }

        try {
            $this->http()->get($location);
        } catch (\Throwable $exception) {
            Log::info('Unsplash-Download-Trigger fehlgeschlagen.', [
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Attributionstext, wie er unter dem Bild steht.
     *
     * @param  array<string, mixed>  $photo
     */
    private function credit(array $photo): string
    {
        $name = trim((string) ($photo['user']['name'] ?? '')) ?: 'Unsplash';

        return "Foto: {$name} / Unsplash";
    }

    /**
     * Dieselbe Attribution mit den von Unsplash geforderten Links.
     *
     * @param  array<string, mixed>  $photo
     */
    private function creditHtml(array $photo): string
    {
        $utm = 'utm_source='.rawurlencode((string) $this->option('utm_source', 'sun_ratgeber')).'&utm_medium=referral';
        $name = trim((string) ($photo['user']['name'] ?? '')) ?: 'Unsplash';
        $profile = (string) ($photo['user']['links']['html'] ?? 'https://unsplash.com');

        return 'Foto: <a href="'.e($profile.'?'.$utm).'" rel="nofollow noopener">'.e($name).'</a>'
            .' / <a href="'.e('https://unsplash.com/?'.$utm).'" rel="nofollow noopener">Unsplash</a>';
    }

    /**
     * Bildbeschreibung des Anbieters. Sie geht als Kontext in den Alt-Text.
     *
     * @param  array<string, mixed>  $photo
     */
    private function description(array $photo): ?string
    {
        foreach (['alt_description', 'description'] as $key) {
            $value = trim((string) ($photo[$key] ?? ''));

            if ($value !== '') {
                return Str::limit($value, 200, '');
            }
        }

        return null;
    }

    private function record(LlmContext $context, int $durationMs, bool $successful, ?string $error): void
    {
        $costUsd = (float) $this->option('pricing.per_image', 0.0);

        LlmUsageLog::create([
            'tenant_id' => $context->tenantId,
            'provider' => self::PROVIDER,
            'model' => 'unsplash',
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

        return Http::baseUrl(rtrim((string) $this->option('base_url', 'https://api.unsplash.com'), '/'))
            ->withHeaders([
                'Authorization' => 'Client-ID '.$this->accessKey(),
                'Accept-Version' => 'v1',
            ])
            ->acceptJson()
            ->timeout((int) $this->option('timeout', 30))
            ->retry(
                (int) ($retry['attempts'] ?? 3),
                (int) ($retry['base_delay_ms'] ?? 2000),
                fn (\Throwable $exception) => ! $exception instanceof \Illuminate\Http\Client\RequestException
                    || $exception->response->status() >= 500
                    || $exception->response->status() === 429,
                throw: false,
            );
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function accessKey(): string
    {
        return trim((string) $this->option('access_key', ''));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.providers.images.unsplash.{$key}", $default);
    }
}
