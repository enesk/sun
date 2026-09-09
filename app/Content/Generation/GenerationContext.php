<?php

declare(strict_types=1);

namespace App\Content\Generation;

use App\Content\Models\FactSnippet;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\SerpInsightDto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Alles, was der Artikel-Generator (#14) ueber ein Thema weiss.
 *
 * Der ContextAssembler stellt das Objekt einmal je Entwurf zusammen; jede
 * Generierungsstufe liest daraus, keine Stufe fragt selbst eine Datenquelle.
 * Das haelt die Zahl der Abfragen und der Provider-Aufrufe je Artikel
 * berechenbar und macht einen Retry billig — der Kontext wird dabei
 * wiederverwendet.
 *
 * `promptVars()` liefert genau die Variablen, die die Prompt-Templates aus
 * #13 dokumentieren (docs/content-prompts.md §4). Fehlt eine davon, faellt
 * die betroffene Stufe auf ihren eingebauten Prompt zurueck, statt den
 * ganzen Entwurf zu verlieren.
 *
 * @phpstan-type LinkTarget array{type: string, label: string, url: string, anchor: string, note: string}
 */
final class GenerationContext
{
    /**
     * @param  array<int, string>  $secondaryKeywords
     * @param  Collection<int, FactSnippet>  $factSnippets
     * @param  Collection<int, SourceItem>  $sourceItems
     * @param  array<string, mixed>|null  $portalData
     * @param  array<int, array{type: string, label: string, url: string, anchor: string, note: string}>  $linkTargets
     * @param  array{min: int, max: int}  $targetWords
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantName,
        public readonly string $branch,
        public readonly string $branchLabel,
        public readonly string $styleguide,
        public readonly TenantContentSetting $settings,
        public readonly TopicCandidate $topic,
        public readonly string $intent,
        public readonly string $primaryKeyword,
        public readonly array $secondaryKeywords,
        public readonly string $regionScope,
        public readonly ?string $regionCode,
        public readonly string $regionName,
        public readonly ?SerpInsightDto $serp,
        public readonly Collection $factSnippets,
        public readonly Collection $sourceItems,
        public readonly ?array $portalData,
        public readonly ?string $seasonalHook,
        public readonly ?string $newsHook,
        public readonly array $linkTargets,
        public readonly array $targetWords,
    ) {}

    public function isRegional(): bool
    {
        return $this->regionScope !== 'national';
    }

    /**
     * Faktenschnipsel mit echtem Regionalbezug. Ohne mindestens einen davon
     * gibt es keinen Regionalblock — genau das prueft das Abnahmekriterium
     * 'missing_regional_facts'.
     *
     * @return Collection<int, FactSnippet>
     */
    public function regionalFactSnippets(): Collection
    {
        return $this->factSnippets->filter(
            fn (FactSnippet $snippet): bool => $snippet->region_scope !== 'national'
        )->values();
    }

    /**
     * People-Also-Ask-Fragen; sie tragen die Nebenfragen der Gliederung.
     *
     * @return array<int, string>
     */
    public function paa(): array
    {
        return $this->serp?->paa ?? [];
    }

    /**
     * @return array<int, string>
     */
    public function competitorHeadings(): array
    {
        return array_slice($this->serp?->competitorHeadings() ?? [], 0, 25);
    }

    /**
     * Die Faktenliste, wie das Modell sie sieht: nummeriert, mit Quelle und
     * Stand. Die Nummer ist die fact_snippets-ID, damit eine Stufe belegen
     * kann, welchen Fakt sie verwendet hat.
     */
    public function factBlock(): string
    {
        if ($this->factSnippets->isEmpty()) {
            return 'Keine belegten Fakten vorhanden. Nennen Sie in diesem Fall keine Zahl, '
                .'keinen Preis und keine Frist.';
        }

        return $this->factSnippets->map(function (FactSnippet $snippet): string {
            $value = trim((string) $snippet->value.' '.(string) $snippet->unit);
            $period = $snippet->period !== null && $snippet->period !== '' ? ", Stand {$snippet->period}" : '';
            $region = $snippet->region_scope === 'national'
                ? 'bundesweit'
                : trim("{$snippet->region_scope} {$snippet->region_code}");

            return "[F{$snippet->getKey()}] ({$region}{$period}, Quelle: "
                .($snippet->source_name ?: 'Portal').') '
                .trim((string) $snippet->statement)
                .($value !== '' ? " — Wert: {$value}" : '');
        })->implode("\n");
    }

    /**
     * Portal-Eigendaten als Klartext. Das ist der Teil, den kein Wettbewerber
     * hat, und die Grundlage des Regionalblocks.
     */
    public function portalBlock(): string
    {
        $data = $this->portalData;

        if ($data === null || (int) ($data['provider_count'] ?? 0) === 0) {
            return 'Keine eigenen Portalzahlen fuer diese Region vorhanden.';
        }

        $lines = [
            "Gelistete Betriebe in {$data['region_name']}: ".(int) $data['provider_count'],
        ];

        if (($data['avg_rating'] ?? null) !== null) {
            $lines[] = 'Durchschnittsbewertung: '.number_format((float) $data['avg_rating'], 1, ',', '.')
                .' aus '.(int) ($data['review_count'] ?? 0).' Bewertungen';
        }

        $services = array_map(
            static fn (array $service): string => (string) $service['name'].' ('.(int) $service['count'].')',
            array_values((array) ($data['common_services'] ?? [])),
        );

        if ($services !== []) {
            $lines[] = 'Haeufigste Leistungen: '.implode(', ', $services);
        }

        return implode("\n", $lines);
    }

    /**
     * Interne Linkziele als Klartext, mit dem gewuenschten Ankertext. Der
     * Anker ist vorgegeben und nicht dem Modell ueberlassen: er muss
     * beschreibend sein und darf nicht 'hier klicken' lauten.
     */
    public function linkBlock(): string
    {
        if ($this->linkTargets === []) {
            return 'Keine internen Linkziele vorhanden.';
        }

        return collect($this->linkTargets)->map(
            static fn (array $target): string => "- {$target['url']} — Ankertext: \"{$target['anchor']}\" ({$target['note']})"
        )->implode("\n");
    }

    /**
     * Die dokumentierten Prompt-Variablen (#13).
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function promptVars(array $extra = []): array
    {
        return array_merge([
            'tenant_name' => $this->tenantName,
            'branch' => $this->branchLabel,
            'styleguide' => $this->styleguide,
            'intent' => $this->intent,
            'primary_keyword' => $this->primaryKeyword,
            'secondary_keywords' => $this->secondaryKeywords === []
                ? 'keine'
                : implode(', ', $this->secondaryKeywords),
            'region_scope' => $this->regionScope,
            'region_name' => $this->regionName,
            'serp_paa' => $this->paa() === [] ? 'keine' : implode(' | ', $this->paa()),
            'competitor_h2s' => $this->competitorHeadings() === []
                ? 'keine'
                : implode(' | ', $this->competitorHeadings()),
            'fact_snippets' => $this->factBlock(),
            'portal_data' => $this->portalBlock(),
            'internal_link_targets' => $this->linkBlock(),
            'seasonal_hook' => $this->seasonalHook ?? 'kein besonderer Saisonanlass',
            'news_hook' => $this->newsHook ?? 'kein aktueller Nachrichtenanlass',
            'outline' => 'liegt in diesem Schritt noch nicht vor',
            'section' => 'liegt in diesem Schritt noch nicht vor',
        ], $extra);
    }

    /**
     * Kurzbeschreibung fuer Logeintraege.
     */
    public function label(): string
    {
        return Str::limit($this->primaryKeyword, 60).' ('.$this->regionScope.')';
    }
}
