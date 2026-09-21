<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Research\SectionFactMap;
use App\Guide\Support\OutlineAnchors;
use App\Models\Portal\Post;
use LogicException;
use RuntimeException;

/**
 * Fachlogik hinter WriteArticleJob und UpdateSectionsJob (#10). Ergebnis ist
 * immer eine neue guide_article_versions-Zeile; posts und
 * guide_article_details schreibt erst der Publisher (#12).
 *
 * create(): jeden Abschnitt der gesperrten Gliederung einzeln, danach
 *   Key-Facts (nur guide_facts), Kurzantwort, FAQ, Titel/Meta.
 * update(): nur die Abschnitte aus changed_section_ids_json; alle anderen
 *   Segmente uebernimmt der HtmlAssembler byte-identisch aus dem
 *   veroeffentlichten Artikel. Key-Facts, Kurzantwort, FAQ, Titel und Meta
 *   entstehen nur neu, wenn sie einen geaenderten Fakt nennen bzw. (Key-Facts)
 *   enthalten. Dazu ein Changelog-Eintrag, ein Satz je Aenderung.
 *
 * fix(): Fix-Durchlauf des Qualitaetsgates (#11) auf Basis der geprueften
 *   Fassung, nur fuer die Teile aus dem Fix-Plan.
 *
 * Die Gliederung wird nie veraendert; ohne Sperre wird nicht geschrieben.
 * Vor dem Speichern prueft HtmlAssembler::violations() Ueberschriften,
 * Tag-Whitelist und Fazit-Saetze; ein Verstoss bricht den Lauf ab.
 *
 * Laeuft im Tenant-Kontext.
 */
class ArticleWriter
{
    public function __construct(
        private readonly HtmlAssembler $html,
        private readonly SectionWriter $sections,
        private readonly KeyFactsBuilder $keyFacts,
        private readonly ShortAnswerWriter $shortAnswer,
        private readonly FaqWriter $faq,
        private readonly MetaWriter $meta,
        private readonly ChangelogWriter $changelog,
        private readonly InternalLinkResolver $links,
        private readonly FactMentions $mentions,
    ) {}

    public function create(Topic $topic, TopicRun $run): ArticleVersion
    {
        $context = $this->context($topic, $run);
        $entries = $context->entries();
        $targets = $this->links->forTopic($topic, $context->setting);
        $plan = $this->links->distribute($entries, $targets);
        $internalUrls = $this->links->urls($targets);

        /** @var ArticleDetail|null $detail */
        $detail = $topic->articleDetail;

        // Zuordnungen der Aenderungserkennung bleiben, Abschnitte werden neu erfasst.
        $previousMap = SectionFactMap::forDetail($detail);
        $map = new SectionFactMap([], [], $previousMap->assigned);
        $bodies = [];

        foreach ($entries as $entry) {
            $section = $this->sections->write($context, $entry, $plan[$entry['id']] ?? [], $internalUrls, $map);
            $bodies[$entry['id']] = $section['html'];
            $map = $map->withSection($entry['id'], $section['used_fact_keys']);
        }

        $body = $this->html->assemble($entries, $bodies);
        $this->assertValid($body, $entries, $targets);

        $keyFacts = $this->keyFacts->build($context, $map);
        $map = $map->withKeyFacts($this->keyFacts->keys($keyFacts));
        $shortAnswer = $this->shortAnswer->write($context);
        $faq = $this->faq->write($context);
        $meta = $this->meta->write($context);

        $this->recordWriting($run, [
            'mode' => 'create',
            'sections' => array_keys($bodies),
            'short_answer_fact_keys' => $shortAnswer['used_fact_keys'],
            'primary_keyword' => $meta['primary_keyword'],
            'internal_links' => $this->html->links($body, internal: true),
            'external_links' => $this->html->links($body, internal: false),
        ]);

        return $this->storeVersion($topic, $run, [
            'title' => $meta['title'],
            'meta_title' => $meta['title'],
            'meta_description' => $meta['meta_description'],
            'body_html' => $body,
            'short_answer' => $shortAnswer['short_answer'],
            'faq_json' => $faq,
            'key_facts_json' => $keyFacts,
            // Neufassung eines bestehenden Artikels: die Historie bleibt.
            'changelog_json' => (array) ($detail?->changelog_json ?? []),
            'section_fact_map_json' => $map->toArray(),
            'change_summary' => null,
        ]);
    }

    public function update(Topic $topic, TopicRun $run): ArticleVersion
    {
        /** @var Post|null $post */
        $post = $topic->article;

        if ($post === null) {
            throw new RuntimeException("Thema {$topic->getKey()} hat keinen veroeffentlichten Artikel; ein Update ist nicht moeglich.");
        }

        $context = $this->context($topic, $run);
        $entries = $context->entries();

        /** @var ArticleDetail|null $detail */
        $detail = $topic->articleDetail;
        $map = SectionFactMap::forDetail($detail);
        $existing = (string) $post->body;

        $changedFacts = array_values(array_filter((array) ($run->research_json['changed_facts'] ?? []), 'is_array'));
        $changedKeys = array_values(array_unique(array_map(fn (array $fact): string => (string) ($fact['key'] ?? ''), $changedFacts)));
        $requested = array_flip(array_map('strval', (array) ($run->changed_section_ids_json ?? [])));

        $targets = $this->links->forTopic($topic, $context->setting);
        $plan = $this->links->distribute($entries, $targets);
        $internalUrls = $this->links->urls($targets);

        // Abschnitte, die im Bestand fehlen (etwa migrierte Altartikel ohne
        // ids), werden neu geschrieben; die Gliederung bleibt, wie sie ist.
        $missing = array_flip($this->html->mismatchedSections($existing, $entries));
        $segments = $this->html->split($existing);
        $bodies = [];
        $edited = [];

        foreach ($entries as $entry) {
            $id = $entry['id'];

            if (isset($missing[$id])) {
                $section = $this->sections->write($context, $entry, $plan[$id] ?? [], $internalUrls, $map);
                $bodies[$id] = $section['html'];
                $map = $map->withSection($id, $section['used_fact_keys']);

                continue;
            }

            if (! isset($requested[$id])) {
                continue;
            }

            $section = $this->sections->update($context, $entry, $this->html->body($segments[$id]), $changedFacts, $internalUrls, $map);

            if (! $section['changed']) {
                continue;
            }

            $bodies[$id] = $section['html'];
            $edited[$id] = $section['edited_sentences'];
            $map = $map->withSection($id, $section['used_fact_keys'] !== [] ? $section['used_fact_keys'] : ($map->sections[$id] ?? []));
        }

        $body = $bodies === [] ? $existing : $this->html->replace($existing, $entries, $bodies);
        $this->assertValid($body, $entries);

        $keyFacts = $this->keyFacts->refresh((array) ($detail?->key_facts_json ?? []), $context, $changedKeys, $map);
        $map = $map->withKeyFacts($this->keyFacts->keys($keyFacts['rows']));

        $shortAnswer = (string) ($detail?->short_answer ?? '');
        $shortAnswerRewritten = trim($shortAnswer) === '' || $this->mentions->mentionsAny($shortAnswer, $changedFacts);

        if ($shortAnswerRewritten) {
            $shortAnswer = $this->shortAnswer->write($context)['short_answer'];
        }

        $faq = (array) ($detail?->faq_json ?? []);
        $faqRewritten = $faq === [] || $this->mentions->mentionsAny($this->faqText($faq), $changedFacts);

        if ($faqRewritten) {
            $faq = $this->faq->write($context);
        }

        $title = (string) $post->title;
        $metaTitle = (string) ($post->meta_title ?: $post->title);
        $metaDescription = (string) ($post->meta_description ?? '');
        $metaRewritten = $this->mentions->mentionsAny("{$title} {$metaTitle} {$metaDescription}", $changedFacts);

        if ($metaRewritten) {
            $meta = $this->meta->write($context);
            $title = $meta['title'];
            $metaTitle = $meta['title'];
            $metaDescription = $meta['meta_description'];
        }

        $changedSectionIds = array_values(array_filter(
            array_map(fn (array $entry): string => $entry['id'], $entries),
            fn (string $id): bool => isset($bodies[$id]),
        ));

        $changelog = (array) ($detail?->changelog_json ?? []);
        $entry = null;

        if ($changedFacts !== [] || $changedSectionIds !== []) {
            $entry = $this->changelog->write(
                $context,
                $changedFacts,
                $changedSectionIds,
                array_intersect_key($bodies, array_flip($changedSectionIds)),
            );
            array_unshift($changelog, $entry);
        }

        $this->recordWriting($run, [
            'mode' => 'update',
            'requested_section_ids' => array_keys($requested),
            'changed_section_ids' => $changedSectionIds,
            'rewritten_missing_section_ids' => array_keys($missing),
            'edited_sentences' => $edited,
            'key_facts_updated' => $keyFacts['affected'],
            'short_answer_updated' => $shortAnswerRewritten,
            'faq_updated' => $faqRewritten,
            'meta_updated' => $metaRewritten,
        ]);

        if ($entry !== null) {
            $run->forceFill(['change_summary' => $entry['summary']])->save();
        }

        return $this->storeVersion($topic, $run, [
            'title' => $title,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'body_html' => $body,
            'short_answer' => $shortAnswer,
            'faq_json' => $faq,
            'key_facts_json' => $keyFacts['rows'],
            'changelog_json' => $changelog,
            'section_fact_map_json' => $map->toArray(),
            'change_summary' => $entry['summary'] ?? null,
        ]);
    }

    /**
     * Fix-Durchlauf des Qualitaetsgates (#11): eine neue Fassung auf Basis der
     * geprueften, in der nur die im Plan genannten Teile nachgebessert sind.
     * Alle anderen Abschnitte bleiben byte-identisch (HtmlAssembler::replace()).
     *
     * Plan-Schluessel: Abschnitts-ids, short_answer, faq, meta, key_facts und
     * artikel (Anweisungen ohne Abschnitt; sie gehen an jeden nachgebesserten
     * Abschnitt mit). Kurzantwort, FAQ, Meta und Key-Facts entstehen neu aus
     * dem Fakten-Set. Externe Links nur auf Quellen ohne broken_at.
     *
     * @param  array<string, array<int, string>>  $plan
     */
    public function fix(Topic $topic, TopicRun $run, ArticleVersion $base, array $plan): ArticleVersion
    {
        $context = $this->context($topic, $run);
        $entries = $context->entries();
        $existing = (string) $base->body_html;
        $map = SectionFactMap::fromArray($base->section_fact_map_json);

        $targets = $this->links->forTopic($topic, $context->setting);
        $linkPlan = $this->links->distribute($entries, $targets);
        $broken = $topic->sources()->whereNotNull('broken_at')->pluck('url')->map(fn (mixed $url): string => (string) $url)->all();
        $internalUrls = array_values(array_unique([...$this->links->urls($targets), ...$this->html->links($existing, internal: true)]));
        $externalUrls = array_values(array_diff($context->externalUrls, $broken));

        $general = array_values((array) ($plan['artikel'] ?? []));
        $segments = $this->html->split($existing);
        $bodies = [];

        foreach ($entries as $entry) {
            $id = $entry['id'];

            if (! isset($plan[$id]) || ! isset($segments[$id])) {
                continue;
            }

            $section = $this->sections->fix(
                $context,
                $entry,
                $this->html->body($segments[$id]),
                [...array_values($plan[$id]), ...$general],
                $linkPlan[$id] ?? [],
                $internalUrls,
                $externalUrls,
                $map,
            );
            $bodies[$id] = $section['html'];
            $map = $map->withSection($id, $section['used_fact_keys'] !== [] ? $section['used_fact_keys'] : ($map->sections[$id] ?? []));
        }

        $body = $bodies === [] ? $existing : $this->html->replace($existing, $entries, $bodies);
        $this->assertValid($body, $entries);

        $keyFacts = (array) ($base->key_facts_json ?? []);

        if (isset($plan['key_facts'])) {
            $keyFacts = $this->keyFacts->build($context, $map);
            $map = $map->withKeyFacts($this->keyFacts->keys($keyFacts));
        }

        $shortAnswer = isset($plan['short_answer']) ? $this->shortAnswer->write($context)['short_answer'] : (string) $base->short_answer;
        $faq = isset($plan['faq']) ? $this->faq->write($context) : (array) ($base->faq_json ?? []);
        $title = (string) $base->title;
        $metaTitle = (string) ($base->meta_title ?: $base->title);
        $metaDescription = (string) $base->meta_description;

        if (isset($plan['meta'])) {
            $meta = $this->meta->write($context);
            $title = $meta['title'];
            $metaTitle = $meta['title'];
            $metaDescription = $meta['meta_description'];
        }

        $parts = array_values(array_intersect(['short_answer', 'faq', 'meta', 'key_facts'], array_keys($plan)));

        $run->forceFill([
            'research_json' => [...(array) ($run->research_json ?? []), 'quality_fix' => [
                'base_version_id' => (int) $base->getKey(),
                'section_ids' => array_keys($bodies),
                'parts' => $parts,
            ]],
        ])->save();

        return $this->storeVersion($topic, $run, [
            'title' => $title,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'body_html' => $body,
            'short_answer' => $shortAnswer,
            'faq_json' => $faq,
            'key_facts_json' => $keyFacts,
            'changelog_json' => (array) ($base->changelog_json ?? []),
            'section_fact_map_json' => $map->toArray(),
            'change_summary' => $base->change_summary,
        ]);
    }

    /**
     * "Mit Hinweis neu schreiben" aus der Pruef-Queue (#16): die zuletzt
     * gepruefte Fassung des Laufs wird mit dem Hinweis der Redaktion
     * nachgebessert — ueber denselben Weg wie der Fix-Durchlauf (fix()), damit
     * alle nicht betroffenen Abschnitte byte-identisch bleiben.
     *
     * Betroffen sind die Abschnitte, die dieser Lauf geschrieben hat
     * (research_json.writing), bei einer Neuanlage also alle. Der Hinweis
     * geht an jeden dieser Abschnitte.
     */
    public function revise(Topic $topic, TopicRun $run, string $instructions): ArticleVersion
    {
        /** @var ArticleVersion|null $base */
        $base = $run->versions()->orderByDesc('id')->first();

        if ($base === null) {
            throw new RuntimeException("Lauf {$run->getKey()} hat keine Fassung, die nachgebessert werden koennte.");
        }

        $writing = (array) ($run->research_json['writing'] ?? []);
        $sectionIds = ($writing['mode'] ?? null) === 'create'
            ? array_map(fn (array $entry): string => $entry['id'], OutlineAnchors::flatten($topic->outline_json))
            : array_values(array_unique(array_map('strval', [
                ...(array) ($writing['changed_section_ids'] ?? []),
                ...(array) ($run->research_json['quality_fix']['section_ids'] ?? []),
            ])));

        if ($sectionIds === []) {
            $sectionIds = array_map(fn (array $entry): string => $entry['id'], OutlineAnchors::flatten($topic->outline_json));
        }

        $plan = array_fill_keys($sectionIds, []);
        $plan['artikel'] = [trim($instructions)];

        $version = $this->fix($topic, $run, $base, $plan);

        $run->forceFill([
            'research_json' => [...(array) ($run->research_json ?? []), 'review_rewrite' => [
                'base_version_id' => (int) $base->getKey(),
                'version_id' => (int) $version->getKey(),
                'section_ids' => $sectionIds,
                'instructions' => trim($instructions),
            ]],
        ])->save();

        return $version;
    }

    private function context(Topic $topic, TopicRun $run): WritingContext
    {
        if (! $topic->isOutlineLocked() || ($topic->outline_json ?? []) === []) {
            throw new LogicException("Thema {$topic->getKey()} hat keine gesperrte Gliederung; es wird nicht geschrieben.");
        }

        $context = WritingContext::for($topic, $run);

        if ($context->facts->isEmpty()) {
            throw new RuntimeException("Thema {$topic->getKey()} hat kein Fakten-Set; ohne Fakten wird nicht geschrieben.");
        }

        return $context;
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $entries
     * @param  array{portal: list<array{type: string, url: string, anchor: string}>, related: list<array{type: string, url: string, anchor: string}>}|null  $targets
     */
    private function assertValid(string $body, array $entries, ?array $targets = null): void
    {
        $errors = $this->html->violations($body, $entries);

        if ($targets !== null) {
            $portalUrls = array_map(fn (array $target): string => $target['url'], $targets['portal']);
            $present = count(array_intersect($portalUrls, $this->html->links($body, internal: true)));
            $required = min(count($portalUrls), max(0, (int) config('guide.writing.links.min_portal', 2)));

            if ($present < $required) {
                $errors[] = "Nur {$present} von {$required} Pflichtlinks auf Portalseiten im Artikel.";
            }
        }

        if ($errors !== []) {
            throw new RuntimeException('Artikel verletzt die Schreibregeln: '.implode(' ', $errors));
        }
    }

    /**
     * @param  array<int, mixed>  $faq
     */
    private function faqText(array $faq): string
    {
        return implode(' ', array_map(
            fn (mixed $item): string => is_array($item) ? ((string) ($item['question'] ?? '')).' '.((string) ($item['answer'] ?? '')) : '',
            $faq,
        ));
    }

    /**
     * Protokoll des Schreibschritts fuer Qualitaetsgate (#11) und Dashboard;
     * steht neben dem Recherche-Protokoll in research_json.writing.
     *
     * @param  array<string, mixed>  $data
     */
    private function recordWriting(TopicRun $run, array $data): void
    {
        $run->forceFill([
            'research_json' => [...(array) ($run->research_json ?? []), 'writing' => $data],
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function storeVersion(Topic $topic, TopicRun $run, array $attributes): ArticleVersion
    {
        $version = (int) ArticleVersion::query()
            ->where(fn ($query) => $query
                ->where('guide_topic_id', $topic->getKey())
                ->when($topic->article_id !== null, fn ($inner) => $inner->orWhere('article_id', $topic->article_id)))
            ->max('version');

        /** @var ArticleVersion */
        return ArticleVersion::query()->create([
            ...$attributes,
            'article_id' => $topic->article_id,
            'guide_topic_id' => $topic->getKey(),
            'guide_topic_run_id' => $run->getKey(),
            'version' => $version + 1,
        ]);
    }
}
