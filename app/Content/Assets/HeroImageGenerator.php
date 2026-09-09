<?php

declare(strict_types=1);

namespace App\Content\Assets;

use App\Content\Llm\LlmContext;
use App\Content\Models\ArticleDraft;
use App\Content\Providers\FalClient;
use App\Content\Providers\UnsplashClient;
use App\Content\Support\BranchResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Beschafft das Titelbild eines Entwurfs (#16).
 *
 * Drei Stufen mit absteigender Verlaesslichkeit, jede faengt den Fehler der
 * vorigen auf:
 *
 *  1. fal.ai (Flux) mit einem Prompt aus Titel, Branche und Region. Der
 *     Prompt traegt die Negativ-Vorgaben aus
 *     config('content.assets.hero.negatives'): kein Text, keine Logos, keine
 *     erkennbaren Gesichter, keine Marken.
 *  2. Unsplash-Suche mit denselben Stichwoertern. Attributionspflichtig; der
 *     Nachweis kommt als hero_image_credit mit zurueck.
 *  3. Das Branchen-Standardbild aus resources/content/fallback/<branch>.webp.
 *
 * Scheitern alle drei, gibt der Generator null zurueck. Der Entwurf bleibt
 * dann ohne Titelbild freigegeben — ein fehlendes Bild ist kein Grund, einen
 * fertigen Artikel zurueckzuhalten.
 */
class HeroImageGenerator
{
    public const SOURCE_FAL = 'fal';

    public const SOURCE_UNSPLASH = 'unsplash';

    public const SOURCE_FALLBACK = 'fallback';

    public function __construct(
        private readonly FalClient $fal,
        private readonly UnsplashClient $unsplash,
    ) {}

    /**
     * @return array{binary: string, source: string, credit: ?string, credit_html: ?string, prompt: ?string, description: ?string}|null
     */
    public function generate(ArticleDraft $draft, ?string $branch, string $branchLabel, ?LlmContext $context = null): ?array
    {
        $context ??= LlmContext::forDraft($draft, FalClient::OPERATION);
        $sources = (array) config('content.assets.hero.sources', ['fal', 'unsplash']);

        foreach ($sources as $source) {
            $result = match ($source) {
                self::SOURCE_FAL => $this->fromFal($draft, $branchLabel, $context),
                self::SOURCE_UNSPLASH => $this->fromUnsplash($draft, $branchLabel, $context),
                default => null,
            };

            if ($result !== null) {
                return $result;
            }
        }

        return $this->fromFallback($branch);
    }

    /**
     * Der Bild-Prompt. Er beschreibt eine Szene aus Branche und Thema und
     * haengt Stil- und Negativ-Vorgaben an. Der Artikeltitel geht bewusst
     * nicht woertlich hinein: Flux versucht sonst, ihn ins Bild zu schreiben.
     */
    public function prompt(ArticleDraft $draft, string $branchLabel): string
    {
        $subject = $this->subject($draft);
        $style = trim((string) config('content.assets.hero.style', 'fotorealistisch'));
        $negatives = implode(', ', array_map(
            static fn ($negative): string => trim((string) $negative),
            (array) config('content.assets.hero.negatives', []),
        ));

        $region = $this->regionHint($draft);

        return trim(
            "Fotorealistische Aufnahme aus dem Arbeitsalltag der Branche {$branchLabel} in Deutschland. "
            ."Motiv: {$subject}."
            .($region !== null ? " Umgebung: {$region}." : '')
            ."\nStil: {$style}."
            .($negatives !== '' ? "\nAusdruecklich nicht im Bild: {$negatives}." : '')
        );
    }

    /**
     * @return array{binary: string, source: string, credit: ?string, credit_html: ?string, prompt: ?string, description: ?string}|null
     */
    private function fromFal(ArticleDraft $draft, string $branchLabel, LlmContext $context): ?array
    {
        if (! $this->fal->isConfigured()) {
            return null;
        }

        $prompt = $this->prompt($draft, $branchLabel);

        try {
            $binary = $this->fal->generate($prompt, $context);
        } catch (Throwable $exception) {
            Log::warning('Titelbild ueber fal.ai nicht erzeugbar, Rueckfall auf Unsplash.', [
                'draft_id' => $draft->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return [
            'binary' => $binary,
            'source' => self::SOURCE_FAL,
            'credit' => null,
            'credit_html' => null,
            'prompt' => $prompt,
            'description' => null,
        ];
    }

    /**
     * @return array{binary: string, source: string, credit: ?string, credit_html: ?string, prompt: ?string, description: ?string}|null
     */
    private function fromUnsplash(ArticleDraft $draft, string $branchLabel, LlmContext $context): ?array
    {
        if (! $this->unsplash->isConfigured()) {
            return null;
        }

        $query = $this->stockQuery($draft, $branchLabel);

        try {
            $photo = $this->unsplash->search($query, $context->withOperation(UnsplashClient::OPERATION));
        } catch (Throwable $exception) {
            Log::warning('Titelbild ueber Unsplash nicht beschaffbar, Rueckfall auf das Branchenbild.', [
                'draft_id' => $draft->getKey(),
                'query' => $query,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        return [
            'binary' => $photo['binary'],
            'source' => self::SOURCE_UNSPLASH,
            'credit' => $photo['credit'],
            'credit_html' => $photo['credit_html'],
            'prompt' => $query,
            'description' => $photo['description'],
        ];
    }

    /**
     * Das Branchen-Standardbild. Fehlt es, greift 'default'; fehlt auch das,
     * bleibt der Entwurf ohne Titelbild.
     *
     * @return array{binary: string, source: string, credit: ?string, credit_html: ?string, prompt: ?string, description: ?string}|null
     */
    private function fromFallback(?string $branch): ?array
    {
        $directory = rtrim((string) config('content.assets.fallback.path', resource_path('content/fallback')), '/');
        $default = (string) config('content.assets.fallback.default', 'default');

        foreach (array_filter([$branch, $default]) as $key) {
            $path = "{$directory}/{$key}.webp";

            if (! is_file($path) || ! is_readable($path)) {
                continue;
            }

            $binary = (string) file_get_contents($path);

            if ($binary === '') {
                continue;
            }

            return [
                'binary' => $binary,
                'source' => self::SOURCE_FALLBACK,
                'credit' => null,
                'credit_html' => null,
                'prompt' => null,
                'description' => null,
            ];
        }

        Log::warning('Kein Branchen-Standardbild gefunden.', [
            'branch' => $branch,
            'directory' => $directory,
        ]);

        return null;
    }

    /**
     * Suchbegriff fuer die Bilddatenbank: Branche plus die tragenden Woerter
     * des Haupt-Keywords, ohne Fuellwoerter und ohne Ortsnamen.
     */
    private function stockQuery(ArticleDraft $draft, string $branchLabel): string
    {
        $keyword = trim((string) ($draft->topicCandidate?->primary_keyword ?? ''));
        $words = array_slice(array_filter(
            preg_split('/\s+/u', $keyword) ?: [],
            static fn (string $word): bool => mb_strlen($word) > 3,
        ), 0, 3);

        return trim($branchLabel.' '.implode(' ', $words));
    }

    /**
     * Bildmotiv aus dem Thema. Der Titel wird auf seinen inhaltlichen Kern
     * gekuerzt: Fragezeichen, Jahreszahlen und Doppelpunkt-Zusaetze fuehren
     * Flux zu Schrift im Bild.
     */
    private function subject(ArticleDraft $draft): string
    {
        $title = (string) ($draft->topicCandidate?->primary_keyword ?: $draft->title);
        $title = preg_replace('/\s*[:–—-]\s.*$/u', '', $title) ?? $title;
        $title = preg_replace('/\b(19|20)\d{2}\b/u', '', $title) ?? $title;
        $title = str_replace(['?', '!', '"', '„', '“'], '', $title);

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $title) ?? ''), 90, '');
    }

    private function regionHint(ArticleDraft $draft): ?string
    {
        if ((string) $draft->region_scope === 'national' || $draft->region_code === null) {
            return null;
        }

        $state = (array) config("content.regions.states.{$draft->region_code}", []);
        $name = (string) ($state['name'] ?? '');

        return $name !== '' ? "typische Bauweise und Landschaft in {$name}" : null;
    }

    /**
     * Branchenschluessel und -bezeichnung eines Mandanten. Ohne Treffer wird
     * neutral formuliert; das Standardbild heisst dann 'default'.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function branchOf(\App\Models\Tenant $tenant): array
    {
        $branch = BranchResolver::resolve($tenant);

        return [$branch, $branch === null ? 'Handwerk und Dienstleistung' : (string) BranchResolver::label($branch)];
    }
}
