<?php

declare(strict_types=1);

namespace App\Content\Quality;

use App\Content\Models\ArticleDraft;
use App\Content\Support\EvidenceMarks;
use App\Content\Support\TextFold;
use App\Content\Support\UmlautSpelling;
use Illuminate\Support\Str;

/**
 * Stufe 1 des Qualitaetsgates: deterministischer SEO-Lint ohne Modellaufruf
 * (#15).
 *
 * Reine PHP-Pruefung. Das Regelwerk steht in config/content_seo_rules.php und
 * ist dort einzeln abschaltbar, gewichtbar und als blockierend markierbar —
 * eine Regel zu aendern ist damit eine Konfigurations-, keine Codeaenderung.
 *
 * Der Linter kennt keine externen Dienste. Ergebnisse, die einen Netzaufruf
 * oder eine Rechnung brauchen (Linkpruefung, Lesbarkeit), kommen fertig als
 * Eingabe herein; so bleibt die Klasse ohne Seiteneffekte und testbar.
 *
 * Statuswerte je Regel: 'ok', 'warn', 'fail', 'skipped'. In die Teilnote geht
 * 'ok' voll, 'warn' zur Haelfte, 'fail' gar nicht ein; 'skipped' zaehlt nicht
 * mit.
 */
final class SeoLinter
{
    /**
     * Beschriftungen fuer die Pruefflaeche (#20).
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'title_length' => 'Titellänge',
        'meta_description_length' => 'Länge der Meta-Description',
        'single_h1' => 'Genau eine H1',
        'h2_count' => 'Anzahl H2-Abschnitte',
        'keyword_in_title' => 'Keyword im Titel',
        'keyword_in_h1' => 'Keyword in der H1',
        'keyword_in_short_answer' => 'Keyword in der Kurzantwort',
        'keyword_in_first_paragraph' => 'Keyword im ersten Absatz',
        'word_count' => 'Wortzahl',
        'internal_links' => 'Interne Verlinkung',
        'faq_count' => 'Anzahl FAQ-Einträge',
        'image_alt' => 'Alt-Texte der Bilder',
        'external_links_reachable' => 'Externe Links erreichbar',
        'readability' => 'Lesbarkeit (Flesch)',
        'umlaut_spelling' => 'Umlaute',
        'doorway' => 'Regionalbezug im Fließtext',
        'ymyl_disclaimer' => 'Pflichthinweis (YMYL)',
    ];

    /**
     * Deutsche Namen der Suchintention. Verbindlich fuer jede Flaeche des
     * Panels (§4.2) — in der Pruefflaeche steht nie ein Codewort.
     */
    private const INTENT_LABELS = [
        'informational' => 'informierend',
        'commercial' => 'vergleichend',
        'transactional' => 'abschlussnah',
        'navigational' => 'navigierend',
    ];

    /**
     * Huelle aller Zielkorridore. Sie greift nur, wenn die Eingabe keinen
     * Korridor mitbringt (Nachbewertung ohne Themenkandidat).
     */
    private const FALLBACK_CORRIDOR = ['min' => 900, 'max' => 1800];

    /**
     * @param  array{primary_keyword?: string, region_scope?: string, region_name?: ?string, is_ymyl?: bool, link_results?: array<int, array<string, mixed>>, readability?: array<string, mixed>, has_regional_fact?: bool, intent?: ?string, target_words?: array{min: int, max: int}}  $input
     * @return array{rules: array<int, array<string, mixed>>, blocking: array<int, string>, score: float}
     */
    public function lint(ArticleDraft $draft, array $input = []): array
    {
        $body = (string) $draft->body_html;
        $text = $this->plainText($body);
        $keyword = trim((string) ($input['primary_keyword'] ?? ''));

        $rows = [];

        foreach ((array) config('content_seo_rules.rules', []) as $key => $rule) {
            $rule = (array) $rule;

            if (! (bool) ($rule['enabled'] ?? true)) {
                continue;
            }

            $result = $this->evaluate((string) $key, $rule, $draft, $body, $text, $keyword, $input);

            if ($result === null) {
                continue;
            }

            $rows[] = [
                'check' => (string) $key,
                'label' => __(self::LABELS[$key] ?? (string) $key),
                'status' => $result['status'],
                'message' => $result['message'],
                'blocking' => $result['status'] === 'fail' && (bool) ($rule['blocking'] ?? false),
                'weight' => (float) ($rule['weight'] ?? 1.0),
            ];
        }

        return [
            'rules' => $rows,
            'blocking' => $this->blocking($rows),
            'score' => $this->score($rows),
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $input
     * @return array{status: string, message: ?string}|null
     */
    private function evaluate(
        string $key,
        array $rule,
        ArticleDraft $draft,
        string $body,
        string $text,
        string $keyword,
        array $input,
    ): ?array {
        return match ($key) {
            'title_length' => $this->length(
                (string) ($draft->meta_title ?: $draft->title),
                (int) ($rule['min'] ?? 30),
                (int) ($rule['max'] ?? config('content.generation.title_max_chars', 60)),
                __('Zeichen im Titel'),
            ),
            'meta_description_length' => $this->length(
                (string) $draft->meta_description,
                (int) ($rule['min'] ?? 110),
                (int) ($rule['max'] ?? config('content.generation.meta_description_max_chars', 155)),
                __('Zeichen in der Meta-Description'),
            ),
            'single_h1' => $this->singleH1($draft, $body),
            'h2_count' => $this->h2Count($body, (int) ($rule['min'] ?? 3), (int) ($rule['max'] ?? 10)),
            'keyword_in_title' => $this->contains($keyword, (string) ($draft->meta_title ?: $draft->title), __('Titel')),
            'keyword_in_h1' => $this->contains($keyword, (string) $draft->title, __('H1')),
            'keyword_in_short_answer' => $this->contains($keyword, (string) $draft->short_answer, __('Kurzantwort')),
            'keyword_in_first_paragraph' => $this->contains($keyword, $this->firstParagraph($body), __('ersten Absatz')),
            'word_count' => $this->wordCount($text, $rule, $input),
            'internal_links' => $this->internalLinks(
                $body,
                (int) ($rule['min'] ?? config('content.generation.internal_links.min_portal', 2)),
                (int) ($rule['max_articles'] ?? config('content.generation.internal_links.max_articles', 3)),
            ),
            'faq_count' => $this->faqCount($draft, (int) ($rule['min'] ?? 3), (int) ($rule['max'] ?? 6)),
            'image_alt' => $this->imageAlt($body),
            'external_links_reachable' => $this->externalLinks((array) ($input['link_results'] ?? [])),
            'readability' => $this->readability((array) ($input['readability'] ?? [])),
            'umlaut_spelling' => $this->umlautSpelling($draft, $body, (int) ($rule['max_body_suspects'] ?? 2)),
            'doorway' => $this->doorway($text, $input),
            'ymyl_disclaimer' => $this->ymylDisclaimer($text, (bool) ($input['is_ymyl'] ?? false)),
            default => null,
        };
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function length(string $value, int $min, int $max, string $subject): array
    {
        $length = mb_strlen(trim($value));

        if ($length === 0) {
            return ['status' => 'fail', 'message' => __('Keine Angabe vorhanden.')];
        }

        if ($length < $min || $length > $max) {
            return ['status' => 'warn', 'message' => __(':count :subject (Korridor :min bis :max).', [
                'count' => $length,
                'subject' => $subject,
                'min' => $min,
                'max' => $max,
            ])];
        }

        return ['status' => 'ok', 'message' => __(':count :subject.', ['count' => $length, 'subject' => $subject])];
    }

    /**
     * Die Seite setzt den Artikeltitel als H1 (#17). Eine H1 im Fliesstext
     * waere die zweite.
     *
     * @return array{status: string, message: ?string}
     */
    private function singleH1(ArticleDraft $draft, string $body): array
    {
        if (trim((string) $draft->title) === '') {
            return ['status' => 'fail', 'message' => __('Der Artikel hat keinen Titel und damit keine H1.')];
        }

        $count = preg_match_all('/<h1[\s>]/i', $body);

        if ($count > 0) {
            return ['status' => 'fail', 'message' => __('Der Fließtext enthält :count zusätzliche H1.', ['count' => $count])];
        }

        return ['status' => 'ok', 'message' => __('Genau eine H1 (Artikeltitel).')];
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function h2Count(string $body, int $min, int $max): array
    {
        $count = (int) preg_match_all('/<h2[\s>]/i', $body);

        if ($count < $min) {
            return ['status' => 'fail', 'message' => __('Nur :count H2-Abschnitte, mindestens :min erwartet.', ['count' => $count, 'min' => $min])];
        }

        if ($count > $max) {
            return ['status' => 'warn', 'message' => __(':count H2-Abschnitte, höchstens :max vorgesehen.', ['count' => $count, 'max' => $max])];
        }

        return ['status' => 'ok', 'message' => __(':count H2-Abschnitte.', ['count' => $count])];
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function contains(string $keyword, string $haystack, string $subject): array
    {
        if ($keyword === '') {
            return ['status' => 'skipped', 'message' => __('Kein Haupt-Keyword hinterlegt.')];
        }

        if (trim($haystack) === '') {
            return ['status' => 'fail', 'message' => __('Kein :subject vorhanden.', ['subject' => $subject])];
        }

        return $this->matches($keyword, $haystack)
            ? ['status' => 'ok', 'message' => __('Keyword steht im :subject.', ['subject' => $subject])]
            : ['status' => 'fail', 'message' => __('„:keyword" fehlt im :subject.', ['keyword' => $keyword, 'subject' => $subject])];
    }

    /**
     * Laenge gegen den Zielkorridor der Suchintention (§4.2). Drei
     * Schweregrade: 'ok' innerhalb Korridor plus Toleranz, 'warn' ausserhalb
     * der Toleranz aber innerhalb der Reissleine, 'fail' ausserhalb der
     * Reissleine — nur dort blockiert die Regel.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $input
     * @return array{status: string, message: ?string}
     */
    private function wordCount(string $text, array $rule, array $input): array
    {
        $count = $text === '' ? 0 : count(preg_split('/\s+/u', $text) ?: []);

        $hardMin = (int) config('content.quality.hard_word_limits.min', 600);
        $hardMax = (int) config('content.quality.hard_word_limits.max', 3000);

        if ($count < $hardMin) {
            return ['status' => 'fail', 'message' => __(':count Wörter. Unter der harten Untergrenze von :min Wörtern — der Lauf ist abgebrochen, nicht kurz.', [
                'count' => $this->number($count),
                'min' => $this->number($hardMin),
            ])];
        }

        if ($count > $hardMax) {
            return ['status' => 'fail', 'message' => __(':count Wörter. Über der harten Obergrenze von :max Wörtern.', [
                'count' => $this->number($count),
                'max' => $this->number($hardMax),
            ])];
        }

        $corridor = $this->wordCorridor($rule, $input);
        $tolerance = (array) config('content.quality.word_tolerance', []);
        $under = (float) ($tolerance['under'] ?? 0.10);
        $over = (float) ($tolerance['over'] ?? 0.25);

        $tolMin = (int) round($corridor['min'] * (1.0 - $under));
        $tolMax = (int) round($corridor['max'] * (1.0 + $over));

        // Der Korridor steht im Text immer als Ziel, nicht als Toleranzband:
        // die Toleranz ist eine Nachsicht des Gates, kein Auftrag an den Texter.
        $suffix = $corridor['intent'] !== null
            ? __(':min–:max (:intent)', [
                'min' => $this->number($corridor['min']),
                'max' => $this->number($corridor['max']),
                'intent' => $corridor['intent'],
            ])
            : __(':min–:max', [
                'min' => $this->number($corridor['min']),
                'max' => $this->number($corridor['max']),
            ]);

        if ($count < $tolMin) {
            return ['status' => 'warn', 'message' => __(':count Wörter, :diff unter dem Zielkorridor :corridor.', [
                'count' => $this->number($count),
                'diff' => $this->number($tolMin - $count),
                'corridor' => $suffix,
            ])];
        }

        if ($count > $tolMax) {
            return ['status' => 'warn', 'message' => __(':count Wörter, :diff über dem Zielkorridor :corridor.', [
                'count' => $this->number($count),
                'diff' => $this->number($count - $tolMax),
                'corridor' => $suffix,
            ])];
        }

        return ['status' => 'ok', 'message' => __(':count Wörter, Zielkorridor :corridor.', [
            'count' => $this->number($count),
            'corridor' => $suffix,
        ])];
    }

    /**
     * Zielkorridor der Regel. Ausdrueckliche min/max in der Regelkonfiguration
     * schlagen die Eingabe — das bleibt der Notgriff fuer den Betrieb. Fehlt
     * der Korridor ganz, wird gegen die Huelle gemessen und die Intention im
     * Text weggelassen; uebersprungen wird nie.
     *
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $input
     * @return array{min: int, max: int, intent: ?string}
     */
    private function wordCorridor(array $rule, array $input): array
    {
        $target = (array) ($input['target_words'] ?? []);
        $targetMin = isset($target['min']) ? (int) $target['min'] : null;
        $targetMax = isset($target['max']) ? (int) $target['max'] : null;

        $hasTarget = $targetMin !== null && $targetMax !== null;

        $intentKey = trim((string) ($input['intent'] ?? ''));
        $intent = $hasTarget && $intentKey !== '' && isset(self::INTENT_LABELS[$intentKey])
            ? __(self::INTENT_LABELS[$intentKey])
            : null;

        $ruleMin = $rule['min'] ?? null;
        $ruleMax = $rule['max'] ?? null;

        $min = $ruleMin !== null && $ruleMin !== ''
            ? (int) $ruleMin
            : ($targetMin ?? self::FALLBACK_CORRIDOR['min']);

        $max = $ruleMax !== null && $ruleMax !== ''
            ? (int) $ruleMax
            : ($targetMax ?? self::FALLBACK_CORRIDOR['max']);

        return ['min' => $min, 'max' => max($min, $max), 'intent' => $intent];
    }

    /**
     * Zahl mit Tausenderpunkt. Der Pruefer liest Wortzahlen, keine Rohwerte.
     */
    private function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function internalLinks(string $body, int $min, int $maxArticles): array
    {
        preg_match_all('/<a\s[^>]*href="(\/[^"]*)"/i', $body, $matches);

        $urls = array_values(array_unique($matches[1] ?? []));
        $prefix = (string) config('content.sources.gsc_gap.article_path_prefix', '/ratgeber/');
        $articles = array_filter($urls, static fn (string $url): bool => str_starts_with($url, $prefix));

        if (count($urls) < $min) {
            return ['status' => 'fail', 'message' => __('Nur :count interne Links, mindestens :min erwartet.', [
                'count' => count($urls),
                'min' => $min,
            ])];
        }

        if (count($articles) > $maxArticles) {
            return ['status' => 'warn', 'message' => __(':count Verweise auf andere Ratgeber, höchstens :max vorgesehen.', [
                'count' => count($articles),
                'max' => $maxArticles,
            ])];
        }

        return ['status' => 'ok', 'message' => __(':count interne Links, davon :articles auf Ratgeber.', [
            'count' => count($urls),
            'articles' => count($articles),
        ])];
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function faqCount(ArticleDraft $draft, int $min, int $max): array
    {
        $count = count((array) ($draft->faq_json ?? []));

        if ($count < $min) {
            return ['status' => 'fail', 'message' => __('Nur :count FAQ-Einträge, mindestens :min erwartet.', ['count' => $count, 'min' => $min])];
        }

        if ($count > $max) {
            return ['status' => 'warn', 'message' => __(':count FAQ-Einträge, höchstens :max vorgesehen.', ['count' => $count, 'max' => $max])];
        }

        return ['status' => 'ok', 'message' => __(':count FAQ-Einträge.', ['count' => $count])];
    }

    /**
     * Bilder liefert #16; bis dahin hat der Fliesstext keine, und die Regel
     * meldet 'uebersprungen' statt einen Fehler zu erfinden.
     *
     * @return array{status: string, message: ?string}
     */
    private function imageAlt(string $body): array
    {
        $total = (int) preg_match_all('/<img\s[^>]*>/i', $body, $matches);

        if ($total === 0) {
            return ['status' => 'skipped', 'message' => __('Der Fließtext enthält keine Bilder.')];
        }

        $without = 0;

        foreach ($matches[0] as $tag) {
            if (preg_match('/\salt="[^"]+"/i', (string) $tag) !== 1) {
                $without++;
            }
        }

        return $without === 0
            ? ['status' => 'ok', 'message' => __('Alle :count Bilder haben einen Alt-Text.', ['count' => $total])]
            : ['status' => 'fail', 'message' => __(':count von :total Bildern ohne Alt-Text.', ['count' => $without, 'total' => $total])];
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     * @return array{status: string, message: ?string}
     */
    private function externalLinks(array $results): array
    {
        if ($results === []) {
            return ['status' => 'skipped', 'message' => __('Keine externen Links im Artikel.')];
        }

        $broken = array_values(array_filter($results, static fn (array $row): bool => ($row['status'] ?? '') === 'broken'));
        $unreachable = array_values(array_filter($results, static fn (array $row): bool => ($row['status'] ?? '') === 'unreachable'));

        if ($broken !== []) {
            return ['status' => 'fail', 'message' => __(':count externe Links antworten mit einem Fehlercode (:urls).', [
                'count' => count($broken),
                'urls' => implode(', ', array_map(
                    static fn (array $row): string => (string) $row['url'].' → '.(string) ($row['code'] ?? '?'),
                    array_slice($broken, 0, 3),
                )),
            ])];
        }

        if ($unreachable !== []) {
            return ['status' => 'warn', 'message' => __(':count externe Links waren nicht erreichbar (Zeitüberschreitung).', [
                'count' => count($unreachable),
            ])];
        }

        return ['status' => 'ok', 'message' => __('Alle :count externen Links antworten.', ['count' => count($results)])];
    }

    /**
     * Umschriebene Umlaute ("Naehe", "fuer"), die UmlautSpelling nicht
     * korrigieren konnte (#41).
     *
     * @return array{status: string, message: ?string}
     */
    private function umlautSpelling(ArticleDraft $draft, string $body, int $maxBodySuspects): array
    {
        $visible = implode(' ', array_filter([
            (string) $draft->title,
            (string) $draft->meta_title,
            (string) $draft->meta_description,
            (string) $draft->short_answer,
        ]));

        $prominent = UmlautSpelling::suspects($visible);
        $faq = implode(' ', array_map(
            static fn ($entry): string => is_array($entry) ? implode(' ', array_filter($entry, 'is_string')) : '',
            (array) ($draft->faq_json ?? []),
        ));
        $inText = array_values(array_diff(UmlautSpelling::suspects($body.' '.$faq), $prominent));

        if ($prominent !== []) {
            return ['status' => 'fail', 'message' => __('Umschriebene Umlaute in Titel, Meta-Angaben oder Kurzantwort: :words.', [
                'words' => implode(', ', array_slice($prominent, 0, 8)),
            ])];
        }

        if ($inText === []) {
            return ['status' => 'ok', 'message' => __('Keine umschriebenen Umlaute gefunden.')];
        }

        return [
            'status' => count($inText) > $maxBodySuspects ? 'fail' : 'warn',
            'message' => __(':count Wörter mit umschriebenem Umlaut im Text: :words.', [
                'count' => count($inText),
                'words' => implode(', ', array_slice($inText, 0, 8)),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $readability
     * @return array{status: string, message: ?string}
     */
    private function readability(array $readability): array
    {
        if ($readability === []) {
            return ['status' => 'skipped', 'message' => null];
        }

        $score = (float) ($readability['score'] ?? 0);
        $target = (int) ($readability['target'] ?? 50);

        return $score >= $target
            ? ['status' => 'ok', 'message' => __('Flesch :score (:level).', ['score' => $score, 'level' => $readability['level'] ?? ''])]
            : ['status' => 'warn', 'message' => __('Flesch :score, Ziel mindestens :target (:level).', [
                'score' => $score,
                'target' => $target,
                'level' => $readability['level'] ?? '',
            ])];
    }

    /**
     * Doorway-Test: ein Regionalartikel muss die Region im Fliesstext tragen,
     * nicht nur in Titel und Meta, und braucht einen regionalen Fakt.
     *
     * @param  array<string, mixed>  $input
     * @return array{status: string, message: ?string}
     */
    private function doorway(string $text, array $input): array
    {
        $scope = (string) ($input['region_scope'] ?? 'national');
        $region = trim((string) ($input['region_name'] ?? ''));

        if ($scope === 'national' || $region === '') {
            return ['status' => 'skipped', 'message' => __('Bundesweites Thema ohne Regionalbezug.')];
        }

        $required = max(1, (int) config('content_seo_rules.doorway.min_mentions', 3));

        // Dichte im Fliesstext ueber dieselbe Faltung wie die Keyword-Regeln
        // (#95): "München" zaehlt auch, wo der Text "Muenchen" schreibt.
        $mentions = max(
            substr_count(TextFold::fold($text), TextFold::fold($region)),
            substr_count(TextFold::fold($text, expandUmlauts: false), TextFold::fold($region, expandUmlauts: false)),
        );

        if ($mentions < $required) {
            return ['status' => 'fail', 'message' => __('„:region" steht nur :count Mal im Fließtext, mindestens :min erwartet.', [
                'region' => $region,
                'count' => $mentions,
                'min' => $required,
            ])];
        }

        if ((bool) config('content_seo_rules.doorway.require_regional_fact', true)
            && ! (bool) ($input['has_regional_fact'] ?? false)) {
            return ['status' => 'fail', 'message' => __('Kein regionaler Fakt zu :region im Artikel.', ['region' => $region])];
        }

        return ['status' => 'ok', 'message' => __('„:region" steht :count Mal im Fließtext, mit regionalem Fakt.', [
            'region' => $region,
            'count' => $mentions,
        ])];
    }

    /**
     * @return array{status: string, message: ?string}
     */
    private function ymylDisclaimer(string $text, bool $isYmyl): array
    {
        if (! $isYmyl) {
            return ['status' => 'skipped', 'message' => __('Kein YMYL-Mandant.')];
        }

        foreach ((array) config('content_seo_rules.ymyl.disclaimer_markers', []) as $marker) {
            if (is_string($marker) && $marker !== '' && Str::contains($text, $marker, ignoreCase: true)) {
                return ['status' => 'ok', 'message' => __('Pflichthinweis gefunden („:marker").', ['marker' => $marker])];
            }
        }

        return ['status' => 'fail', 'message' => __('Der Pflichthinweis aus dem Styleguide fehlt im Artikel.')];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private function blocking(array $rows): array
    {
        $issues = [];

        foreach ($rows as $row) {
            if (! ($row['blocking'] ?? false)) {
                continue;
            }

            $issues[] = trim((string) $row['label'].': '.(string) ($row['message'] ?? ''));
        }

        return $issues;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function score(array $rows): float
    {
        $total = 0.0;
        $reached = 0.0;

        foreach ($rows as $row) {
            if ($row['status'] === 'skipped') {
                continue;
            }

            $weight = (float) $row['weight'];
            $total += $weight;
            $reached += match ($row['status']) {
                'ok' => $weight,
                'warn' => $weight / 2,
                default => 0.0,
            };
        }

        return $total <= 0.0 ? 100.0 : round(($reached / $total) * 100, 1);
    }

    /**
     * Keyword-Treffer unabhaengig von Gross-/Kleinschreibung, Wortstellung
     * und Beugung: "Waermepumpe Kosten" trifft auch "Kosten einer
     * Waermepumpe" und "was eine Waermepumpe kostet". Dafuer wird von
     * laengeren Woertern die Endung abgeschnitten — eine Stemming-Bibliothek
     * waere fuer eine Ja/Nein-Pruefung unverhaeltnismaessig.
     */
    private function matches(string $keyword, string $haystack): bool
    {
        $expandedHaystack = TextFold::fold($haystack);
        $plainHaystack = TextFold::fold($haystack, expandUmlauts: false);

        // Beide Faltungen des Keywords, Wort fuer Wort: 'ö' => 'oe' fuer die
        // Umschrift der Themenkandidaten, 'ö' => 'o' fuer Texte, die die
        // Punkte einfach weglassen. Die Trennzeichen sind in beiden gleich,
        // die Wortlisten damit deckungsgleich.
        $expandedParts = preg_split('/\s+/u', TextFold::fold($keyword)) ?: [];
        $plainParts = preg_split('/\s+/u', TextFold::fold($keyword, expandUmlauts: false)) ?: [];

        foreach ($expandedParts as $index => $part) {
            if (mb_strlen($part) < 3) {
                continue;
            }

            $expanded = $this->stem($part);
            $plain = $this->stem((string) ($plainParts[$index] ?? $part));

            // Paarweise verglichen: "Förderung" trifft damit sowohl
            // "foerderung" als auch "forderung", ohne dass Umschrift und
            // verkuerzte Schreibweise sich vermischen.
            if (! str_contains($expandedHaystack, $expanded) && ! str_contains($plainHaystack, $plain)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Endung abschneiden, sobald das Wort lang genug ist. Kurze Woerter
     * bleiben ganz, sonst treffen ihre Reste ueberall.
     */
    private function stem(string $part): string
    {
        return mb_strlen($part) >= 6 ? mb_substr($part, 0, mb_strlen($part) - 2) : $part;
    }

    private function firstParagraph(string $body): string
    {
        if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $body, $matches) === 1) {
            return $this->plainText((string) $matches[1]);
        }

        return $this->plainText($body);
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<\/(p|li|h2|h3|td|th|blockquote)>/i', ' ', $html) ?? $html;

        // Belegmarken sind kein Lesetext und zaehlen weder als Wort noch als
        // Keyword-Umfeld (#76).
        $html = EvidenceMarks::strip($html);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html))) ?? '');
    }
}
