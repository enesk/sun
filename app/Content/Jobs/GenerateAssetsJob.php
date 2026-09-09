<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Assets\AltTextGenerator;
use App\Content\Assets\AssetStorage;
use App\Content\Assets\HeroImageGenerator;
use App\Content\Assets\ImageOptimizer;
use App\Content\Assets\InfographicRenderer;
use App\Content\Enums\DraftStatus;
use App\Content\Llm\LlmContext;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
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
 * Titelbild und Infografik eines freigegebenen Entwurfs (#16).
 *
 * Der Job laeuft nach dem Qualitaetsgate und macht drei Dinge:
 *
 *  1. Titelbild. Der HeroImageGenerator versucht fal.ai (Flux), dann die
 *     Unsplash-Suche, zuletzt das Branchen-Standardbild. Das Ergebnis wird in
 *     drei Breiten als WebP abgelegt (config('content.assets.widths')); die
 *     groesste bleibt unter 150 KB.
 *  2. Infografik. Die Key-Facts-Tabelle noch einmal als SVG, server-seitig
 *     und ohne Modellaufruf, in den Farben des Mandanten.
 *  3. Alt-Text. Ein Satz mit claude-sonnet-5 aus Titel, Branche, Region und
 *     Bildbeschreibung; ohne Modellantwort ein deterministischer Ersatztext.
 *
 * Grundregel: Assets sind Beiwerk. Keine der drei Stufen darf einen
 * freigegebenen Artikel zurueckwerfen — der Entwurf bleibt in jedem Fall
 * `approved`, und der Publisher (#21) wird auch dann angestossen, wenn kein
 * einziges Bild entstanden ist. Was gescheitert ist, steht in
 * `assets_json.errors` und ist damit im Content-Panel sichtbar.
 */
class GenerateAssetsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Wiederholungen macht der Provider-Layer (Retry, Circuit Breaker). Ein
     * Queue-Retry wuerde ein zweites Bild bezahlen.
     */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $tenantId,
        public int $draftId,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.assets', 'content-assets'));
    }

    public function uniqueId(): string
    {
        return "content-generate-assets:{$this->tenantId}:{$this->draftId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        HeroImageGenerator $hero,
        ImageOptimizer $optimizer,
        InfographicRenderer $infographic,
        AltTextGenerator $altText,
        AssetStorage $storage,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($tenant, $hero, $optimizer, $infographic, $altText, $storage): void {
            $draft = ArticleDraft::query()->find($this->draftId);

            if ($draft === null) {
                Log::warning('Asset-Erzeugung ohne Entwurf.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $this->draftId,
                ]);

                return;
            }

            // Der Job haengt an der Freigabe. Ein Entwurf, der inzwischen
            // wieder in der Pruefung steht, bekommt keine Bilder.
            if (! in_array($draft->status, [DraftStatus::APPROVED, DraftStatus::SCHEDULED, DraftStatus::PUBLISHED], true)) {
                Log::info('Asset-Erzeugung uebersprungen, Status passt nicht.', [
                    'tenant_id' => $this->tenantId,
                    'draft_id' => $draft->getKey(),
                    'status' => $draft->status->value,
                ]);

                return;
            }

            $settings = TenantContentSetting::current();
            [$branch, $branchLabel] = HeroImageGenerator::branchOf($tenant);

            $assets = (array) ($draft->assets_json ?? []);
            $assets['errors'] = [];
            $assets['generated_at'] = now()->toIso8601String();
            $assets['branch'] = $branch;

            $attributes = [];

            $this->buildHero($draft, $tenant, $hero, $optimizer, $altText, $storage, $branch, $branchLabel, $assets, $attributes);
            $this->buildInfographic($draft, $tenant, $infographic, $storage, $settings, $assets, $attributes);

            $attributes['assets_json'] = $assets;
            $draft->forceFill($attributes)->save();

            Log::info('Assets erzeugt.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'hero_source' => $draft->hero_image_source,
                'errors' => count($assets['errors']),
            ]);

            $this->dispatchFollowUps($tenant, $draft);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Titelbild
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>  $attributes
     */
    private function buildHero(
        ArticleDraft $draft,
        Tenant $tenant,
        HeroImageGenerator $hero,
        ImageOptimizer $optimizer,
        AltTextGenerator $altText,
        AssetStorage $storage,
        ?string $branch,
        string $branchLabel,
        array &$assets,
        array &$attributes,
    ): void {
        $context = LlmContext::forDraftId((int) $draft->getKey(), $this->tenantId);

        try {
            $image = $hero->generate($draft, $branch, $branchLabel, $context);
        } catch (Throwable $exception) {
            $assets['errors'][] = 'hero: '.$exception->getMessage();

            Log::error('Titelbild konnte nicht beschafft werden.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if ($image === null) {
            $assets['errors'][] = 'hero: keine Bildquelle verfuegbar';

            return;
        }

        try {
            $variants = $optimizer->variants($image['binary']);
        } catch (Throwable $exception) {
            $assets['errors'][] = 'hero_optimize: '.$exception->getMessage();

            Log::error('Titelbild nicht konvertierbar.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        $slug = (string) ($draft->slug ?: $draft->title);
        $stored = [];

        foreach ($variants as $variant) {
            $path = $storage->put($tenant, $slug, "hero-{$variant['width']}.webp", $variant['binary']);

            $stored[] = [
                'path' => $path,
                'url' => $storage->url($path),
                'width' => $variant['width'],
                'height' => $variant['height'],
                'bytes' => $variant['bytes'],
                'quality' => $variant['quality'],
            ];
        }

        if ((bool) ($variants[0]['over_limit'] ?? false)) {
            $limit = (int) config('content.assets.hero_max_bytes', 153600);
            $assets['errors'][] = 'hero_size: '.$variants[0]['bytes']." Byte ueber der Grenze von {$limit} Byte";

            Log::warning('Titelbild bleibt ueber der Groessengrenze.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'bytes' => $variants[0]['bytes'],
                'limit' => $limit,
            ]);
        }

        $assets['hero'] = [
            'source' => $image['source'],
            'prompt' => $image['prompt'],
            'variants' => $stored,
        ];

        $attributes['hero_image_path'] = $stored[0]['path'];
        $attributes['hero_image_source'] = $image['source'];
        $attributes['hero_image_credit'] = $image['credit'];
        $attributes['hero_image_alt'] = $altText->generate($draft, $branchLabel, [
            'source' => $image['source'],
            'prompt' => $image['prompt'],
            'description' => $image['description'],
        ], $context);

        // Die verlinkte Fassung der Attribution gehoert nicht in eine
        // Textspalte; sie steht daneben und wird im Template ausgegeben.
        $assets['hero']['credit_html'] = $image['credit_html'];
    }

    /*
    |--------------------------------------------------------------------------
    | Infografik
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $assets
     * @param  array<string, mixed>  $attributes
     */
    private function buildInfographic(
        ArticleDraft $draft,
        Tenant $tenant,
        InfographicRenderer $renderer,
        AssetStorage $storage,
        TenantContentSetting $settings,
        array &$assets,
        array &$attributes,
    ): void {
        try {
            $svg = $renderer->render($tenant, $draft, $settings);
        } catch (Throwable $exception) {
            $assets['errors'][] = 'infographic: '.$exception->getMessage();

            Log::error('Infografik konnte nicht erzeugt werden.', [
                'tenant_id' => $this->tenantId,
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return;
        }

        if ($svg === null) {
            // Kein Fehler: ein Artikel ohne tabellarische Fakten bekommt keine
            // Infografik.
            $assets['infographic'] = null;

            return;
        }

        $path = $storage->put($tenant, (string) ($draft->slug ?: $draft->title), 'infografik.svg', $svg);

        $assets['infographic'] = [
            'path' => $path,
            'url' => $storage->url($path),
            'bytes' => strlen($svg),
        ];

        $attributes['infographic_svg_path'] = $path;
    }

    /*
    |--------------------------------------------------------------------------
    | Kette
    |--------------------------------------------------------------------------
    */

    /**
     * Der naechste Job der Kette. Wie ueberall in der Pipeline stoesst jeder
     * Job seinen Nachfolger selbst an, nachdem sein Ergebnis persistiert ist;
     * noch nicht existierende Folgejobs (#21) werden uebersprungen.
     */
    private function dispatchFollowUps(Tenant $tenant, ArticleDraft $draft): void
    {
        $job = 'App\\Content\\Jobs\\ScheduleAndPublishJob';

        if (! class_exists($job)) {
            return;
        }

        try {
            $job::dispatch((int) $tenant->getKey(), (int) $draft->getKey());
        } catch (Throwable $exception) {
            Log::error('Asset-Erzeugung: Folgejob konnte nicht angestossen werden.', [
                'job' => $job,
                'tenant_id' => $tenant->getKey(),
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
