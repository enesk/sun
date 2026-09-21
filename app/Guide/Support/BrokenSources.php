<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Research\ResearchService;

/**
 * Nicht erreichbare Quellen eines Themas beim Ausliefern (#27,
 * design/guide-frontend.md §4.3a). Massgeblich ist allein
 * `guide_sources.broken_at !== null` zum Zeitpunkt der Auslieferung.
 *
 * URLs werden immer ueber ResearchService::urlKey() verglichen (mit/ohne
 * www., Schraegstrich am Ende, Fragment), nie per Zeichenkette.
 *
 *  - Quellenliste: eine kaputte Quelle bleibt ohne Verweis stehen, solange sie
 *    einen aktuellen Fakt belegt oder im Text verlinkt ist; sonst entfaellt sie.
 *  - Changelog, JSON-LD, llms.txt: keine kaputte URL als Verweis.
 *  - Fliesstext: <a> auf kaputte URLs entfaellt, der Ankertext bleibt.
 */
final class BrokenSources
{
    /**
     * @param  array<string, true>  $broken  urlKey => true
     * @param  array<string, true>  $backingFacts  urlKeys kaputter Quellen aktueller Fakten
     */
    private function __construct(
        private readonly array $broken,
        private readonly array $backingFacts,
    ) {}

    public static function none(): self
    {
        return new self([], []);
    }

    public static function forTopic(?int $topicId): self
    {
        if ($topicId === null) {
            return self::none();
        }

        $sources = Source::query()
            ->where('guide_topic_id', $topicId)
            ->whereNotNull('broken_at')
            ->get(['id', 'url']);

        if ($sources->isEmpty()) {
            return self::none();
        }

        $backingIds = Fact::query()
            ->where('guide_topic_id', $topicId)
            ->where('is_current', true)
            ->whereIn('source_id', $sources->modelKeys())
            ->distinct()
            ->pluck('source_id')
            ->flip();

        $broken = [];
        $backing = [];

        foreach ($sources as $source) {
            $key = ResearchService::urlKey((string) $source->url);
            $broken[$key] = true;

            if ($backingIds->has($source->getKey())) {
                $backing[$key] = true;
            }
        }

        return new self($broken, $backing);
    }

    /**
     * Kaputte URL-Schluessel je Thema, eine Abfrage fuer viele Themen
     * (llms-full.txt).
     *
     * @param  array<int, int>  $topicIds
     * @return array<int, self>
     */
    public static function forTopics(array $topicIds): array
    {
        $topicIds = array_values(array_unique(array_filter($topicIds)));

        if ($topicIds === []) {
            return [];
        }

        $byTopic = [];

        Source::query()
            ->whereIn('guide_topic_id', $topicIds)
            ->whereNotNull('broken_at')
            ->get(['guide_topic_id', 'url'])
            ->each(function (Source $source) use (&$byTopic): void {
                $byTopic[(int) $source->guide_topic_id][ResearchService::urlKey((string) $source->url)] = true;
            });

        return array_map(fn (array $broken): self => new self($broken, []), $byTopic);
    }

    public function isEmpty(): bool
    {
        return $this->broken === [];
    }

    public function isBroken(?string $url): bool
    {
        return $url !== null && trim($url) !== '' && isset($this->broken[ResearchService::urlKey($url)]);
    }

    /**
     * Nur erreichbare URLs gelangen in einen Verweis.
     */
    public function linkable(?string $url): ?string
    {
        return $this->isBroken($url) ? null : $url;
    }

    /**
     * Kaputte Quelle, die weder einen aktuellen Fakt belegt noch im Text
     * steht: sie entfaellt in der Quellenliste.
     *
     * @param  array<string, true>  $textKeys  hrefKeys() der ausgelieferten Fassung
     */
    public function isReplaced(?string $url, array $textKeys): bool
    {
        if (! $this->isBroken($url)) {
            return false;
        }

        $key = ResearchService::urlKey((string) $url);

        return ! isset($this->backingFacts[$key]) && ! isset($textKeys[$key]);
    }

    /**
     * Entfernt <a> auf kaputte URLs; der Ankertext bleibt als normaler Text.
     */
    public function stripLinks(string $html): string
    {
        if ($this->broken === [] || $html === '') {
            return $html;
        }

        return (string) preg_replace_callback(
            '#<a\b[^>]*?\bhref\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a\s*>#is',
            fn (array $match): string => $this->isBroken(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5)) ? $match[3] : $match[0],
            $html,
        );
    }

    /**
     * urlKeys aller externen href in den Texten der ausgelieferten Fassung
     * (Fliesstext, FAQ, Kurzantwort).
     *
     * @return array<string, true>
     */
    public static function hrefKeys(?string ...$html): array
    {
        $keys = [];

        foreach ($html as $text) {
            if ($text === null || $text === '') {
                continue;
            }

            preg_match_all('#<a\b[^>]*?\bhref\s*=\s*(["\'])(https?://.*?)\1#is', $text, $matches);

            foreach ($matches[2] as $url) {
                $keys[ResearchService::urlKey(html_entity_decode($url, ENT_QUOTES | ENT_HTML5))] = true;
            }
        }

        return $keys;
    }
}
