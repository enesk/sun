<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Sources\AbstractHttpConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\KeywordMatcher;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Generischer Feed-Leser fuer Foerderung, Recht und Verbandsmeldungen (#11).
 *
 * Der Connector selbst kennt keine einzige Adresse: die Feeds stehen in
 * config/content_feeds.php, gruppiert nach 'global', 'states' und
 * 'branches.<schluessel>'. Ein neuer Feed ist ein Konfigurationseintrag.
 *
 * Gelesen werden immer nur Titel, Datum, Link und die Kurzbeschreibung des
 * Feeds — nie die verlinkte Seite. Damit bleibt der Abruf leicht, und es
 * landet kein fremder Volltext in der Datenbank.
 *
 * Ein einzelner toter Feed bricht den Lauf nicht ab: Behoerdenfeeds aendern
 * ihre Adresse ohne Ankuendigung, und ein 404 bei der KfW darf nicht den
 * Circuit Breaker fuer alle Mandanten oeffnen. Erst wenn *kein* Feed
 * erreichbar war, wird der Fehler nach oben gereicht.
 */
class RssFeedConnector extends AbstractHttpConnector
{
    public function key(): string
    {
        return 'rss_feeds';
    }

    public function schedule(): string
    {
        return SourceFrequency::SIX_HOURLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $feeds = $this->feedsFor($context);

        if ($feeds === []) {
            Log::info('Keine Feeds fuer diesen Mandanten konfiguriert.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
                'branch' => $context->branch(),
            ]);

            return collect();
        }

        $matcher = new KeywordMatcher($context->branchKeywords());
        $items = collect();
        $failures = [];

        foreach ($feeds as $feed) {
            try {
                $items = $items->merge($this->readFeed($feed, $context, $matcher));
            } catch (Throwable $exception) {
                $failures[] = "{$feed['key']}: {$exception->getMessage()}";

                Log::warning('Feed nicht lesbar, uebersprungen.', [
                    'connector' => $this->key(),
                    'feed' => $feed['key'],
                    'url' => $feed['url'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        // Alle Feeds tot heisst: Netz, Proxy oder User-Agent, nicht der Feed.
        if ($failures !== [] && count($failures) === count($feeds)) {
            throw new \RuntimeException('Kein Feed erreichbar: '.implode('; ', array_slice($failures, 0, 3)));
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $feed
     * @return Collection<int, SourceItemDto>
     */
    private function readFeed(array $feed, TenantContext $context, KeywordMatcher $matcher): Collection
    {
        $xml = $this->feed((string) $feed['url'], $context);

        if ($xml === null) {
            return collect();
        }

        $maxItems = max(1, (int) $feed['max_items']);
        $filter = (bool) $feed['filter_by_keywords'] && $matcher->hasKeywords();
        $items = collect();
        $seen = 0;

        foreach ($this->entries($xml) as $entry) {
            if ($seen >= $maxItems) {
                break;
            }

            $seen++;
            $item = $this->toItem($entry, $feed, $matcher, $filter);

            if ($item !== null) {
                $items->push($item);
            }
        }

        return $items;
    }

    /**
     * RSS 2.0 und Atom in einem Aufwasch — die Behoerdenfeeds nutzen beides.
     *
     * @return iterable<SimpleXMLElement>
     */
    private function entries(SimpleXMLElement $xml): iterable
    {
        if (isset($xml->channel->item)) {
            return $xml->channel->item;
        }

        if (isset($xml->item)) {
            return $xml->item;
        }

        return $xml->entry ?? [];
    }

    /**
     * @param  array<string, mixed>  $feed
     */
    private function toItem(SimpleXMLElement $entry, array $feed, KeywordMatcher $matcher, bool $filter): ?SourceItemDto
    {
        $title = trim((string) $entry->title);

        if ($title === '') {
            return null;
        }

        $description = $this->description($entry);
        $matched = $matcher->matches($title.' '.$description);

        if ($filter && $matched === []) {
            return null;
        }

        $url = $this->link($entry);
        $publishedAt = $this->publishedAt($entry);

        return new SourceItemDto(
            type: (string) $feed['type'],
            title: $title,
            url: $url,
            // Titel + Beschreibung, im DTO auf 300 Zeichen gekuerzt.
            snippet: $description !== '' ? $description : null,
            regionScope: (string) $feed['region_scope'],
            regionCode: $feed['region_code'] !== null ? (string) $feed['region_code'] : null,
            keywords: $matched,
            signalStrength: $this->strength($feed, $matched, $publishedAt),
            publishedAt: $publishedAt,
            raw: [
                'feed_key' => $feed['key'],
                'feed_name' => $feed['name'],
                'matched_keywords' => $matched,
            ],
            externalId: $feed['key'].':'.mb_substr(hash('sha256', $url.'|'.$title), 0, 32),
        );
    }

    private function description(SimpleXMLElement $entry): string
    {
        foreach (['description', 'summary', 'content'] as $field) {
            $value = trim(strip_tags((string) ($entry->{$field} ?? '')));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * RSS liefert den Link als Text, Atom als href-Attribut.
     */
    private function link(SimpleXMLElement $entry): ?string
    {
        $link = trim((string) $entry->link);

        if ($link !== '') {
            return $link;
        }

        $href = trim((string) ($entry->link['href'] ?? ''));

        if ($href !== '') {
            return $href;
        }

        $guid = trim((string) ($entry->guid ?? $entry->id ?? ''));

        return str_starts_with($guid, 'http') ? $guid : null;
    }

    private function publishedAt(SimpleXMLElement $entry): ?\DateTimeInterface
    {
        foreach (['pubDate', 'published', 'updated'] as $field) {
            $value = trim((string) ($entry->{$field} ?? ''));

            if ($value === '') {
                continue;
            }

            try {
                return new \DateTimeImmutable($value);
            } catch (Throwable) {
                continue;
            }
        }

        // Dublin Core, bei einigen Behoerdenfeeds die einzige Datumsangabe.
        $dc = trim((string) ($entry->children('http://purl.org/dc/elements/1.1/')->date ?? ''));

        if ($dc !== '') {
            try {
                return new \DateTimeImmutable($dc);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Grundgewicht der Quelle, abgeschwaecht mit dem Alter der Meldung: was
     * aelter als das Frischefenster ist, zaehlt nur noch halb.
     *
     * @param  array<string, mixed>  $feed
     * @param  array<int, string>  $matched
     */
    private function strength(array $feed, array $matched, ?\DateTimeInterface $publishedAt): float
    {
        $base = (float) $feed['signal_strength'];

        // Jeder zusaetzliche Keyword-Treffer erhoeht das Gewicht leicht.
        $base += min(0.2, count($matched) * 0.05);

        $freshDays = max(1, (int) config('content.sources.rss_feeds.fresh_days', 14));

        if ($publishedAt !== null && $publishedAt->getTimestamp() < now()->subDays($freshDays)->getTimestamp()) {
            $base *= 0.5;
        }

        return max(0.0, min(1.0, $base));
    }

    /**
     * Alle Feeds, die fuer diesen Mandanten gelten: global + Branche +
     * Bundeslaender, die der Mandant ueberhaupt bespielt.
     *
     * @return array<int, array<string, mixed>>
     */
    private function feedsFor(TenantContext $context): array
    {
        $defaults = (array) config('content_feeds.defaults', []);
        $branch = $context->branch();

        $raw = array_merge(
            (array) config('content_feeds.global', []),
            $branch !== null ? (array) config("content_feeds.branches.{$branch}", []) : [],
            $context->allowsRegionScope('state') ? (array) config('content_feeds.states', []) : [],
        );

        $feeds = [];

        foreach ($raw as $entry) {
            $feed = $this->normalizeFeed((array) $entry, $defaults);

            if ($feed === null || isset($feeds[$feed['key']])) {
                continue;
            }

            if (! $this->appliesToTenant($feed, $context)) {
                continue;
            }

            $feeds[$feed['key']] = $feed;
        }

        return array_values($feeds);
    }

    /**
     * Landesfeeds laufen nur fuer Mandanten, die dieses Land bespielen. Ohne
     * gepflegte preferred_states gilt kein Land als bevorzugt und die
     * Landesfeeds bleiben aus — sonst holt jedes Portal 16 Laender ab.
     *
     * @param  array<string, mixed>  $feed
     */
    private function appliesToTenant(array $feed, TenantContext $context): bool
    {
        if ($feed['region_scope'] !== 'state') {
            return $context->allowsRegionScope('national');
        }

        $preferred = $context->preferredStates();

        return $feed['region_code'] !== null && in_array($feed['region_code'], $preferred, true);
    }

    /**
     * @param  array<string, mixed>  $feed
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>|null
     */
    private function normalizeFeed(array $feed, array $defaults): ?array
    {
        $key = trim((string) ($feed['key'] ?? ''));
        $url = trim((string) ($feed['url'] ?? ''));

        if ($key === '' || ! str_starts_with($url, 'http')) {
            return null;
        }

        $merged = array_merge($defaults, $feed);

        if (! (bool) ($merged['enabled'] ?? true)) {
            return null;
        }

        $regionCode = $merged['region_code'] ?? null;

        if ($regionCode !== null && ! StateCatalog::exists((string) $regionCode)) {
            Log::warning('Feed mit unbekanntem region_code uebersprungen.', [
                'connector' => $this->key(),
                'feed' => $key,
                'region_code' => $regionCode,
            ]);

            return null;
        }

        return [
            'key' => $key,
            'url' => $url,
            'name' => (string) ($merged['name'] ?? $key),
            'type' => (string) ($merged['type'] ?? 'rss'),
            'region_scope' => (string) ($merged['region_scope'] ?? 'national'),
            'region_code' => $regionCode !== null ? (string) $regionCode : null,
            'signal_strength' => (float) ($merged['signal_strength'] ?? 0.35),
            'max_items' => (int) ($merged['max_items'] ?? 20),
            'filter_by_keywords' => (bool) ($merged['filter_by_keywords'] ?? true),
        ];
    }
}
