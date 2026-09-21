<?php

declare(strict_types=1);

namespace App\Guide\Research;

use App\Guide\Dto\ChangeSet;
use App\Guide\Enums\RunMode;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Models\ArticleDetail;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Models\Fact;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Support\TenantPromptVars;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Aenderungserkennung nach der Tiefenrecherche (#9).
 *
 * Vergleicht das Fakten-Set vor und nach der Recherche auf normalisierten
 * Werten (FactNormalizer), erzeugt changed_facts[] und ordnet sie ueber die
 * SectionFactMap den Abschnitten der gesperrten Gliederung zu. Daraus folgt
 * der RunMode:
 *
 *   create     Thema ohne Artikel oder create-Lauf
 *   update     guide.force_rewrite (alle Abschnitte, unabhaengig vom Hash)
 *              oder mindestens ein geaenderter Fakt
 *   unchanged  kein geaenderter Fakt
 *
 * Geaenderte Fakten, die in keinem Abschnitt vorkommen, gehen in die
 * Key-Facts-Tabelle und per structured()-Aufruf (guide.assign_facts) in den
 * thematisch naechsten Abschnitt; die Zuordnung wird in der SectionFactMap
 * gespeichert. Scheitert der Aufruf, bleibt es bei der Key-Facts-Tabelle: Die
 * Fakten sind zu diesem Zeitpunkt schon gespeichert, ein Abbruch wuerde die
 * Aenderung beim naechsten Lauf unsichtbar machen.
 */
class ChangeDetector
{
    public const TEMPLATE_ASSIGN = 'guide.assign_facts';

    public function __construct(
        private readonly FactNormalizer $normalizer,
        private readonly LlmClient $client,
    ) {}

    /**
     * Aktuelles Fakten-Set als Vergleichsgrundlage, je Schluessel.
     *
     * @return array<string, array{key: string, label: string, value: string, unit: ?string, valid_from: ?string, source_url: ?string}>
     */
    public function snapshot(Topic $topic): array
    {
        $facts = [];

        foreach ($topic->currentFacts()->with('source')->get() as $fact) {
            /** @var Fact $fact */
            $facts[(string) $fact->key] = [
                'key' => (string) $fact->key,
                'label' => (string) $fact->label,
                'value' => (string) $fact->value,
                'unit' => $fact->unit,
                'valid_from' => $fact->valid_from?->toDateString(),
                'source_url' => $fact->source?->url,
            ];
        }

        return $facts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $before  snapshot() vor der Recherche
     * @param  array<string, array<string, mixed>>  $after  snapshot() danach
     */
    public function detect(Topic $topic, TopicRun $run, array $before, array $after): ChangeSet
    {
        $changedFacts = $this->diff($before, $after);
        $outlineIds = $topic->outlineSectionIds();

        if ($run->mode === RunMode::CREATE || $topic->article_id === null) {
            return new ChangeSet(RunMode::CREATE, $changedFacts, $outlineIds, true);
        }

        if ((bool) config('guide.force_rewrite', false)) {
            return new ChangeSet(RunMode::UPDATE, $changedFacts, $outlineIds, true, forced: true);
        }

        if ($changedFacts === []) {
            return new ChangeSet(RunMode::UNCHANGED);
        }

        $detail = $topic->articleDetail;
        $map = SectionFactMap::forDetail($detail);
        $changedKeys = array_column($changedFacts, 'key');
        $unmapped = array_values(array_filter($changedKeys, fn (string $key): bool => ! $map->isMapped($key)));

        $assigned = [];
        $error = null;

        if ($unmapped !== [] && $outlineIds !== []) {
            try {
                $assigned = $this->assign($topic, $run, $map, array_values(array_filter(
                    $changedFacts,
                    fn (array $fact): bool => in_array($fact['key'], $unmapped, true),
                )));
            } catch (Throwable $exception) {
                report($exception);
                $error = mb_substr($exception::class.': '.$exception->getMessage(), 0, 500);
            }
        }

        foreach ($unmapped as $key) {
            $map = isset($assigned[$key])
                ? $map->withAssignment($key, $assigned[$key], (int) $run->getKey(), Carbon::now()->toDateString())
                : $map->withKeyFacts([...$map->keyFacts, $key]);
        }

        if ($detail instanceof ArticleDetail && $unmapped !== []) {
            $map->saveTo($detail);
        }

        $affected = [];

        foreach ($changedKeys as $key) {
            array_push($affected, ...$map->sectionsFor($key));
        }

        return new ChangeSet(
            mode: RunMode::UPDATE,
            changedFacts: $changedFacts,
            changedSectionIds: array_values(array_intersect($outlineIds, $affected)),
            keyFactsAffected: $unmapped !== [] || array_filter($changedKeys, fn (string $key): bool => $map->inKeyFacts($key)) !== [],
            assigned: $assigned,
            assignmentError: $error,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $before
     * @param  array<string, array<string, mixed>>  $after
     * @return array<int, array<string, mixed>>
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $key => $fact) {
            $old = $before[$key] ?? null;

            if ($old !== null && $this->normalizer->same($old, $fact)) {
                continue;
            }

            $changes[] = $this->entry($fact, $old, $old === null ? 'added' : 'changed');
        }

        foreach (array_diff_key($before, $after) as $fact) {
            $changes[] = $this->entry($fact, $fact, 'removed');
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $fact
     * @param  array<string, mixed>|null  $old
     * @return array<string, mixed>
     */
    private function entry(array $fact, ?array $old, string $change): array
    {
        $display = fn (?array $f): ?string => $f === null ? null : trim(((string) $f['value']).' '.((string) ($f['unit'] ?? '')));

        return [
            'key' => (string) $fact['key'],
            'label' => (string) $fact['label'],
            'old_value' => $display($old),
            'new_value' => $change === 'removed' ? null : $display($fact),
            'unit' => $fact['unit'] ?? null,
            'valid_from' => $fact['valid_from'] ?? null,
            'source_url' => $fact['source_url'] ?? null,
            'change' => $change,
        ];
    }

    /**
     * Thematisch naechster Abschnitt je Fakt ohne Abschnitt.
     *
     * @param  array<int, array<string, mixed>>  $facts
     * @return array<string, string> fact_key => section_id
     */
    private function assign(Topic $topic, TopicRun $run, SectionFactMap $map, array $facts): array
    {
        $template = PromptTemplate::query()
            ->resolve(self::TEMPLATE_ASSIGN, tenancy()->initialized ? (int) tenant()?->getKey() : null)
            ->first();

        if ($template === null) {
            throw new RuntimeException("Prompt-Template '".self::TEMPLATE_ASSIGN."' fehlt. php artisan db:seed --class=GuidePromptTemplateSeeder ausfuehren.");
        }

        $result = $this->client->structured($template, [
            ...TenantPromptVars::current(),
            'question' => (string) $topic->question,
            'outline' => (string) json_encode($this->outline($topic, $map), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'changed_facts' => (string) json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], [], LlmCallContext::forRun($run));

        $validSections = $topic->outlineSectionIds();
        $validKeys = array_column($facts, 'key');
        $assigned = [];

        foreach ((array) ($result['assignments'] ?? []) as $assignment) {
            $key = (string) ($assignment['fact_key'] ?? '');
            $sectionId = (string) ($assignment['section_id'] ?? '');

            if (in_array($key, $validKeys, true) && in_array($sectionId, $validSections, true)) {
                $assigned[$key] ??= $sectionId;
            }
        }

        return $assigned;
    }

    /**
     * Gliederung fuer {{outline}} mit den bisher verwendeten Fakt-Schluesseln.
     *
     * @return array<int, array<string, mixed>>
     */
    private function outline(Topic $topic, SectionFactMap $map): array
    {
        $walk = function (array $sections) use (&$walk, $map): array {
            $rows = [];

            foreach ($sections as $section) {
                $id = (string) ($section['id'] ?? '');

                $rows[] = [
                    'id' => $id,
                    'level' => (int) ($section['level'] ?? 2),
                    'heading' => (string) ($section['heading'] ?? ''),
                    'fact_keys' => $map->sections[$id] ?? [],
                    'children' => $walk((array) ($section['children'] ?? [])),
                ];
            }

            return $rows;
        };

        return $walk($topic->outline_json ?? []);
    }
}
