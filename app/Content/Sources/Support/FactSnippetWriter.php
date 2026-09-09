<?php

declare(strict_types=1);

namespace App\Content\Sources\Support;

use App\Content\Models\FactSnippet;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;

/**
 * Schreibt Kennzahlen als fact_snippets (#11).
 *
 * Gemeinsame Stelle fuer StatisticsConnector und PortalDataProvider, damit
 * Fingerprint, Haltbarkeit und Pflichtfelder ueberall gleich entstehen. Der
 * Fingerprint bildet Kennzahl + Region + Bezugszeitraum ab: derselbe Wert aus
 * einem neuen Abruf aktualisiert die vorhandene Zeile, ein neuer Zeitraum legt
 * eine neue an.
 *
 * Muss im Tenant-Kontext aufgerufen werden.
 */
final class FactSnippetWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $snippets  je Eintrag siehe write()
     * @return array{written: int, keys: array<int, string>}
     */
    public function writeMany(array $snippets): array
    {
        $keys = [];

        foreach ($snippets as $snippet) {
            $record = $this->write($snippet);

            if ($record !== null) {
                $keys[] = (string) $record->fact_key;
            }
        }

        return ['written' => count($keys), 'keys' => array_values(array_unique($keys))];
    }

    /**
     * @param  array{
     *     fact_key: string,
     *     statement: string,
     *     value?: string|int|float|null,
     *     unit?: string|null,
     *     period?: string|null,
     *     region_scope?: string,
     *     region_code?: string|null,
     *     source_name?: string|null,
     *     source_url?: string|null,
     *     retrieved_at?: DateTimeInterface|null,
     *     valid_until?: DateTimeInterface|null,
     *     valid_days?: int|null,
     *     confidence?: float,
     *     source_item_id?: int|null,
     * }  $attributes
     */
    public function write(array $attributes): ?FactSnippet
    {
        $factKey = trim((string) ($attributes['fact_key'] ?? ''));
        $statement = trim((string) ($attributes['statement'] ?? ''));

        if ($factKey === '' || $statement === '') {
            return null;
        }

        $retrievedAt = $this->toDate($attributes['retrieved_at'] ?? null) ?? CarbonImmutable::now();
        $regionScope = (string) ($attributes['region_scope'] ?? 'national');
        $regionCode = $attributes['region_code'] ?? null;
        $period = $attributes['period'] ?? null;

        $fingerprint = hash('sha256', implode('|', [
            $factKey,
            $regionScope,
            (string) $regionCode,
            (string) $period,
        ]));

        $snippet = FactSnippet::query()->firstOrNew(['fingerprint' => $fingerprint]);

        $snippet->fill([
            'fact_key' => Str::limit($factKey, 128, ''),
            'statement' => $statement,
            'value' => $this->stringify($attributes['value'] ?? null),
            'unit' => $this->limit($attributes['unit'] ?? null, 32),
            'period' => $this->limit($period, 64),
            'region_scope' => $regionScope,
            'region_code' => $this->limit($regionCode, 32),
            'source_name' => $this->limit($attributes['source_name'] ?? null, 255),
            'source_url' => $this->limit($attributes['source_url'] ?? null, 2048),
            'source_item_id' => $attributes['source_item_id'] ?? null,
            'retrieved_at' => $retrievedAt,
            'valid_until' => $this->validUntil($attributes, $retrievedAt),
            'confidence' => (float) ($attributes['confidence'] ?? 1.0),
        ]);

        $snippet->fingerprint = $fingerprint;
        $snippet->save();

        return $snippet;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validUntil(array $attributes, CarbonImmutable $retrievedAt): ?CarbonImmutable
    {
        $explicit = $this->toDate($attributes['valid_until'] ?? null);

        if ($explicit !== null) {
            return $explicit;
        }

        $days = $attributes['valid_days'] ?? null;

        return is_numeric($days) ? $retrievedAt->addDays((int) $days) : null;
    }

    private function toDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::parse($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return CarbonImmutable::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            // Ganze Zahlen ohne Nachkommastellen, sonst hoechstens zwei.
            $value = fmod($value, 1.0) === 0.0 ? (string) (int) $value : number_format($value, 2, '.', '');
        }

        return Str::limit((string) $value, 128, '');
    }

    private function limit(mixed $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim((string) $value);

        return $clean === '' ? null : Str::limit($clean, $length, '');
    }
}
