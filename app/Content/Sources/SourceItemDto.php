<?php

declare(strict_types=1);

namespace App\Content\Sources;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Normalisiertes Rohsignal, wie es jeder Connector zurueckgibt (#7).
 *
 * Die Normalisierung passiert im Konstruktor, damit ein Connector nur noch
 * Rohwerte reichen muss: Snippet auf 300 Zeichen, Signalstaerke auf 0..1,
 * Keywords getrimmt und dedupliziert.
 */
final class SourceItemDto
{
    public const SNIPPET_MAX_LENGTH = 300;

    public readonly string $type;

    public readonly string $title;

    public readonly ?string $url;

    public readonly ?string $snippet;

    public readonly string $regionScope;

    public readonly ?string $regionCode;

    /** @var array<int, string> */
    public readonly array $keywords;

    public readonly float $signalStrength;

    public readonly ?CarbonImmutable $publishedAt;

    /** @var array<string, mixed> */
    public readonly array $raw;

    public readonly ?string $externalId;

    public readonly string $language;

    /**
     * Ersetzt URL + Titel als Grundlage des Fingerprints. Quellen, die
     * dasselbe Objekt unter unveraenderter URL fortschreiben (Foerderprogramme
     * mit Aenderungsdatum, Statistiktabellen je Stichtag), geben hier den
     * Wert an, der eine Aktualisierung von einem Duplikat unterscheidet.
     */
    public readonly ?string $fingerprintSeed;

    /**
     * @param  array<int, string>  $keywords
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        string $type,
        string $title,
        ?string $url = null,
        ?string $snippet = null,
        string $regionScope = 'national',
        ?string $regionCode = null,
        array $keywords = [],
        float $signalStrength = 0.0,
        ?DateTimeInterface $publishedAt = null,
        array $raw = [],
        ?string $externalId = null,
        string $language = 'de',
        ?string $fingerprintSeed = null,
    ) {
        $this->type = Str::limit(trim($type), 32, '');
        $this->title = Str::limit(trim(preg_replace('/\s+/u', ' ', $title) ?? ''), 255, '');
        $this->url = $url !== null && trim($url) !== '' ? Str::limit(trim($url), 2048, '') : null;
        $this->snippet = $this->normalizeSnippet($snippet);
        $this->regionScope = $regionScope;
        $this->regionCode = $regionCode !== null && trim($regionCode) !== '' ? trim($regionCode) : null;
        $this->keywords = $this->normalizeKeywords($keywords);
        $this->signalStrength = max(0.0, min(1.0, $signalStrength));
        $this->publishedAt = $publishedAt !== null ? CarbonImmutable::parse($publishedAt) : null;
        $this->raw = $raw;
        $this->externalId = $externalId !== null && trim($externalId) !== '' ? trim($externalId) : null;
        $this->language = $language;
        $this->fingerprintSeed = $fingerprintSeed !== null && trim($fingerprintSeed) !== '' ? trim($fingerprintSeed) : null;
    }

    /**
     * Deduplikations-Hash aus URL und Titel. Gleiche Meldung unter derselben
     * URL erzeugt denselben Fingerprint, unabhaengig vom Abrufzeitpunkt.
     * Mit gesetztem fingerprintSeed zaehlt ausschliesslich dieser Wert.
     */
    public function fingerprint(): string
    {
        if ($this->fingerprintSeed !== null) {
            return hash('sha256', $this->fingerprintSeed);
        }

        $url = $this->url !== null ? rtrim(mb_strtolower($this->url), '/') : '';
        $title = mb_strtolower($this->title);

        return hash('sha256', $url.'|'.$title);
    }

    public function isValid(): bool
    {
        return $this->title !== '';
    }

    /**
     * Attribute fuer App\Content\Models\SourceItem.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(string $sourceKey, DateTimeInterface $fetchedAt): array
    {
        return [
            'source_key' => $sourceKey,
            'source_type' => $this->type,
            'external_id' => $this->externalId,
            'fingerprint' => $this->fingerprint(),
            'title' => $this->title,
            'summary' => $this->snippet,
            'url' => $this->url,
            'language' => $this->language,
            'region_scope' => $this->regionScope,
            'region_code' => $this->regionCode,
            'keywords_json' => $this->keywords,
            'signal_strength' => $this->signalStrength,
            'payload_json' => $this->raw,
            'published_at' => $this->publishedAt,
            'fetched_at' => $fetchedAt,
        ];
    }

    private function normalizeSnippet(?string $snippet): ?string
    {
        if ($snippet === null) {
            return null;
        }

        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($snippet)) ?? '');

        if ($clean === '') {
            return null;
        }

        return Str::limit($clean, self::SNIPPET_MAX_LENGTH, '');
    }

    /**
     * @param  array<int, mixed>  $keywords
     * @return array<int, string>
     */
    private function normalizeKeywords(array $keywords): array
    {
        $normalized = [];

        foreach ($keywords as $keyword) {
            if (! is_string($keyword)) {
                continue;
            }

            $clean = trim(preg_replace('/\s+/u', ' ', $keyword) ?? '');

            if ($clean !== '') {
                $normalized[] = mb_strtolower($clean);
            }
        }

        return array_values(array_unique($normalized));
    }
}
