<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Content\Models\DraftSource;
use App\Models\Portal\City;
use App\Models\Portal\Post;
use App\Support\CityUrl;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bereitet die Bausteine des article_blueprint (Design SUN-RC-004) fuer das
 * Ratgeber-Template auf: Kurzantwort, Key-Facts, FAQ, Quellen, Regionalblock
 * und Titelbild.
 *
 * Die Zusatzfelder liegen am ArticleDraft (#3), der veroeffentlichte Beitrag
 * bleibt die Anzeigequelle. Fehlt der Entwurf — etwa bei redaktionell von Hand
 * gepflegten Beitraegen —, liefert jede Methode einen leeren Wert und der Block
 * entfaellt im Template.
 */
class ArticleBlockPresenter
{
    /** Quellen aelter als so viele Monate bekommen einen Standhinweis. */
    private const STALE_SOURCE_MONTHS = 24;

    public function shortAnswer(?ArticleDraft $draft): ?string
    {
        $value = trim((string) $draft?->short_answer);

        return $value !== '' ? $value : null;
    }

    /**
     * Key-Facts-Tabelle. Akzeptiert sowohl eine Liste aus Merkmal/Wert-Paaren
     * als auch eine einfache Zuordnung, weil der Generator (#14) das Schema
     * noch nicht festgeschrieben hat.
     *
     * @return array{rows: list<array{label: string, value: string}>, caption: ?string, numeric: bool}
     */
    public function keyFacts(?ArticleDraft $draft): array
    {
        $raw = $draft?->key_facts_json ?? [];
        $rows = [];
        $caption = null;

        foreach ($raw as $key => $entry) {
            if ($key === 'caption' || $key === 'source') {
                $caption = is_string($entry) ? $entry : null;

                continue;
            }

            if (is_array($entry)) {
                $label = (string) ($entry['label'] ?? $entry['fact'] ?? $entry['key'] ?? (is_string($key) ? $key : ''));
                $value = (string) ($entry['value'] ?? $entry['text'] ?? '');
                $rowSource = trim((string) ($entry['source'] ?? ''));

                if ($rowSource !== '' && $caption === null) {
                    $caption = $rowSource;
                }
            } else {
                $label = is_string($key) ? $key : '';
                $value = (string) $entry;
            }

            if (trim($label) === '' || trim($value) === '') {
                continue;
            }

            $rows[] = ['label' => trim($label), 'value' => trim($value)];
        }

        $rows = array_slice($rows, 0, 7);

        return [
            'rows' => $rows,
            'caption' => $caption,
            'numeric' => $rows !== [] && collect($rows)->every(
                fn (array $row): bool => (bool) preg_match('/^[\d\s.,€%–\-–]+$/u', $row['value'])
            ),
        ];
    }

    /**
     * @return list<array{question: string, answer: string}>
     */
    public function faq(?ArticleDraft $draft): array
    {
        $items = [];

        foreach ($draft?->faq_json ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $question = trim((string) ($entry['question'] ?? $entry['frage'] ?? ''));
            $answer = trim((string) ($entry['answer'] ?? $entry['antwort'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = ['question' => $question, 'answer' => $answer];
        }

        return array_slice($items, 0, 6);
    }

    /**
     * @return list<array{title: string, url: ?string, publisher: ?string, published_at: ?Carbon, stale_year: ?string}>
     */
    public function sources(?ArticleDraft $draft): array
    {
        if ($draft === null) {
            return [];
        }

        return $draft->sources()
            ->where('is_cited', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (DraftSource $source): array {
                $publishedAt = $source->published_at !== null ? Carbon::parse($source->published_at) : null;

                return [
                    'title' => (string) ($source->title ?: $source->url),
                    'url' => $source->url,
                    'publisher' => $source->publisher,
                    'published_at' => $publishedAt,
                    'stale_year' => $publishedAt && $publishedAt->lt(now()->subMonths(self::STALE_SOURCE_MONTHS))
                        ? $publishedAt->format('Y')
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Aenderungshinweise („Aktualisiert am ...", #24), juengster zuerst.
     *
     * Eintraege ohne Datum fallen heraus: sie gehoeren zu einer Fassung, die
     * noch nicht erschienen ist — der Publisher stempelt das Datum erst beim
     * Livegang. Ein Ratgeber ohne Aktualisierung liefert eine leere Liste, und
     * der Block entfaellt im Template.
     *
     * @return list<array{at: Carbon, summary: string, reasons: list<string>}>
     */
    public function changelog(?ArticleDraft $draft): array
    {
        if ($draft === null) {
            return [];
        }

        $entries = [];

        foreach ($draft->changelogEntries() as $entry) {
            if ($entry['at'] === null) {
                continue;
            }

            $entries[] = [
                'at' => Carbon::parse($entry['at']),
                'summary' => $entry['summary'],
                'reasons' => $entry['reasons'],
            ];
        }

        usort($entries, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return $entries;
    }

    /**
     * Regionalblock. Ziel ist immer eine echte Seite des Tenants: die Stadtseite,
     * wenn der Ort im Portal gefuehrt wird, sonst die gefilterte Firmensuche.
     *
     * @return array{name: string, url: string, company_count: ?int, facts: list<array{label: string, value: string}>, intro: ?string, outro: ?string}|null
     */
    public function region(?ArticleDraft $draft): ?array
    {
        if ($draft === null || $draft->region_scope !== 'city') {
            return null;
        }

        $code = trim((string) $draft->region_code);

        if ($code === '') {
            return null;
        }

        $city = City::query()
            ->where('slug', Str::slug($code))
            ->orWhere('name', $code)
            ->first();

        if ($city === null) {
            return null;
        }

        $outline = $draft->outline_json ?? [];

        return [
            'name' => $city->name,
            'url' => CityUrl::show($city),
            'company_count' => $city->companies()->count(),
            'facts' => $this->pairs($outline['regional_facts'] ?? []),
            'intro' => isset($outline['regional_intro']) ? (string) $outline['regional_intro'] : null,
            'outro' => isset($outline['regional_outro']) ? (string) $outline['regional_outro'] : null,
        ];
    }

    /**
     * HowTo-Auszeichnung. Die Pipeline fuehrt dafuer keine eigene Spalte; der
     * Generator (#14) legt die Schritte unter `howto` in der Gliederung ab.
     *
     * @return array{name: string, steps: list<array{name: string, text: string}>}|null
     */
    public function howTo(?ArticleDraft $draft): ?array
    {
        $howTo = ($draft?->outline_json ?? [])['howto'] ?? null;

        if (! is_array($howTo)) {
            return null;
        }

        $steps = [];

        foreach ($howTo['steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $name = trim((string) ($step['name'] ?? ''));
            $text = trim((string) ($step['text'] ?? ''));

            if ($name === '' || $text === '') {
                continue;
            }

            $steps[] = ['name' => $name, 'text' => $text];
        }

        if ($steps === []) {
            return null;
        }

        return [
            'name' => trim((string) ($howTo['name'] ?? $draft->title)),
            'steps' => $steps,
        ];
    }

    /**
     * Titelbild mit fest reservierter Geometrie (16:9). Die Breiten stammen
     * aus dem Asset-Lauf (#16): `assets_json.hero.variants` haelt je Variante
     * URL, Breite und Hoehe, `hero_image_alt` den Alt-Text und
     * `assets_json.hero.credit_html` die verlinkte Attribution.
     *
     * Ohne Entwurf — oder bei einem von Hand gepflegten Beitrag — bleibt es
     * beim Medien-Anhang des Beitrags; die Geometrie bleibt in beiden Faellen
     * gesetzt, damit kein Layoutsprung entsteht.
     *
     * @return array{src: string, srcset: ?string, webp: ?string, width: int, height: int, alt: string, credit: ?string, credit_html: ?string, source: ?string}|null
     */
    public function heroImage(Post $post, ?ArticleDraft $draft = null): ?array
    {
        $fromDraft = $this->heroImageFromDraft($draft);

        if ($fromDraft !== null) {
            return $fromDraft;
        }

        $url = $post->featured_image_url;

        if (! $url) {
            return null;
        }

        $media = $post->getFirstMedia('featured_image');
        $srcset = null;
        $webp = null;

        if ($media) {
            $candidates = [];

            foreach (['hero_640' => 640, 'hero_960' => 960, 'hero_1440' => 1440] as $conversion => $width) {
                if ($media->hasGeneratedConversion($conversion)) {
                    $candidates[] = $media->getUrl($conversion).' '.$width.'w';
                }
            }

            if ($candidates !== []) {
                $srcset = implode(', ', $candidates);
                $webp = $srcset;
            }
        }

        return [
            'src' => $url,
            'srcset' => $srcset,
            'webp' => $webp,
            'width' => 1440,
            'height' => 810,
            'alt' => $draft?->hero_image_alt ?: ($media?->getCustomProperty('alt') ?: $post->title),
            'credit' => $this->text($draft?->hero_image_credit),
            'credit_html' => null,
            'source' => $this->text($draft?->hero_image_source),
        ];
    }

    /**
     * Titelbild aus dem Asset-Lauf. Die Varianten stehen absteigend nach
     * Breite (ImageOptimizer sortiert sie so), die erste ist damit die
     * 1200er-Fassung und zugleich das Bild fuer `og:image` und Schema.org.
     *
     * @return array{src: string, srcset: ?string, webp: ?string, width: int, height: int, alt: string, credit: ?string, credit_html: ?string, source: ?string}|null
     */
    private function heroImageFromDraft(?ArticleDraft $draft): ?array
    {
        if ($draft === null) {
            return null;
        }

        $variants = $this->heroVariants($draft);

        if ($variants === []) {
            return null;
        }

        $largest = $variants[0];
        $srcset = implode(', ', array_map(
            static fn (array $variant): string => $variant['url'].' '.$variant['width'].'w',
            $variants,
        ));

        $creditHtml = $this->text(Arr::get($this->assetsOf($draft), 'hero.credit_html'));

        return [
            'src' => $largest['url'],
            'srcset' => count($variants) > 1 ? $srcset : null,
            // Alle Varianten sind WebP; eine zweite <source> mit demselben
            // Inhalt waere nur Ballast.
            'webp' => null,
            'width' => $largest['width'],
            'height' => $largest['height'],
            'alt' => trim((string) $draft->hero_image_alt) ?: (string) $draft->title,
            'credit' => $this->text($draft->hero_image_credit),
            'credit_html' => $creditHtml,
            'source' => $this->text($draft->hero_image_source),
        ];
    }

    /**
     * Hero-Varianten des Entwurfs, absteigend nach Breite.
     *
     * @return list<array{url: string, width: int, height: int}>
     */
    public function heroVariants(?ArticleDraft $draft): array
    {
        $raw = Arr::get($this->assetsOf($draft), 'hero.variants');
        $variants = [];

        foreach (is_array($raw) ? $raw : [] as $variant) {
            if (! is_array($variant)) {
                continue;
            }

            $url = trim((string) ($variant['url'] ?? ''));
            $width = (int) ($variant['width'] ?? 0);

            if ($url === '' || $width <= 0) {
                continue;
            }

            $height = (int) ($variant['height'] ?? 0);

            $variants[] = [
                'url' => $url,
                'width' => $width,
                'height' => $height > 0 ? $height : (int) round($width * 9 / 16),
            ];
        }

        usort($variants, static fn (array $a, array $b): int => $b['width'] <=> $a['width']);

        return $variants;
    }

    /**
     * Groesste Hero-Variante als absolute URL — die Fassung fuer `og:image`,
     * `twitter:image` und das Article-Markup (#71).
     */
    public function heroImageUrl(?ArticleDraft $draft): ?string
    {
        return $this->heroVariants($draft)[0]['url'] ?? null;
    }

    /**
     * Infografik aus den Key-Facts (#16). Sie steht als eigenstaendiges Bild
     * im Artikel, nicht als Hintergrund: nur so ist sie mit einem eigenen
     * Alt-Text beschreibbar.
     *
     * @return array{url: string, alt: string}|null
     */
    public function infographic(?ArticleDraft $draft): ?array
    {
        if ($draft === null) {
            return null;
        }

        $url = $this->text(Arr::get($this->assetsOf($draft), 'infographic.url'));

        if ($url === null && $draft->infographic_svg_path !== null) {
            $url = Storage::disk((string) config('content.assets.disk', 'public'))
                ->url((string) $draft->infographic_svg_path);
        }

        if ($url === null || $url === '') {
            return null;
        }

        return [
            'url' => $url,
            'alt' => 'Infografik: '.trim((string) $draft->title),
        ];
    }

    /**
     * Asset-Bericht des Entwurfs (#16) als Array.
     *
     * @return array<string, mixed>
     */
    private function assetsOf(?ArticleDraft $draft): array
    {
        return (array) ($draft?->assets_json ?? []);
    }

    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param  mixed  $value
     * @return list<array{label: string, value: string}>
     */
    private function pairs($value): array
    {
        $pairs = [];

        foreach (is_array($value) ? $value : [] as $key => $entry) {
            if (is_array($entry)) {
                $label = (string) ($entry['label'] ?? (is_string($key) ? $key : ''));
                $entryValue = (string) ($entry['value'] ?? '');
            } else {
                $label = is_string($key) ? $key : '';
                $entryValue = (string) $entry;
            }

            if (trim($label) === '' || trim($entryValue) === '') {
                continue;
            }

            $pairs[] = ['label' => trim($label), 'value' => trim($entryValue)];
        }

        return array_slice($pairs, 0, 4);
    }
}
