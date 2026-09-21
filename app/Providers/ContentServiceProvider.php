<?php

declare(strict_types=1);

namespace App\Providers;

use App\Content\Assets\AltTextGenerator;
use App\Content\Assets\AssetStorage;
use App\Content\Assets\HeroImageGenerator;
use App\Content\Assets\ImageOptimizer;
use App\Content\Assets\InfographicRenderer;
use App\Content\Events\ContentRepublished;
use App\Content\Events\ContentWithdrawn;
use App\Content\Llm\BudgetGuard;
use App\Content\Llm\ClaudeCliTransport;
use App\Content\Llm\EmbeddingClient;
use App\Content\Llm\LlmClient;
use App\Content\Llm\PromptRenderer;
use App\Content\Llm\SchemaValidator;
use App\Content\Models\Central\ContentFingerprint;
use App\Content\Providers\AdSenseClient;
use App\Content\Providers\DataForSeoClient;
use App\Content\Providers\FalClient;
use App\Content\Providers\IndexNowClient;
use App\Content\Providers\SearchConsoleClient;
use App\Content\Providers\UnsplashClient;
use App\Content\Services\ArticleBlockPresenter;
use App\Content\Services\ArticleMapper;
use App\Content\Services\CompetitorOutlineExtractor;
use App\Content\Services\FingerprintService;
use App\Content\Services\LlmsTxtBuilder;
use App\Content\Services\PerformanceAggregator;
use App\Content\Services\PortalDataProvider;
use App\Content\Services\Publisher;
use App\Content\Services\PublishScheduler;
use App\Content\Services\RatgeberSitemapGenerator;
use App\Content\Services\SerpInsightService;
use App\Content\Sources\Connectors\DataForSeoTrendsConnector;
use App\Content\Sources\Connectors\DummyPingConnector;
use App\Content\Sources\Connectors\FoerderdatenbankConnector;
use App\Content\Sources\Connectors\GoogleNewsRssConnector;
use App\Content\Sources\Connectors\GoogleTrendsRssConnector;
use App\Content\Sources\Connectors\PortalDataConnector;
use App\Content\Sources\Connectors\RssFeedConnector;
use App\Content\Sources\Connectors\SearchConsoleGapConnector;
use App\Content\Sources\Connectors\SeasonalCalendarConnector;
use App\Content\Sources\Connectors\StatisticsConnector;
use App\Content\Sources\SourceRegistry;
use App\Content\Sources\SourceRunner;
use App\Content\Sources\Support\FactSnippetWriter;
use App\Content\Sources\Support\RegionResolver;
use App\Models\Portal\Post;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registrierung der Content-Pipeline (#7 ff.).
 *
 * Quell-Connectoren werden ausschliesslich hier als getaggte Services
 * eingetragen. Registry, Runner, Job und Command finden sie ueber das Tag;
 * kein Connector wird irgendwo namentlich verdrahtet.
 */
class ContentServiceProvider extends ServiceProvider
{
    public const SOURCE_CONNECTOR_TAG = 'content.source_connectors';

    /**
     * Registrierte Quell-Connectoren. Neue Connectoren (#8-#11) kommen hier
     * dazu, jeweils mit einem Schalter, falls sie Zugangsdaten brauchen.
     *
     * @return array<int, class-string>
     */
    private function connectors(): array
    {
        return array_values(array_filter([
            config('content.sources.dummy_enabled') ? DummyPingConnector::class : null,
            config('content.sources.gsc_gap.enabled') ? SearchConsoleGapConnector::class : null,
            config('content.sources.google_trends_rss.enabled') ? GoogleTrendsRssConnector::class : null,
            $this->dataForSeoTrendsEnabled() ? DataForSeoTrendsConnector::class : null,
            config('content.sources.rss_feeds.enabled') ? RssFeedConnector::class : null,
            config('content.sources.foerderdatenbank.enabled') ? FoerderdatenbankConnector::class : null,
            config('content.sources.statistics.enabled') ? StatisticsConnector::class : null,
            config('content.sources.google_news_rss.enabled') ? GoogleNewsRssConnector::class : null,
            config('content.sources.seasonal_calendar.enabled') ? SeasonalCalendarConnector::class : null,
            config('content.sources.portal_data.enabled') ? PortalDataConnector::class : null,
        ]));
    }

    /**
     * Der Trends-Connector (#8) kostet Geld und braucht Zugangsdaten. Ohne
     * DATAFORSEO_LOGIN/-PASSWORD wird er gar nicht erst registriert, damit ein
     * halb konfiguriertes Staging keinen Provider-Alarm ausloest.
     */
    private function dataForSeoTrendsEnabled(): bool
    {
        return (bool) config('content.sources.dataforseo_trends.enabled')
            && (string) config('content.providers.dataforseo.login', '') !== ''
            && (string) config('content.providers.dataforseo.password', '') !== '';
    }

    public function register(): void
    {
        foreach ($this->connectors() as $connector) {
            $this->app->singleton($connector);
        }

        $this->app->tag($this->connectors(), self::SOURCE_CONNECTOR_TAG);

        $this->app->singleton(
            SourceRegistry::class,
            fn ($app) => new SourceRegistry($app->tagged(self::SOURCE_CONNECTOR_TAG)),
        );

        $this->app->singleton(
            SourceRunner::class,
            fn ($app) => new SourceRunner($app->make(SourceRegistry::class)),
        );

        $this->registerSourceServices();
        $this->registerLlm();
        $this->registerAssets();
        $this->registerPublishing();
    }

    /**
     * Veroeffentlichung (#21). Zustandslose Dienste; der Publisher haelt
     * nichts als seine vier Mitarbeiter.
     */
    private function registerPublishing(): void
    {
        $this->app->singleton(IndexNowClient::class, fn () => new IndexNowClient);
        $this->app->singleton(PublishScheduler::class, fn () => new PublishScheduler);
        $this->app->singleton(FingerprintService::class, fn ($app) => new FingerprintService(
            $app->make(EmbeddingClient::class),
        ));
        $this->app->singleton(Publisher::class, fn ($app) => new Publisher(
            $app->make(ArticleMapper::class),
            $app->make(FingerprintService::class),
            $app->make(IndexNowClient::class),
            $app->make(RatgeberSitemapGenerator::class),
        ));
    }

    /**
     * Cache-Invalidierung der KI-Dateien (#18).
     *
     * /llms.txt und /llms-full.txt liegen eine Stunde im Cache. Jede
     * Veroeffentlichung, Aenderung oder Zuruecknahme eines Beitrags verwirft
     * ihn sofort, damit die Liste nicht bis zu einer Stunde hinterherhinkt.
     */
    public function boot(): void
    {
        Post::saved(fn () => LlmsTxtBuilder::flush());
        Post::deleted(function (Post $post): void {
            LlmsTxtBuilder::flush();

            // Ein geloeschter Beitrag darf keinen Fingerprint in der
            // Central-DB zuruecklassen (#21): er wuerde als Duplikat gegen
            // kuenftige Themen zaehlen und auf eine tote URL verlinken.
            $tenant = tenant();

            if ($tenant instanceof Tenant) {
                ContentFingerprint::query()
                    ->forTenant((int) $tenant->getKey())
                    ->where('article_id', (int) $post->getKey())
                    ->delete();
            }
        });

        Event::listen([ContentWithdrawn::class, ContentRepublished::class], fn () => LlmsTxtBuilder::flush());
    }

    /**
     * Dienste, die einzelne Connectoren brauchen (#9 ff.). Zustandslos bis auf
     * den Cache, deshalb Singletons.
     */
    private function registerSourceServices(): void
    {
        $this->app->singleton(SearchConsoleClient::class, fn () => new SearchConsoleClient);
        $this->app->singleton(DataForSeoClient::class, fn ($app) => new DataForSeoClient(
            $app->make(BudgetGuard::class),
        ));
        $this->app->singleton(RegionResolver::class, fn () => new RegionResolver);

        // Faktenschnipsel (#11): schreiben Statistik-Connector und
        // PortalDataProvider ueber dieselbe Stelle.
        $this->app->singleton(FactSnippetWriter::class, fn () => new FactSnippetWriter);
        $this->app->singleton(PortalDataProvider::class, fn ($app) => new PortalDataProvider(
            $app->make(FactSnippetWriter::class),
        ));

        // SERP-Analyse (#10). Laeuft nicht im Scheduler, sondern auf Zuruf
        // aus Scoring (#12) und Generator (#14).
        $this->app->singleton(CompetitorOutlineExtractor::class, fn () => new CompetitorOutlineExtractor);
        $this->app->singleton(SerpInsightService::class, fn ($app) => new SerpInsightService(
            $app->make(DataForSeoClient::class),
            $app->make(CompetitorOutlineExtractor::class),
        ));

        // Metrik-Collector und Lernschleife (#23). Der AdSense-Zugang ist
        // optional: ohne Freischaltung liefert er ein leeres Ergebnis, statt
        // den Collector scheitern zu lassen.
        $this->app->singleton(AdSenseClient::class, fn ($app) => new AdSenseClient(
            $app->make(BudgetGuard::class),
        ));
        $this->app->singleton(PerformanceAggregator::class, fn () => new PerformanceAggregator);
    }

    /**
     * Titelbild und Infografik (#16). Alle Dienste sind zustandslos; ihre
     * Ablage kennt nur der AssetStorage.
     */
    private function registerAssets(): void
    {
        $this->app->singleton(FalClient::class, fn ($app) => new FalClient(
            $app->make(BudgetGuard::class),
        ));
        $this->app->singleton(UnsplashClient::class, fn ($app) => new UnsplashClient(
            $app->make(BudgetGuard::class),
        ));

        $this->app->singleton(AssetStorage::class, fn () => new AssetStorage);
        $this->app->singleton(ImageOptimizer::class, fn () => new ImageOptimizer);
        $this->app->singleton(HeroImageGenerator::class, fn ($app) => new HeroImageGenerator(
            $app->make(FalClient::class),
            $app->make(UnsplashClient::class),
        ));
        $this->app->singleton(InfographicRenderer::class, fn ($app) => new InfographicRenderer(
            $app->make(ArticleBlockPresenter::class),
            $app->make(TenantBrandingService::class),
        ));
        $this->app->singleton(AltTextGenerator::class, fn ($app) => new AltTextGenerator(
            $app->make(LlmClient::class),
            $app->make(PromptRenderer::class),
        ));
    }

    /**
     * LLM-Zugang (#6). Zustandslose Dienste, deshalb Singletons: der
     * BudgetGuard haelt keinen Zaehler im Speicher, sondern fragt jedes Mal
     * llm_usage_logs.
     */
    private function registerLlm(): void
    {
        $this->app->singleton(BudgetGuard::class, fn () => new BudgetGuard);
        $this->app->singleton(PromptRenderer::class, fn () => new PromptRenderer);
        $this->app->singleton(SchemaValidator::class, fn () => new SchemaValidator);

        $this->app->singleton(LlmClient::class, fn ($app) => new LlmClient(
            $app->make(BudgetGuard::class),
            $app->make(PromptRenderer::class),
            $app->make(SchemaValidator::class),
            $app->make(ClaudeCliTransport::class),
        ));

        $this->app->singleton(EmbeddingClient::class, fn ($app) => new EmbeddingClient(
            $app->make(BudgetGuard::class),
        ));
    }
}
