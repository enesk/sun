<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Enums\TrustLevel;
use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Models\Topic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt das Fakten-Set eines Themas versioniert (#8).
 *
 * Je Schluessel gibt es hoechstens einen aktuellen Fakt (is_current):
 *  - neuer Schluessel      -> neuer Datensatz mit first_seen_at
 *  - geaenderter Wert      -> alter Datensatz is_current = false, neuer
 *                             Datensatz mit first_seen_at
 *  - unveraenderter Wert   -> nur last_seen_at; war die bisherige Quelle
 *                             nicht erreichbar (broken_at, #27) und kommt der
 *                             Wert jetzt aus einer anderen, wechselt nur die
 *                             Quelle (source_replaced) — keine inhaltliche
 *                             Aenderung, kein Changelog, kein lastmod
 *  - nicht mehr geliefert  -> bleibt unveraendert aktuell (kein last_seen_at);
 *                             ein Fakt verschwindet nicht, nur weil eine
 *                             Suche ihn nicht wiederfindet.
 *
 * "Wert" meint wie beim facts_hash das Tupel (value, unit, valid_from),
 * verglichen in der Normalform des FactNormalizer (#9): '1.500 €' und
 * '1500 Euro' sind derselbe Wert.
 * Quellen landen je Thema und URL einmal in guide_sources; gespeichert werden
 * nur Titel, URL, Herausgeber und Datum, nie Seitentext.
 */
final class FactStore
{
    public function __construct(
        private readonly FactNormalizer $normalizer,
    ) {}

    /**
     * Erwartet je Schluessel genau einen Eintrag (Konflikte loest ResearchService).
     *
     * @param  array<int, array{key: string, label: string, value: string, unit: ?string, valid_from: ?string, stale_source: bool, source: array{url: string, title: ?string, publisher: ?string, published_at: ?string, trust_level: TrustLevel}}>  $facts
     * @return array{created: array<int, string>, changed: array<int, array{key: string, old_value: string, new_value: string}>, confirmed: array<int, string>, unconfirmed: array<int, string>, source_replaced: array<int, array{key: string, old_url: string, new_url: string}>}
     */
    public function store(Topic $topic, array $facts, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return DB::connection($topic->getConnectionName())->transaction(function () use ($topic, $facts, $now): array {
            $result = ['created' => [], 'changed' => [], 'confirmed' => [], 'unconfirmed' => [], 'source_replaced' => []];

            /** @var \Illuminate\Support\Collection<string, Fact> $current */
            $current = $topic->currentFacts()->lockForUpdate()->get()->keyBy('key');
            $sources = $topic->sources()->get();

            foreach ($facts as $fact) {
                $existing = $current->get($fact['key']);

                if ($existing !== null && $this->sameValue($existing, $fact)) {
                    $attributes = ['last_seen_at' => $now];
                    $oldSource = $sources->firstWhere('id', $existing->source_id);

                    if ($oldSource?->isBroken() && ResearchService::urlKey((string) $oldSource->url) !== ResearchService::urlKey($fact['source']['url'])) {
                        $attributes['source_id'] = $this->source($topic, $sources, $fact['source'], $now)->getKey();
                        $result['source_replaced'][] = ['key' => $fact['key'], 'old_url' => (string) $oldSource->url, 'new_url' => $fact['source']['url']];
                    }

                    $existing->forceFill($attributes)->save();
                    $result['confirmed'][] = $fact['key'];

                    continue;
                }

                $source = $this->source($topic, $sources, $fact['source'], $now);

                if ($existing !== null) {
                    $existing->forceFill(['is_current' => false])->save();
                    $result['changed'][] = [
                        'key' => $fact['key'],
                        'old_value' => $this->display($existing->value, $existing->unit),
                        'new_value' => $this->display($fact['value'], $fact['unit']),
                    ];
                } else {
                    $result['created'][] = $fact['key'];
                }

                $topic->facts()->create([
                    'key' => $fact['key'],
                    'label' => $fact['label'],
                    'value' => $fact['value'],
                    'unit' => $fact['unit'],
                    'valid_from' => $fact['valid_from'],
                    'source_id' => $source->getKey(),
                    'stale_source' => $fact['stale_source'],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'is_current' => true,
                ]);
            }

            $delivered = array_column($facts, 'key');
            $result['unconfirmed'] = $current->keys()
                ->reject(fn (string $key): bool => in_array($key, $delivered, true))
                ->values()
                ->all();

            return $result;
        });
    }

    /**
     * @param  array{key: string, label: string, value: string, unit: ?string, valid_from: ?string}  $fact
     */
    private function sameValue(Fact $existing, array $fact): bool
    {
        return $this->normalizer->same(
            ['value' => $existing->value, 'unit' => $existing->unit, 'valid_from' => $existing->valid_from?->toDateString()],
            $fact,
        );
    }

    /**
     * Quelle je Thema und URL einmal; eine erneut gefundene Quelle bekommt
     * das neue Abrufdatum und die aktuelle Einstufung.
     *
     * @param  \Illuminate\Support\Collection<int, Source>  $sources
     * @param  array{url: string, title: ?string, publisher: ?string, published_at: ?string, trust_level: TrustLevel}  $data
     */
    private function source(Topic $topic, $sources, array $data, Carbon $now): Source
    {
        $attributes = [
            'title' => $data['title'] !== null ? mb_substr($data['title'], 0, 255) : null,
            'publisher' => $data['publisher'] !== null ? mb_substr($data['publisher'], 0, 255) : null,
            'published_at' => $data['published_at'],
            'retrieved_at' => $now,
            'trust_level' => $data['trust_level'],
        ];

        $source = $sources->first(fn (Source $source): bool => $source->url === $data['url']);

        if ($source !== null) {
            $source->forceFill($attributes)->save();

            return $source;
        }

        $source = $topic->sources()->create(['url' => $data['url']] + $attributes);
        $sources->push($source);

        return $source;
    }

    private function display(string $value, ?string $unit): string
    {
        return trim($value.' '.($unit ?? ''));
    }
}
