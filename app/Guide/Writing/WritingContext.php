<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Llm\LlmCallContext;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Research\SourceEvaluator;
use App\Guide\Support\OutlineAnchors;
use App\Guide\Support\TenantPromptVars;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Alles, was die Schreibstufen eines Themas gemeinsam brauchen (#10):
 * Fakten-Set, erlaubte Quellen, Gliederung und die Prompt-Variablen, die in
 * jeder Stufe gleich sind.
 *
 * Styleguide und System-Prompt sind je Portal identisch und gehen als
 * gecachte System-Bloecke raus (App\Guide\Llm\PromptRenderer). Das
 * Fakten-Set steht in jedem Abschnittsaufruf an derselben Stelle, damit der
 * Aufruf klein bleibt und sich nur im Abschnitt unterscheidet.
 *
 * Wird im Tenant-Kontext gebaut und ist danach unveraenderlich; eine neue
 * Gliederung (Vorschlag, Sperre) braucht einen neuen Kontext.
 */
final class WritingContext
{
    /**
     * @param  Collection<int, Fact>  $facts  aktuelle Fakten (is_current) mit Quelle
     * @param  array<int, string>  $externalUrls  URLs aus guide_sources (ohne Blacklist)
     * @param  array<string, string>  $baseVars
     */
    private function __construct(
        public readonly Topic $topic,
        public readonly ?TopicRun $run,
        public readonly TenantGuideSetting $setting,
        public readonly Collection $facts,
        public readonly array $externalUrls,
        public readonly array $baseVars,
        public readonly string $today,
    ) {}

    public static function for(Topic $topic, ?TopicRun $run = null): self
    {
        $timezone = (string) config('guide.timezone', 'Europe/Berlin');
        $setting = TenantGuideSetting::current();
        $evaluator = SourceEvaluator::forSetting($setting);

        /** @var Collection<int, Fact> $facts */
        $facts = $topic->currentFacts()->with('source')->orderBy('key')->get();

        $externalUrls = $topic->sources()
            ->pluck('url')
            ->map(fn (mixed $url): string => trim((string) $url))
            ->filter(fn (string $url): bool => $url !== '' && preg_match('#^https?://#i', $url) === 1 && ! $evaluator->isBlacklisted($url))
            ->unique()
            ->values()
            ->all();

        $knownUrls = $facts->map(fn (Fact $fact): ?string => $fact->source?->url)->filter()->unique()->values()->all();
        $today = Carbon::now($timezone)->toDateString();

        return new self(
            topic: $topic,
            run: $run,
            setting: $setting,
            facts: $facts,
            externalUrls: $externalUrls,
            baseVars: [
                ...TenantPromptVars::current($setting),
                'question' => (string) $topic->question,
                'category' => (string) ($topic->category?->name ?? 'Allgemein'),
                'notes' => trim((string) $topic->notes) !== '' ? trim((string) $topic->notes) : 'keine',
                'today' => $today,
                'last_checked_at' => $topic->last_checked_at?->copy()->setTimezone($timezone)->toDateString() ?? 'noch nie',
                'current_facts' => self::factsJson($facts),
                'sources' => $evaluator->promptText($knownUrls),
                'outline' => self::json(self::outlineForPrompt($topic->outline_json)),
            ],
            today: $today,
        );
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, string>
     */
    public function vars(array $vars = []): array
    {
        return [...$this->baseVars, ...$vars];
    }

    public function llmContext(): LlmCallContext
    {
        return $this->run !== null ? LlmCallContext::forRun($this->run) : LlmCallContext::forTopic($this->topic);
    }

    /**
     * Gliederung in Lesereihenfolge (OutlineAnchors::flatten()).
     *
     * @return list<array{id: string, text: string, level: int}>
     */
    public function entries(): array
    {
        return OutlineAnchors::flatten($this->topic->outline_json);
    }

    /**
     * @return array<int, string>
     */
    public function factKeys(): array
    {
        return $this->facts->pluck('key')->map(fn (mixed $key): string => (string) $key)->values()->all();
    }

    /**
     * Nur Schluessel aus dem Fakten-Set des Themas; alles andere hat das
     * Modell erfunden und zaehlt nicht als Beleg.
     *
     * @param  array<int, mixed>  $keys
     * @return array<int, string>
     */
    public function knownFactKeys(array $keys): array
    {
        $known = array_flip($this->factKeys());

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $key): string => trim((string) $key), $keys),
            fn (string $key): bool => isset($known[$key]),
        )));
    }

    public function fact(string $key): ?Fact
    {
        return $this->facts->first(fn (Fact $fact): bool => $fact->key === $key);
    }

    public function source(string $url): ?Source
    {
        foreach ($this->facts as $fact) {
            if ($fact->source !== null && $fact->source->url === $url) {
                return $fact->source;
            }
        }

        return null;
    }

    public static function template(string $key): PromptTemplate
    {
        $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
        $template = PromptTemplate::query()->resolve($key, $tenantId)->first();

        if ($template === null) {
            throw new RuntimeException("Prompt-Template '{$key}' fehlt. php artisan db:seed --class=GuidePromptTemplateSeeder ausfuehren.");
        }

        return $template;
    }

    public static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  Collection<int, Fact>  $facts
     */
    private static function factsJson(Collection $facts): string
    {
        $rows = $facts->map(fn (Fact $fact): array => [
            'key' => $fact->key,
            'label' => $fact->label,
            'value' => $fact->value,
            'unit' => $fact->unit,
            'valid_from' => $fact->valid_from?->toDateString(),
            'source_url' => $fact->source?->url,
            'publisher' => $fact->source?->publisher,
            'source_published_at' => $fact->source?->published_at?->toDateString(),
        ])->values()->all();

        return $rows === [] ? '[]' : self::json($rows);
    }

    /**
     * @param  array<int, mixed>|null  $outline
     * @return array<int, array<string, mixed>>
     */
    private static function outlineForPrompt(?array $outline): array
    {
        return array_map(fn (array $entry): array => [
            'id' => $entry['id'],
            'level' => $entry['level'],
            'heading' => $entry['text'],
        ], OutlineAnchors::flatten($outline));
    }
}
