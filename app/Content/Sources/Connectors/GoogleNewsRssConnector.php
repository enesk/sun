<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Sources\AbstractHttpConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;
use Throwable;

/**
 * Regionale Nachrichten ueber Google News RSS (#11), sechsstuendlich.
 *
 * Je Branche und Bundesland eine Suchanfrage. Der Feed dient allein als
 * Themenhinweis: "in Bayern wird gerade ueber X gesprochen". Deshalb wird
 * ausdruecklich nur gespeichert, was ohne Lizenzfrage gespeichert werden
 * darf — Titel, Medium, Link und Datum. Die Feed-Beschreibung von Google
 * enthaelt Anrisstexte fremder Verlage und wird verworfen, die verlinkten
 * Artikel werden nicht abgerufen.
 */
class GoogleNewsRssConnector extends AbstractHttpConnector
{
    public function key(): string
    {
        return 'google_news_rss';
    }

    public function schedule(): string
    {
        return SourceFrequency::SIX_HOURLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $branchLabel = $context->branchLabel();

        if ($branchLabel === null) {
            Log::info('Google News uebersprungen: Branche des Mandanten nicht erkannt.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
            ]);

            return collect();
        }

        $items = collect();
        $failures = 0;
        $queries = $this->queries($context, $branchLabel);

        foreach ($queries as $query) {
            try {
                $items = $items->merge($this->readSearch($query, $context));
            } catch (Throwable $exception) {
                $failures++;

                Log::warning('Google-News-Abruf gescheitert.', [
                    'connector' => $this->key(),
                    'query' => $query['term'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($queries !== [] && $failures === count($queries)) {
            throw new \RuntimeException('Google News nicht erreichbar.');
        }

        return $items;
    }

    /**
     * Branche bundesweit plus Branche x Bundesland fuer die Laender, die der
     * Mandant bespielt.
     *
     * @return array<int, array{term: string, region_scope: string, region_code: ?string, region_name: string}>
     */
    private function queries(TenantContext $context, string $branchLabel): array
    {
        $queries = [];

        if ($context->allowsRegionScope('national')) {
            $queries[] = [
                'term' => $branchLabel.' '.(string) $this->option('national_suffix', 'Deutschland'),
                'region_scope' => 'national',
                'region_code' => null,
                'region_name' => 'Deutschland',
            ];
        }

        if (! $context->allowsRegionScope('state')) {
            return $queries;
        }

        $maxStates = max(0, (int) $this->option('max_states_per_run', 4));

        foreach (array_slice($context->preferredStates(), 0, $maxStates) as $iso) {
            $name = StateCatalog::name((string) $iso);

            if ($name === null) {
                continue;
            }

            $queries[] = [
                'term' => "{$branchLabel} {$name}",
                'region_scope' => 'state',
                'region_code' => (string) $iso,
                'region_name' => $name,
            ];
        }

        return $queries;
    }

    /**
     * @param  array{term: string, region_scope: string, region_code: ?string, region_name: string}  $query
     * @return Collection<int, SourceItemDto>
     */
    private function readSearch(array $query, TenantContext $context): Collection
    {
        $url = $this->searchUrl($query['term']);
        $feed = $this->feed($url, $context);

        if ($feed === null) {
            return collect();
        }

        $maxItems = max(1, (int) $this->option('max_items_per_query', 15));
        $maxAgeDays = max(1, (int) $this->option('max_age_days', 21));
        $oldest = now()->subDays($maxAgeDays);

        $items = collect();

        foreach ($feed->channel->item ?? [] as $entry) {
            if ($items->count() >= $maxItems) {
                break;
            }

            $title = trim((string) $entry->title);

            if ($title === '') {
                continue;
            }

            $publishedAt = $this->publishedAt($entry);

            if ($publishedAt !== null && $publishedAt->getTimestamp() < $oldest->getTimestamp()) {
                continue;
            }

            $sourceName = trim((string) ($entry->source ?? ''));

            $items->push(new SourceItemDto(
                type: 'news',
                // Google haengt " - <Medium>" an den Titel; das Medium steht
                // ohnehin im eigenen Feld.
                title: $this->stripSource($title, $sourceName),
                url: trim((string) $entry->link) ?: null,
                // Bewusst leer: kein Anrisstext, kein Artikeltext.
                snippet: null,
                regionScope: $query['region_scope'],
                regionCode: $query['region_code'],
                keywords: [],
                signalStrength: (float) $this->option('signal_strength', 0.4),
                publishedAt: $publishedAt,
                raw: [
                    'source_name' => $sourceName,
                    'query' => $query['term'],
                    'region_name' => $query['region_name'],
                ],
                externalId: 'gnews:'.mb_substr(hash('sha256', $title), 0, 32),
            ));
        }

        return $items;
    }

    /**
     * "Neue Foerderung startet - Sueddeutsche Zeitung" => "Neue Foerderung startet".
     */
    private function stripSource(string $title, string $sourceName): string
    {
        if ($sourceName === '' || ! str_ends_with($title, " - {$sourceName}")) {
            return $title;
        }

        return trim(mb_substr($title, 0, mb_strlen($title) - mb_strlen($sourceName) - 3));
    }

    private function publishedAt(SimpleXMLElement $entry): ?\DateTimeInterface
    {
        $value = trim((string) $entry->pubDate);

        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function searchUrl(string $term): string
    {
        $base = (string) $this->option('search_url', 'https://news.google.com/rss/search');

        return $base.'?q='.rawurlencode($term)
            .'&hl='.rawurlencode((string) $this->option('hl', 'de'))
            .'&gl='.rawurlencode((string) $this->option('gl', 'DE'))
            .'&ceid='.rawurlencode((string) $this->option('ceid', 'DE:de'));
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.google_news_rss.{$key}", $default);
    }
}
