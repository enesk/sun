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
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Foerderdatenbank des Bundes (#11), taeglich.
 *
 * Die Programmliste unter foerderdatenbank.de ist die einzige Stelle, an der
 * Bundes-, Landes- und EU-Programme zusammen gepflegt werden. Einen Feed oder
 * eine API gibt es nicht, deshalb wird die Ergebnisliste gelesen — nicht die
 * Detailseiten. Das genuegt: Titel, Foerdergebiet, Foerderberechtigte und
 * Kurzbeschreibung stehen bereits in der Liste.
 *
 * Zwei Dinge sind hier anders als bei den uebrigen Connectoren:
 *
 * 1. Takt. Zwischen zwei Abrufen liegt mindestens eine Sekunde
 *    (request_interval_ms). Der Crawler holt je Bundesland eine Seite, ein
 *    Lauf sind also hoechstens 17 Abrufe ueber 17 Sekunden.
 *
 * 2. Aenderungserkennung. Ein Programm behaelt seine URL, auch wenn sich die
 *    Konditionen aendern. Der Fingerprint enthaelt deshalb ausdruecklich das
 *    Aenderungsdatum: gleiche Fassung = Duplikat, neue Fassung = neues
 *    Rohsignal.
 */
class FoerderdatenbankConnector extends AbstractHttpConnector
{
    public function key(): string
    {
        return 'foerderdatenbank';
    }

    public function schedule(): string
    {
        return SourceFrequency::DAILY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $matcher = new KeywordMatcher($context->branchKeywords());
        $items = collect();
        $failures = 0;
        $requests = 0;

        foreach ($this->targets($context) as $target) {
            if ($requests > 0) {
                $this->throttle();
            }

            $requests++;

            try {
                $items = $items->merge($this->readList($target, $context, $matcher));
            } catch (Throwable $exception) {
                $failures++;

                Log::warning('Foerderdatenbank-Abruf gescheitert.', [
                    'connector' => $this->key(),
                    'region_code' => $target['region_code'],
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        if ($requests > 0 && $failures === $requests) {
            throw new \RuntimeException('Foerderdatenbank nicht erreichbar.');
        }

        return $items;
    }

    /**
     * Bundesweit plus die Bundeslaender, die der Mandant bespielt.
     *
     * @return array<int, array{region_scope: string, region_code: ?string, filter: ?string}>
     */
    private function targets(TenantContext $context): array
    {
        $targets = [];

        if ($context->allowsRegionScope('national')) {
            $targets[] = ['region_scope' => 'national', 'region_code' => null, 'filter' => 'Bundesweit'];
        }

        if (! $context->allowsRegionScope('state')) {
            return $targets;
        }

        $maxStates = max(0, (int) $this->option('max_states_per_run', 4));

        foreach (array_slice($context->preferredStates(), 0, $maxStates) as $iso) {
            $name = StateCatalog::name((string) $iso);

            if ($name === null) {
                continue;
            }

            $targets[] = ['region_scope' => 'state', 'region_code' => (string) $iso, 'filter' => $name];
        }

        return $targets;
    }

    /**
     * @param  array{region_scope: string, region_code: ?string, filter: ?string}  $target
     * @return Collection<int, SourceItemDto>
     */
    private function readList(array $target, TenantContext $context, KeywordMatcher $matcher): Collection
    {
        $response = $this->get($this->listUrl(), $context, $this->query($target));

        if ($response === null) {
            return collect();
        }

        $crawler = new Crawler($response->body(), $this->listUrl());
        $selector = (string) $this->option('result_selector', '.card--fundingprogram');
        $nodes = $crawler->filter($selector);

        if ($nodes->count() === 0) {
            // Die Liste hat ihr Markup geaendert. Als Fehler melden, damit es
            // im Quellen-Monitor auffaellt statt still zu versanden.
            throw new \RuntimeException("Kein Treffer fuer Selektor '{$selector}' — Markup der Programmliste geaendert?");
        }

        $maxItems = max(1, (int) $this->option('max_items_per_region', 40));
        $filter = (bool) $this->option('filter_by_keywords', true) && $matcher->hasKeywords();

        return collect($nodes->each(fn (Crawler $node): ?SourceItemDto => $this->toItem($node, $target, $matcher, $filter)))
            ->filter()
            ->take($maxItems)
            ->values();
    }

    /**
     * @param  array{region_scope: string, region_code: ?string, filter: ?string}  $target
     */
    private function toItem(Crawler $node, array $target, KeywordMatcher $matcher, bool $filter): ?SourceItemDto
    {
        $title = $this->text($node, (string) $this->option('title_selector', '.card--title'));

        if ($title === '') {
            return null;
        }

        $summary = $this->text($node, (string) $this->option('summary_selector', '.card--content'));
        $url = $this->href($node);
        $changedAt = $this->changedAt($node);

        $matched = $matcher->matches($title.' '.$summary);

        if ($filter && $matched === []) {
            return null;
        }

        // Das Foerdergebiet steht in der Liste als eigenes Feld; es ist
        // genauer als der Filter, mit dem abgefragt wurde. Ein bundesweites
        // Programm taucht auch in der Landesabfrage auf und darf dort nicht
        // faelschlich dem Land zugeschlagen werden.
        $area = $this->text($node, (string) $this->option('region_selector', '.card--list'));
        $regionCode = str_contains(mb_strtolower($area), 'bundesweit')
            ? null
            : (StateCatalog::fromText($area) ?? $target['region_code']);
        $regionScope = $regionCode === null ? 'national' : 'state';

        return new SourceItemDto(
            type: 'funding',
            title: $title,
            url: $url,
            snippet: $summary !== '' ? $summary : null,
            regionScope: $regionScope,
            regionCode: $regionCode,
            keywords: $matched,
            signalStrength: (float) $this->option('signal_strength', 0.7),
            publishedAt: $changedAt,
            raw: [
                'funding_area' => $area,
                'changed_at' => $changedAt?->format('Y-m-d'),
                'matched_keywords' => $matched,
                'queried_region' => $target['filter'],
            ],
            externalId: 'fdb:'.mb_substr(hash('sha256', (string) $url), 0, 32),
            // Hash aus Titel + Aenderungsdatum: eine neue Fassung desselben
            // Programms ist ein neues Rohsignal, ein unveraenderter Abruf ein
            // Duplikat.
            fingerprintSeed: 'foerderdatenbank|'.mb_strtolower($title).'|'.($changedAt?->format('Y-m-d') ?? 'ohne-datum'),
        );
    }

    /**
     * Die Liste nennt das Aenderungsdatum als "Stand: 12.03.2026" oder in
     * einem <time datetime="...">-Element.
     */
    private function changedAt(Crawler $node): ?\DateTimeImmutable
    {
        $iso = $this->attribute($node, 'time', 'datetime');

        if ($iso !== '') {
            try {
                return new \DateTimeImmutable($iso);
            } catch (Throwable) {
                // weiter mit dem Textformat
            }
        }

        if (preg_match('/(\d{2})\.(\d{2})\.(\d{4})/', $node->text(''), $matches) === 1) {
            $date = \DateTimeImmutable::createFromFormat('!d.m.Y', "{$matches[1]}.{$matches[2]}.{$matches[3]}");

            return $date === false ? null : $date;
        }

        return null;
    }

    private function href(Crawler $node): ?string
    {
        $href = $this->attribute($node, 'a', 'href');

        if ($href === '') {
            return null;
        }

        if (str_starts_with($href, 'http')) {
            return $href;
        }

        return rtrim((string) $this->option('base_url', 'https://www.foerderdatenbank.de'), '/').'/'.ltrim($href, '/');
    }

    private function text(Crawler $node, string $selector): string
    {
        if ($selector === '') {
            return '';
        }

        try {
            $found = $node->filter($selector);
        } catch (Throwable) {
            return '';
        }

        if ($found->count() === 0) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $found->first()->text('')) ?? '');
    }

    private function attribute(Crawler $node, string $selector, string $attribute): string
    {
        try {
            $found = $node->filter($selector);
        } catch (Throwable) {
            return '';
        }

        return $found->count() === 0 ? '' : trim((string) $found->first()->attr($attribute));
    }

    /**
     * @param  array{region_scope: string, region_code: ?string, filter: ?string}  $target
     * @return array<string, string>
     */
    private function query(array $target): array
    {
        $query = (array) $this->option('query', []);

        if ($target['filter'] !== null) {
            $parameter = (string) $this->option('region_parameter', 'filterFoerdergebiet');
            $query[$parameter] = $target['filter'];
        }

        return array_map(static fn ($value): string => (string) $value, $query);
    }

    private function listUrl(): string
    {
        return (string) $this->option(
            'list_url',
            'https://www.foerderdatenbank.de/SiteGlobals/FDB/Forms/Suche/Foederprogrammsuche_Formular.html',
        );
    }

    /**
     * Hoeflichkeitspause zwischen zwei Abrufen: hoechstens ein Request je
     * Sekunde gegen dieselbe Seite.
     */
    private function throttle(): void
    {
        $interval = max(0, (int) $this->option('request_interval_ms', 1000));

        if ($interval > 0) {
            usleep($interval * 1000);
        }
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.foerderdatenbank.{$key}", $default);
    }
}
