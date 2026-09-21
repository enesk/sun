<?php

declare(strict_types=1);

namespace App\Providers;

use App\Content\Providers\AdSenseClient;
use App\Content\Providers\SearchConsoleClient;
use App\Guide\Events\ArticleApproved;
use App\Guide\Events\ArticleWritten;
use App\Guide\Events\OutlineLocked;
use App\Guide\Events\ResearchCompleted;
use App\Guide\Events\RunRequested;
use App\Guide\Events\TopicCreated;
use App\Guide\Listeners\CheckQualityOnArticleWritten;
use App\Guide\Listeners\ProposeOutlineOnTopicCreated;
use App\Guide\Listeners\PublishOnArticleApproved;
use App\Guide\Listeners\ResumeWritingOnOutlineLocked;
use App\Guide\Listeners\StartChainOnRunRequested;
use App\Guide\Listeners\WriteOnResearchCompleted;
use App\Guide\Llm\ContentBudgetGuard;
use App\Guide\Llm\SchemaValidator;
use App\Guide\Llm\TemplateRenderer;
use App\Guide\Seo\LlmsTxtBuilder;
use App\Models\Portal\Post;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Registrierung der verbliebenen Content-Dienste (LLM-Zugang, Leistungsdaten)
 * und der Listener des Ratgebersystems. Quell-Connectoren, SERP-Analyse,
 * Fingerprints und die alte Veroeffentlichung sind mit #23 entfallen.
 */
class ContentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerLlm();
        $this->registerMetrics();
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
        Post::deleted(fn () => LlmsTxtBuilder::flush());

        // Ratgebersystem, Artikel-Writer (#10), Qualitaetsgate (#11) und Publisher (#12). app/Guide/Listeners liegt
        // ausserhalb der Event-Discovery (nur app/Listeners).
        Event::listen(TopicCreated::class, ProposeOutlineOnTopicCreated::class);
        Event::listen(ResearchCompleted::class, WriteOnResearchCompleted::class);
        Event::listen(OutlineLocked::class, ResumeWritingOnOutlineLocked::class);
        Event::listen(ArticleWritten::class, CheckQualityOnArticleWritten::class);
        Event::listen(ArticleApproved::class, PublishOnArticleApproved::class);
        // Tages-Orchestrator (#13): Einzellauf aus guide:run bzw. Dashboard startet die Kette.
        Event::listen(RunRequested::class, StartChainOnRunRequested::class);
    }

    /**
     * Leistungsdaten der Ratgeber-URLs (docs/guide-system.md §9
     * "Ausserhalb des Ratgeber-Kerns"). Der AdSense-Zugang ist optional: ohne
     * Freischaltung liefert er ein leeres Ergebnis, statt den Collector
     * scheitern zu lassen.
     */
    private function registerMetrics(): void
    {
        $this->app->singleton(SearchConsoleClient::class, fn () => new SearchConsoleClient);
        $this->app->singleton(AdSenseClient::class, fn ($app) => new AdSenseClient(
            $app->make(ContentBudgetGuard::class),
        ));
    }

    /**
     * LLM-Zugang (#6). Zustandslose Dienste, deshalb Singletons: der
     * ContentBudgetGuard haelt keinen Zaehler im Speicher, sondern fragt jedes Mal
     * llm_usage_logs. Den Modellaufruf selbst macht App\Guide\Llm\LlmClient.
     */
    private function registerLlm(): void
    {
        $this->app->singleton(ContentBudgetGuard::class, fn () => new ContentBudgetGuard);
        $this->app->singleton(TemplateRenderer::class, fn () => new TemplateRenderer);
        $this->app->singleton(SchemaValidator::class, fn () => new SchemaValidator);
    }
}
