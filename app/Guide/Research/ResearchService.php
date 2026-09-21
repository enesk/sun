<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Dto\FactSet;
use App\Guide\Dto\ProbeResult;
use App\Guide\Enums\TrustLevel;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Llm\ResearchResult;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Models\Fact;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Support\GuidePageCache;
use App\Guide\Support\TenantPromptVars;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Aktualitaetsrecherche des Ratgebersystems (#8, docs/guide-system.md §2).
 *
 * probe(): guenstige Pruefung (guide.web_search.max_uses_probe Suchen), ob es
 * seit last_checked_at neue Informationen gibt. Ergebnis -> probe_json.
 *
 * deepResearch(): vollstaendiges Fakten-Set (max_uses_deep Suchen). Die
 * Modellausgabe wird nicht ungeprueft uebernommen:
 *  - Quellen der Blacklist, Quellen ohne Einstufung official|trade|press und
 *    URLs, die nicht in den Suchergebnissen standen, belegen keinen Fakt.
 *  - Quellen aelter als guide.research.stale_after_months zaehlen nur, wenn
 *    es fuer den Fakt keine neuere gibt (stale_source).
 *  - Mehrere Werte je Schluessel: hoeheres trust_level gewinnt, bei
 *    Gleichstand das neuere published_at; protokolliert in conflicts[]
 *    (rule trust_level|published_at, oder stale_source, wenn der
 *    abweichende Wert nur aus einer zu alten Quelle stammt).
 * Ergebnis -> guide_facts, guide_sources (FactStore), research_json.
 * Danach vergleicht der ChangeDetector (#9) das Fakten-Set vor und nach der
 * Recherche: research_json.changed_facts, research_json.change_detection und
 * guide_topic_runs.changed_section_ids_json.
 *
 * Statuswechsel des Laufs macht der Job, nicht dieser Service. Seitentext
 * wird nie gespeichert, nur Fakten, Titel, URL, Herausgeber und Datum.
 */
class ResearchService
{
    public const TEMPLATE_PROBE = 'guide.freshness_probe';

    public const TEMPLATE_DEEP = 'guide.deep_research';

    public function __construct(
        private readonly LlmClient $client,
        private readonly FactStore $store,
        private readonly FactsHasher $hasher,
        private readonly ChangeDetector $detector,
    ) {}

    public function probe(Topic $topic, TopicRun $run): ProbeResult
    {
        $setting = TenantGuideSetting::current();
        $evaluator = SourceEvaluator::forSetting($setting);
        $template = $this->template(self::TEMPLATE_PROBE);
        $filter = $evaluator->searchFilter();

        $result = $this->client->research(
            $template,
            $this->vars($topic, $setting, $evaluator),
            [],
            max(1, (int) config('guide.web_search.max_uses_probe', 2)),
            $filter['allowed'],
            $filter['blocked'],
            LlmCallContext::forRun($run),
        );

        $changed = (bool) ($result->data['changed'] ?? false);
        $confidence = (float) ($result->data['confidence'] ?? 0.0);
        $unchanged = ! $changed && $confidence >= (float) config('guide.research.probe_confidence_min', 0.7);

        $candidates = [];
        $rejected = [];

        foreach ((array) ($result->data['candidate_changes'] ?? []) as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($evaluator->isBlacklisted((string) ($candidate['source_url'] ?? ''))) {
                $rejected[] = ['source_url' => (string) ($candidate['source_url'] ?? ''), 'reason' => 'blacklist'];

                continue;
            }

            $candidates[] = $candidate + [
                'trust_level' => $evaluator->trustLevel((string) ($candidate['source_url'] ?? ''))?->value,
            ];
        }

        $data = [
            'changed' => $changed,
            'reason' => (string) ($result->data['reason'] ?? ''),
            'confidence' => $confidence,
            'decision' => $unchanged ? 'unchanged' : 'deep_research',
            'candidate_changes' => $candidates,
            'rejected' => $rejected,
            'meta' => $this->meta($template, $topic, $result, $filter),
        ];

        $this->persistRun($run, 'probe_json', $data, $result->costUsd);

        return new ProbeResult($changed, $confidence, $unchanged, $data, $result->costUsd);
    }

    /**
     * Probe ohne Befund: nur Geprueft-Datum und naechster Termin, das
     * Fakten-Set bleibt unberuehrt (last_changed_at und lastmod ebenso).
     */
    public function markUnchanged(Topic $topic, ?Carbon $now = null): void
    {
        $now ??= Carbon::now();

        $topic->forceFill([
            'last_checked_at' => $now,
            'next_due_at' => $now->copy()->addDays($topic->refreshIntervalDays()),
            'consecutive_failures' => 0,
        ])->save();

        $this->hasher->refresh($topic);
    }

    public function deepResearch(Topic $topic, TopicRun $run): FactSet
    {
        $now = Carbon::now();
        $setting = TenantGuideSetting::current();
        $evaluator = SourceEvaluator::forSetting($setting);
        $template = $this->template(self::TEMPLATE_DEEP);
        $filter = $evaluator->searchFilter();
        $hashBefore = $topic->facts_hash;
        $factsBefore = $this->detector->snapshot($topic);
        $toReplace = $this->factsToReplace($topic);
        $brokenKeys = $topic->sources()
            ->whereNotNull('broken_at')
            ->pluck('url')
            ->mapWithKeys(fn (mixed $url): array => [self::urlKey((string) $url) => true])
            ->all();

        $result = $this->client->research(
            $template,
            [...$this->vars($topic, $setting, $evaluator), 'replace_sources' => $this->replaceSourcesText($toReplace)],
            [],
            max(1, (int) config('guide.web_search.max_uses_deep', 6)),
            $filter['allowed'],
            $filter['blocked'],
            LlmCallContext::forRun($run),
        );

        // Vor dem Fortschreiben des Themas, damit last_checked_at den alten Stand zeigt.
        $meta = $this->meta($template, $topic, $result, $filter);
        [$candidates, $rejected] = $this->candidates($result, $evaluator, $brokenKeys);
        [$facts, $conflicts, $staleRejected] = $this->resolve($candidates, $now);
        $stored = $this->store->store($topic, $facts, $now);
        $factsChanged = $stored['created'] !== [] || $stored['changed'] !== [];

        // Ersetzte Belege aendern die Quellenliste der Seite, nicht ihren Inhalt.
        if ($stored['source_replaced'] !== []) {
            GuidePageCache::flush();
        }

        $topic->forceFill(array_filter([
            'last_checked_at' => $now,
            'last_changed_at' => $factsChanged ? $now : null,
            // Ohne Artikel plant die Kette (#10/#12) den naechsten Termin;
            // sonst waere ein Entwurf bis dahin fuer den Dispatcher gesperrt.
            'next_due_at' => $topic->article_id !== null ? $now->copy()->addDays($topic->refreshIntervalDays()) : null,
        ], fn ($value) => $value !== null))->save();

        $hash = $this->hasher->refresh($topic);
        $changes = $this->detector->detect($topic, $run, $factsBefore, $this->detector->snapshot($topic));

        $data = [
            'facts' => array_map(fn (array $fact): array => [
                'key' => $fact['key'],
                'label' => $fact['label'],
                'value' => $fact['value'],
                'unit' => $fact['unit'],
                'valid_from' => $fact['valid_from'],
                'source_url' => $fact['source']['url'],
                'trust_level' => $fact['source']['trust_level']->value,
                'stale_source' => $fact['stale_source'],
            ], $facts),
            'sources' => array_values(array_map(fn (array $source): array => [
                'url' => $source['url'],
                'title' => $source['title'],
                'publisher' => $source['publisher'],
                'published_at' => $source['published_at'],
                'trust_level' => $source['trust_level']->value,
            ], array_column(array_column($facts, 'source'), null, 'url'))),
            'conflicts' => $conflicts,
            'rejected' => [...$rejected, ...$staleRejected],
            'open_points' => array_values(array_map('strval', (array) ($result->data['open_points'] ?? []))),
            'created_keys' => $stored['created'],
            'changed' => $stored['changed'],
            'confirmed_keys' => $stored['confirmed'],
            'replace_sources' => $toReplace,
            'source_replaced' => $stored['source_replaced'],
            'unconfirmed_keys' => $stored['unconfirmed'],
            'facts_hash_before' => $hashBefore,
            'facts_hash' => $hash,
            'changed_facts' => $changes->changedFacts,
            'change_detection' => $changes->toArray(),
            'meta' => $meta,
        ];

        $run->forceFill(['changed_section_ids_json' => $changes->changedSectionIds]);
        $this->persistRun($run, 'research_json', $data, $result->costUsd);

        return new FactSet(
            data: $data,
            currentFactCount: $topic->currentFacts()->count(),
            factsChanged: $factsChanged,
            factsHash: $hash,
            costUsd: $result->costUsd,
            changes: $changes,
        );
    }

    /**
     * Fakten der Modellausgabe mit ihrer bewerteten Quelle; alles, was keinen
     * Fakt belegen darf, landet mit Grund in $rejected. Nicht erreichbare
     * Quellen des Themas (broken_at, #27) sind als Beleg gesperrt; eine andere
     * Seite derselben Domain bleibt zulaessig.
     *
     * @param  array<string, true>  $brokenKeys  urlKey() der kaputten Quellen
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>}
     */
    private function candidates(ResearchResult $result, SourceEvaluator $evaluator, array $brokenKeys = []): array
    {
        $found = [];

        foreach ($result->citations as $citation) {
            $found[self::urlKey($citation['url'])] = $citation;
        }

        $sources = [];

        foreach ((array) ($result->data['sources'] ?? []) as $source) {
            if (is_array($source) && ! empty($source['url'])) {
                $sources[self::urlKey((string) $source['url'])] = $source;
            }
        }

        $candidates = [];
        $rejected = [];

        foreach ((array) ($result->data['facts'] ?? []) as $fact) {
            if (! is_array($fact) || trim((string) ($fact['key'] ?? '')) === '' || trim((string) ($fact['value'] ?? '')) === '') {
                continue;
            }

            $url = trim((string) ($fact['source_url'] ?? ''));
            $urlKey = self::urlKey($url);
            $reject = fn (string $reason): array => ['key' => (string) $fact['key'], 'source_url' => $url, 'reason' => $reason];

            if ($url === '' || ! isset($sources[$urlKey])) {
                $rejected[] = $reject('source_not_listed');

                continue;
            }

            if (isset($brokenKeys[$urlKey])) {
                $rejected[] = $reject('broken');

                continue;
            }

            $level = $evaluator->trustLevel($url);

            if ($level === null) {
                // Blacklist: die Quelle wird nie gespeichert.
                $rejected[] = $reject('blacklist');

                continue;
            }

            if (! isset($found[$urlKey])) {
                $rejected[] = $reject('not_in_search_results');

                continue;
            }

            if (! SourceEvaluator::supportsFacts($level)) {
                $rejected[] = $reject('trust_level_other');

                continue;
            }

            $source = $sources[$urlKey];
            $citation = $found[$urlKey];
            $publishedAt = self::date($source['published_at'] ?? null) ?? self::date($citation['page_age'] ?? null);

            $candidates[] = [
                'key' => mb_substr(trim((string) $fact['key']), 0, 128),
                'label' => mb_substr(trim((string) ($fact['label'] ?? $fact['key'])), 0, 255),
                'value' => mb_substr(trim((string) $fact['value']), 0, 1000),
                'unit' => self::nullable($fact['unit'] ?? null, 32),
                'valid_from' => self::date($fact['valid_from'] ?? null),
                'stale_source' => false,
                'source' => [
                    'url' => mb_substr((string) $citation['url'], 0, 2048),
                    'title' => self::nullable($source['title'] ?? null, 255) ?? self::nullable($citation['title'], 255),
                    'publisher' => $evaluator->publisher($url) ?? self::nullable($source['publisher'] ?? null, 255),
                    'published_at' => $publishedAt,
                    'trust_level' => $level,
                    'claimed_trust_level' => (string) ($source['trust_level'] ?? ''),
                ],
            ];
        }

        return [$candidates, $rejected];
    }

    /**
     * Genau ein Wert und eine Quelle je Schluessel.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function resolve(array $candidates, Carbon $now): array
    {
        $cutoff = $now->copy()->subMonths((int) config('guide.research.stale_after_months', 24))->toDateString();
        $byKey = [];

        foreach ($candidates as $candidate) {
            $byKey[$candidate['key']][] = $candidate;
        }

        $facts = [];
        $conflicts = [];
        $rejected = [];

        foreach ($byKey as $key => $group) {
            $isStale = fn (array $c): bool => $c['source']['published_at'] !== null && $c['source']['published_at'] < $cutoff;
            $fresh = array_values(array_filter($group, fn (array $c): bool => ! $isStale($c)));

            $droppedStale = [];

            if ($fresh !== []) {
                foreach (array_filter($group, $isStale) as $old) {
                    $rejected[] = ['key' => $key, 'source_url' => $old['source']['url'], 'reason' => 'stale_source_newer_available'];
                    $droppedStale[] = $old;
                }

                $group = $fresh;
            } else {
                $group = array_map(fn (array $c): array => ['stale_source' => true] + $c, $group);
            }

            usort($group, function (array $a, array $b): int {
                $byTrust = SourceEvaluator::rank($b['source']['trust_level']) <=> SourceEvaluator::rank($a['source']['trust_level']);

                return $byTrust !== 0 ? $byTrust : strcmp((string) $b['source']['published_at'], (string) $a['source']['published_at']);
            });

            $winner = $group[0];
            $values = [];

            foreach ($group as $candidate) {
                $values[self::valueKey($candidate)] ??= $candidate;
            }

            $staleValues = array_filter($droppedStale, fn (array $c): bool => ! isset($values[self::valueKey($c)]));

            if (count($values) === 1 && $staleValues !== []) {
                $conflicts[] = [
                    'key' => $key,
                    'rule' => 'stale_source',
                    'winner' => $this->conflictEntry($winner),
                    'values' => array_values(array_map(fn (array $c): array => $this->conflictEntry($c), [$winner, ...$staleValues])),
                ];
            }

            if (count($values) > 1) {
                $runnerUp = array_values(array_filter($values, fn (array $c): bool => self::valueKey($c) !== self::valueKey($winner)))[0];
                $sameTrust = $winner['source']['trust_level'] === $runnerUp['source']['trust_level'];

                $conflicts[] = [
                    'key' => $key,
                    'rule' => $sameTrust ? 'published_at' : 'trust_level',
                    'winner' => $this->conflictEntry($winner),
                    'values' => array_values(array_map(fn (array $c): array => $this->conflictEntry($c), $values)),
                ];
            }

            $facts[] = $winner;
        }

        return [$facts, $conflicts, $rejected];
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function conflictEntry(array $candidate): array
    {
        return [
            'value' => $candidate['value'],
            'unit' => $candidate['unit'],
            'valid_from' => $candidate['valid_from'],
            'source_url' => $candidate['source']['url'],
            'trust_level' => $candidate['source']['trust_level'] instanceof TrustLevel
                ? $candidate['source']['trust_level']->value
                : (string) $candidate['source']['trust_level'],
            'published_at' => $candidate['source']['published_at'],
        ];
    }

    /**
     * Prompt-Variablen beider Stufen. Heute und last_checked_at stehen immer
     * drin, damit das Modell gezielt nach Neuerungen "seit <Datum>" sucht;
     * die Notizen des Themas sind der Recherche-Auftrag der Redaktion.
     *
     * @return array<string, string>
     */
    private function vars(Topic $topic, TenantGuideSetting $setting, SourceEvaluator $evaluator): array
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $current = $topic->currentFacts()->with('source')->orderBy('key')->get();

        $previous = $topic->facts()
            ->with('source')
            ->where('is_current', false)
            ->latest('id')
            ->limit(30)
            ->get();

        $knownUrls = $current->map(fn (Fact $fact): ?string => $fact->source?->url)->filter()->unique()->values()->all();

        return [
            ...TenantPromptVars::current($setting),
            'question' => (string) $topic->question,
            'category' => (string) ($topic->category?->name ?? 'Allgemein'),
            'notes' => trim((string) $topic->notes) !== '' ? trim((string) $topic->notes) : 'keine',
            'today' => Carbon::now($timezone)->toDateString(),
            'last_checked_at' => $topic->last_checked_at?->copy()->setTimezone($timezone)->toDateString()
                ?? 'noch nie (erste Recherche)',
            'current_facts' => $this->factsJson($current),
            'previous_facts' => $this->factsJson($previous),
            'sources' => $evaluator->promptText($knownUrls),
        ];
    }

    /**
     * Aktuelle Fakten, deren Quelle nicht erreichbar ist (broken_at, #27):
     * Auftrag an die Tiefenrecherche, sie mit einer anderen Quelle neu zu belegen.
     *
     * @return list<array{key: string, value: string, url: string}>
     */
    private function factsToReplace(Topic $topic): array
    {
        return $topic->currentFacts()
            ->whereHas('source', fn ($query) => $query->whereNotNull('broken_at'))
            ->with('source')
            ->orderBy('key')
            ->get()
            ->map(fn (Fact $fact): array => [
                'key' => (string) $fact->key,
                'value' => trim($fact->value.' '.($fact->unit ?? '')),
                'url' => (string) $fact->source?->url,
            ])
            ->values()
            ->all();
    }

    /**
     * Block „Zu ersetzende Belege“ fuer guide.deep_research; leer = „keine“.
     *
     * @param  list<array{key: string, value: string, url: string}>  $facts
     */
    private function replaceSourcesText(array $facts): string
    {
        if ($facts === []) {
            return 'keine';
        }

        return implode("\n", array_map(
            fn (array $fact): string => "- {$fact['key']}: bisheriger Wert \"{$fact['value']}\", nicht erreichbare Quelle {$fact['url']}",
            $facts,
        ));
    }

    /**
     * @param  iterable<Fact>  $facts
     */
    private function factsJson(iterable $facts): string
    {
        $rows = [];

        foreach ($facts as $fact) {
            $rows[] = [
                'key' => $fact->key,
                'label' => $fact->label,
                'value' => $fact->value,
                'unit' => $fact->unit,
                'valid_from' => $fact->valid_from?->toDateString(),
                'source_url' => $fact->source?->url,
            ];
        }

        return $rows === [] ? '[]' : (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array{allowed: array<int, string>, blocked: array<int, string>}  $filter
     * @return array<string, mixed>
     */
    private function meta(PromptTemplate $template, Topic $topic, ResearchResult $result, array $filter): array
    {
        return [
            'template_key' => (string) $template->key,
            'template_version' => (int) $template->version,
            'today' => Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString(),
            'last_checked_at' => $topic->last_checked_at?->toIso8601String(),
            'search_filter' => $filter['allowed'] !== [] ? 'allowed_domains' : 'blocked_domains',
            'search_count' => $result->searchCount,
            'search_errors' => $result->searchErrors,
            'citations' => count($result->citations),
            'requests' => $result->requests,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'cost_usd' => round($result->costUsd, 6),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistRun(TopicRun $run, string $column, array $data, float $cost): void
    {
        $run->forceFill([
            $column => $data,
            'cost_usd' => round((float) $run->cost_usd + $cost, 6),
        ])->save();
    }

    private function template(string $key): PromptTemplate
    {
        $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
        $template = PromptTemplate::query()->resolve($key, $tenantId)->first();

        if ($template === null) {
            throw new RuntimeException("Prompt-Template '{$key}' fehlt. php artisan db:seed --class=GuidePromptTemplateSeeder ausfuehren.");
        }

        return $template;
    }

    /**
     * Vergleichsschluessel fuer URLs aus Modellausgabe und Suchergebnissen:
     * ohne Schema, www., Fragment und abschliessenden Schraegstrich.
     */
    public static function urlKey(string $url): string
    {
        $parts = parse_url(trim($url));

        if (! is_array($parts) || empty($parts['host'])) {
            return strtolower(trim($url));
        }

        $host = (string) preg_replace('/^www\./', '', strtolower((string) $parts['host']));
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $query = isset($parts['query']) ? "?{$parts['query']}" : '';

        return "{$host}{$path}{$query}";
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private static function valueKey(array $candidate): string
    {
        return app(FactNormalizer::class)->normalize((string) $candidate['value'], $candidate['unit']);
    }

    private static function date(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        // Unsinnige Jahreszahlen aus Seitenangaben verwerfen.
        return $date->year < 1990 ? null : $date->toDateString();
    }

    private static function nullable(mixed $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
