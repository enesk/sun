<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Sources\AbstractHttpConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Google Trending als RSS (#8), stuendlich.
 *
 * Der Feed liefert die aktuell in Deutschland gefragten Suchbegriffe. Er ist
 * kostenlos, aber breit: fast alles darin ist Sport, Prominenz oder Wetter.
 * Deshalb wird jeder Eintrag gegen die Branchen-Keywords des Mandanten
 * geprueft und nur bei einem Treffer ueberhaupt zu einem source_item.
 *
 * Der Abgleich laeuft ueber Wortstaemme, weil Deutsch flektiert und
 * zusammensetzt: das Branchen-Keyword "Heizung" soll "Heizungen",
 * "Heizungsgesetz" und "Heizungsfoerderung" treffen. Ein einfacher
 * Suffix-Stemmer genuegt dafuer; ein vollstaendiger Stemmer waere fuer eine
 * Ja/Nein-Entscheidung ueber eine Handvoll Keywords zu viel Apparat.
 */
class GoogleTrendsRssConnector extends AbstractHttpConnector
{
    /** Namensraum der ht:-Felder im Trending-Feed. */
    private const HT_NAMESPACE = 'ht';

    /**
     * Suffixe, die der Reihe nach abgeschnitten werden — laengste zuerst,
     * damit "Heizungen" ueber 'ungen' und nicht ueber 'en' abgebaut wird.
     */
    private const SUFFIXES = [
        'ungen', 'innen', 'erin', 'heit', 'keit', 'isch', 'lich', 'ung',
        'end', 'ern', 'em', 'en', 'er', 'es', 'se', 'n', 's', 'e',
    ];

    public function key(): string
    {
        return 'google_trends_rss';
    }

    public function schedule(): string
    {
        return SourceFrequency::HOURLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        if (! $context->allowsRegionScope('national')) {
            return collect();
        }

        $keywords = $context->branchKeywords();

        if ($keywords === []) {
            Log::info('Google Trending uebersprungen: keine Branchen-Keywords gepflegt.', [
                'connector' => $this->key(),
                'tenant_id' => $context->tenantId,
            ]);

            return collect();
        }

        $feed = $this->feed($this->feedUrl(), $context);

        if ($feed === null) {
            return collect();
        }

        $stems = $this->keywordStems($keywords);
        $maxItems = max(1, (int) $this->option('max_items', 60));
        $items = collect();
        $seen = 0;

        // SimpleXML iteriert mit dem Elementnamen als Schluessel, deshalb ein
        // eigener Zaehler.
        foreach ($feed->channel->item ?? [] as $entry) {
            if (++$seen > $maxItems) {
                break;
            }

            $item = $this->toItem($entry, $stems);

            if ($item !== null) {
                $items->push($item);
            }
        }

        return $items;
    }

    /**
     * Ein Feed-Eintrag wird nur dann zum Rohsignal, wenn mindestens ein
     * Branchen-Keyword darin steckt.
     *
     * @param  array<string, array<int, string>>  $stems  Keyword => Wortstaemme
     */
    private function toItem(SimpleXMLElement $entry, array $stems): ?SourceItemDto
    {
        $title = trim((string) $entry->title);

        if ($title === '') {
            return null;
        }

        $ht = $entry->children(self::HT_NAMESPACE, true);
        $news = $this->newsItems($ht);

        // Der Titel allein ist oft nur ein Name; die Schlagzeilen dahinter
        // sagen, worum es geht. Beides zaehlt fuer den Abgleich.
        $haystack = $this->normalize($title.' '.collect($news)->flatten()->implode(' '));
        $matched = $this->matchedKeywords($haystack, $stems);

        if ($matched === []) {
            return null;
        }

        $traffic = $this->traffic((string) ($ht->approx_traffic ?? ''));
        $headline = $news[0]['title'] ?? null;

        return new SourceItemDto(
            type: 'rss',
            title: $title,
            url: $news[0]['url'] ?? $this->exploreUrl($title),
            snippet: $news[0]['snippet'] ?? $headline,
            regionScope: 'national',
            regionCode: $this->countryIso(),
            keywords: array_merge([$title], $matched),
            signalStrength: $this->strength($traffic),
            publishedAt: $this->publishedAt((string) $entry->pubDate),
            raw: [
                'approx_traffic' => $traffic,
                'geo' => $this->geo(),
                'matched_keywords' => $matched,
                'news_items' => $news,
            ],
            externalId: $this->geo().':'.mb_strtolower($title),
        );
    }

    /**
     * ht:news_item ist mehrfach vorhanden; die ersten drei genuegen als
     * Kontext fuer Abgleich und Snippet.
     *
     * @return array<int, array<string, string>>
     */
    private function newsItems(SimpleXMLElement $ht): array
    {
        $news = [];

        foreach ($ht->news_item ?? [] as $entry) {
            $item = $entry->children(self::HT_NAMESPACE, true);

            $news[] = array_filter([
                'title' => trim((string) $item->news_item_title),
                'snippet' => trim((string) $item->news_item_snippet),
                'url' => trim((string) $item->news_item_url),
                'source' => trim((string) $item->news_item_source),
            ], static fn (string $value) => $value !== '');

            if (count($news) >= 3) {
                break;
            }
        }

        return $news;
    }

    /**
     * Welche Branchen-Keywords im Text stecken.
     *
     * @param  array<string, array<int, string>>  $stems
     * @return array<int, string>
     */
    private function matchedKeywords(string $haystack, array $stems): array
    {
        if ($haystack === '') {
            return [];
        }

        $words = explode(' ', $haystack);
        $wordStems = array_unique(array_map(fn (string $word) => $this->stem($word), $words));
        $minLength = max(1, (int) $this->option('min_stem_length', 4));

        $matched = [];

        foreach ($stems as $keyword => $tokens) {
            if ($tokens === []) {
                continue;
            }

            foreach ($tokens as $token) {
                // Lange Staemme duerfen auch im Kompositum treffen
                // ('heiz' in 'heizungsgesetz'), kurze nur als ganzes Wort.
                $hit = mb_strlen($token) >= $minLength
                    ? str_contains($haystack, $token)
                    : in_array($token, $wordStems, true);

                if (! $hit) {
                    continue 2;
                }
            }

            $matched[] = (string) $keyword;
        }

        return $matched;
    }

    /**
     * Jedes Branchen-Keyword auf die Staemme seiner Bestandteile.
     *
     * @param  array<int, string>  $keywords
     * @return array<string, array<int, string>>
     */
    private function keywordStems(array $keywords): array
    {
        $stems = [];

        foreach ($keywords as $keyword) {
            $normalized = $this->normalize($keyword);

            if ($normalized === '') {
                continue;
            }

            $stems[$keyword] = array_values(array_unique(
                array_map(fn (string $word) => $this->stem($word), explode(' ', $normalized))
            ));
        }

        return $stems;
    }

    /**
     * Kleinschreibung, Umlaute aufgeloest, alles andere zu Leerzeichen.
     * Beide Seiten des Abgleichs laufen durch dieselbe Funktion.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(strip_tags($text));

        $text = strtr($text, [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'ú' => 'u',
        ]);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '') ?? '');
    }

    /**
     * Sehr einfacher deutscher Suffix-Stemmer. Es wird hoechstens ein Suffix
     * entfernt, und nur, wenn mindestens vier Zeichen stehen bleiben — sonst
     * schrumpfen kurze Woerter zu Buchstabenresten, die ueberall treffen.
     */
    private function stem(string $word): string
    {
        foreach (self::SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix) && mb_strlen($word) - mb_strlen($suffix) >= 4) {
                return mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
            }
        }

        return $word;
    }

    /**
     * 'ht:approx_traffic' kommt als "200,000+".
     */
    private function traffic(string $value): int
    {
        return (int) preg_replace('/\D/', '', $value);
    }

    /**
     * Signalstaerke linear bis zur konfigurierten Vollausschlag-Schwelle.
     */
    private function strength(int $traffic): float
    {
        $fullScale = max(1, (int) $this->option('traffic_full_scale', 500000));

        return min(1.0, $traffic / $fullScale);
    }

    private function publishedAt(string $pubDate): ?\DateTimeInterface
    {
        if (trim($pubDate) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($pubDate);
        } catch (\Throwable) {
            return null;
        }
    }

    private function feedUrl(): string
    {
        $url = (string) $this->option('feed_url', 'https://trends.google.com/trending/rss');

        return $url.(str_contains($url, '?') ? '&' : '?').'geo='.rawurlencode($this->geo());
    }

    /**
     * Fallback-URL, wenn der Eintrag keine Schlagzeile mitliefert. Sie dient
     * nur als Beleg und geht in den Fingerprint ein; abgerufen wird sie nicht.
     */
    private function exploreUrl(string $title): string
    {
        return 'https://trends.google.com/trends/explore?q='.rawurlencode($title).'&geo='.rawurlencode($this->geo());
    }

    private function geo(): string
    {
        return (string) $this->option('geo', 'DE');
    }

    private function countryIso(): string
    {
        return (string) config('content.regions.country.iso', 'DE');
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.google_trends_rss.{$key}", $default);
    }
}
