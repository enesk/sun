<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Enums\RunMode;
use App\Guide\Enums\RunStatus;
use App\Guide\Jobs\PublishArticleJob;
use App\Guide\Jobs\UpdateSectionsJob;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Fact;
use App\Guide\Models\Source;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Publishing\VersionStore;
use App\Guide\Research\ResearchService;
use App\Guide\Research\SectionFactMap;
use App\Guide\Support\GuidePreviewLink;
use App\Guide\Support\OutlineAnchors;
use App\Guide\Support\UnreachableSources;
use App\Guide\Writing\HtmlAssembler;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Pruef-Queue (#16, design/guide-dashboard.md §8): Pruefblatt eines Laufs in
 * review und die drei Entscheidungen.
 *
 *  - approve(): PublishArticleJob mit der juengsten Fassung des Laufs.
 *  - rewrite(): review -> writing, UpdateSectionsJob mit dem Hinweis als
 *    fix_instructions (ArticleWriter::revise()).
 *  - discard(): review -> failed mit Grund; die veroeffentlichte Fassung
 *    bleibt unberuehrt online.
 *
 * Alle Methoden erwarten den Tenant und oeffnen den Kontext selbst; das
 * Panel arbeitet central. Wer entschieden hat, steht in
 * research_json.review (Protokoll, keine Fachlogik haengt daran).
 */
class ReviewService
{
    /** Changelog-Satz im Pruefblatt (§8.2 Punkt 3). */
    public const CHANGELOG_MAX_LENGTH = 160;

    public const DISCARD_REASONS = [
        'fakten' => 'Fakten falsch oder nicht belegt',
        'quellen' => 'Quellen unzureichend',
        'stil' => 'Sprache oder Stil passt nicht',
        'thema' => 'Thema verfehlt',
        'sonstiges' => 'Sonstiges',
    ];

    public function __construct(
        private readonly HtmlAssembler $html,
        private readonly VersionStore $versions,
        private readonly ArticleDiffRenderer $diff,
        private readonly GuidePreviewLink $previewLink,
        private readonly RunOverviewService $overview,
        private readonly TopicDirectory $directory,
    ) {}

    /**
     * Daten des Pruefblatts (§8.2). null, wenn der Lauf fehlt.
     *
     * @return array<string, mixed>|null
     */
    public function sheet(Tenant $tenant, int $runId): ?array
    {
        return $tenant->run(function () use ($tenant, $runId): ?array {
            /** @var TopicRun|null $run */
            $run = TopicRun::query()->with(['topic.category', 'topic.article'])->find($runId);

            if ($run === null || $run->topic === null) {
                return null;
            }

            $topic = $run->topic;
            /** @var ArticleVersion|null $new */
            $new = $run->versions()->orderByDesc('id')->first();
            $old = $topic->article !== null ? $this->versions->current($topic->article) : null;
            $report = (array) ($run->quality_report_json ?? []);
            $unreachable = UnreachableSources::forTopic($topic);

            return [
                'tenant_id' => (int) $tenant->getKey(),
                'tenant_name' => (string) $tenant->name,
                'run_id' => (int) $run->getKey(),
                'topic_id' => (int) $topic->getKey(),
                'topic_key' => TopicDirectory::key($tenant->getKey(), $topic->getKey()),
                'question' => (string) $topic->question,
                'category' => $topic->category?->name,
                'status' => $run->status->value,
                'is_review' => $run->status === RunStatus::REVIEW,
                'decision' => $run->research_json['review'] ?? null,
                'mode' => $run->mode?->value,
                'mode_label' => $run->mode === RunMode::CREATE ? __('Neuanlage') : __('Aktualisierung'),
                'run_at' => ($run->started_at ?? $run->created_at)?->toIso8601String(),
                'cost' => (float) $run->cost_usd,
                'awaits_outline' => ! $topic->isOutlineLocked(),
                'has_version' => $new !== null,
                'version_id' => $new?->getKey(),
                'old_version' => $old?->version,
                'new_version' => $new?->version,
                'has_report' => $report !== [],
                'reasons' => $this->reasons($report, $unreachable),
                'unreachable' => $unreachable,
                'report' => $this->reportSummary($report),
                'changelog' => $this->changelog($old, $new),
                'sections' => $new !== null ? $this->sections($topic, $old, $new, $run, $unreachable) : [],
                'extras' => $new !== null ? $this->extras($old, $new) : [],
                'preview_url' => $new !== null ? $this->previewLink->for($tenant, $new) : null,
            ];
        });
    }

    public function approve(Tenant $tenant, int $runId): void
    {
        $versionId = $tenant->run(function () use ($runId): int {
            $run = $this->reviewRun($runId);
            /** @var ArticleVersion|null $version */
            $version = $run->versions()->orderByDesc('id')->first();

            if ($version === null) {
                throw new RuntimeException(__('Dieser Lauf hat noch keine Fassung, die freigegeben werden könnte.'));
            }

            $this->record($run, 'approved');

            return (int) $version->getKey();
        });

        PublishArticleJob::dispatch((int) $tenant->getKey(), $runId, $versionId);

        $this->forget();
    }

    public function rewrite(Tenant $tenant, int $runId, string $instructions): void
    {
        $instructions = trim($instructions);

        if ($instructions === '') {
            throw new RuntimeException(__('Bitte beschreiben Sie, was anders werden soll.'));
        }

        $tenant->run(function () use ($runId, $instructions): void {
            $run = $this->reviewRun($runId);

            if (! $run->topic?->isOutlineLocked()) {
                throw new RuntimeException(__('Die Gliederung ist nicht gesperrt. Bitte zuerst im Gliederungs-Editor sperren.'));
            }

            // Sofort auf writing: der Eintrag verschwindet aus der Queue, auch
            // wenn der Job noch wartet. UpdateSectionsJob akzeptiert den Stand.
            $run->forceFill(['status' => RunStatus::WRITING])->save();
            $this->record($run, 'rewrite', $instructions);
        });

        UpdateSectionsJob::dispatch((int) $tenant->getKey(), $runId, true, $instructions);

        $this->forget();
    }

    public function discard(Tenant $tenant, int $runId, string $reason, ?string $note = null): void
    {
        $label = self::DISCARD_REASONS[$reason] ?? self::DISCARD_REASONS['sonstiges'];
        $text = trim('Verworfen in der Prüfung: '.$label.(filled($note) ? ' – '.trim((string) $note) : ''));

        $tenant->run(function () use ($runId, $text, $reason, $note): void {
            $run = $this->reviewRun($runId);

            $run->forceFill([
                'status' => RunStatus::FAILED,
                'error' => mb_substr($text, 0, 2000),
                'finished_at' => Carbon::now(),
            ])->save();

            $this->record($run, 'discarded', trim($reason.' '.(string) $note));
        });

        $this->forget();
    }

    /**
     * "Formulierung aendern" (§8.2 Punkt 3, #33): setzt den Changelog-Satz der
     * zu pruefenden Fassung. Gibt es noch keinen neuen Eintrag (Fakt geaendert,
     * aber kein Satz), wird er vorn angelegt; damit ist die Freigabe wieder
     * moeglich. Satz und Lauf (change_summary) bleiben gleich.
     */
    public function updateChangelog(Tenant $tenant, int $runId, string $summary): void
    {
        $summary = trim((string) preg_replace('/\s+/u', ' ', $summary));

        if ($summary === '') {
            throw new RuntimeException(__('Bitte einen Satz eingeben.'));
        }

        if (mb_strlen($summary) > self::CHANGELOG_MAX_LENGTH) {
            throw new RuntimeException(__('Höchstens :max Zeichen.', ['max' => self::CHANGELOG_MAX_LENGTH]));
        }

        $tenant->run(function () use ($runId, $summary): void {
            $run = $this->reviewRun($runId);
            /** @var ArticleVersion|null $new */
            $new = $run->versions()->orderByDesc('id')->first();

            if ($new === null) {
                throw new RuntimeException(__('Dieser Lauf hat noch keine Fassung.'));
            }

            $topic = $run->topic;
            $old = $topic?->article !== null ? $this->versions->current($topic->article) : null;
            $entries = array_values((array) ($new->changelog_json ?? []));
            $hasNewEntry = $this->changelog($old, $new)['new'] !== null;

            if ($hasNewEntry) {
                $entries[0] = [...$entries[0], 'summary' => $summary];
            }

            if (! $hasNewEntry) {
                array_unshift($entries, [
                    'date' => Carbon::now((string) config('guide.timezone', 'Europe/Berlin'))->toDateString(),
                    'summary' => $summary,
                    'changed_section_ids' => array_values((array) ($run->research_json['writing']['changed_section_ids'] ?? $run->changed_section_ids_json ?? [])),
                    'source_urls' => [],
                    'source' => null,
                ]);
            }

            $new->forceFill(['changelog_json' => $entries, 'change_summary' => $summary])->save();
            $run->forceFill(['change_summary' => $summary])->save();

            Log::info('Ratgeber: Changelog-Satz in der Pruefung geaendert.', [
                'tenant_id' => tenant()?->getTenantKey(),
                'run_id' => $run->getKey(),
                'user_id' => filament()->auth()->user()?->getAuthIdentifier(),
            ]);
        });

        $this->forget();
    }

    /**
     * Lauf fuer eine Entscheidung; steht er nicht mehr auf review, hat
     * jemand anderes schon entschieden (§8.4).
     */
    private function reviewRun(int $runId): TopicRun
    {
        /** @var TopicRun|null $run */
        $run = TopicRun::query()->with('topic')->find($runId);

        if ($run === null) {
            throw new RuntimeException(__('Der Lauf existiert nicht mehr.'));
        }

        if ($run->status !== RunStatus::REVIEW || ($run->research_json['review']['decision'] ?? null) === 'approved') {
            throw new RuntimeException(__('Über diesen Lauf wurde bereits entschieden.'));
        }

        return $run;
    }

    private function record(TopicRun $run, string $decision, ?string $note = null): void
    {
        $user = filament()->auth()->user();

        $run->forceFill([
            'research_json' => [...(array) ($run->research_json ?? []), 'review' => [
                'decision' => $decision,
                'note' => $note,
                'user_id' => $user?->getAuthIdentifier(),
                'user_name' => $user?->name,
                'at' => Carbon::now()->toIso8601String(),
            ]],
        ])->save();

        Log::info('Ratgeber: Entscheidung in der Pruef-Queue.', [
            'tenant_id' => tenant()?->getTenantKey(),
            'run_id' => $run->getKey(),
            'decision' => $decision,
            'user_id' => $user?->getAuthIdentifier(),
        ]);
    }

    private function forget(): void
    {
        $this->overview->forget();
        $this->directory->forget();
    }

    /**
     * Anlass im Klartext (§8.2 Punkt 1): Quellen-Satz zuerst (§5.7.5 A.1),
     * danach die blockierenden Befunde des Gates.
     *
     * @param  array<string, mixed>  $report
     * @param  list<array<string, mixed>>  $unreachable
     * @return list<string>
     */
    private function reasons(array $report, array $unreachable): array
    {
        $reasons = [];

        if (($sentence = UnreachableSources::reviewReason($unreachable)) !== null) {
            $reasons[] = $sentence;
        }

        foreach ((array) ($report['blocking_issues'] ?? []) as $issue) {
            // Die kaputte Quelle steht schon im Satz oben.
            if (is_array($issue) && ($issue['code'] ?? null) !== 'quelle_defekt' && filled($issue['message'] ?? null)) {
                $reasons[] = (string) $issue['message'];
            }
        }

        if ($reasons === [] && isset($report['final_score'], $report['threshold']) && (float) $report['final_score'] < (float) $report['threshold']) {
            $reasons[] = __('Die Qualitätsbewertung liegt mit :score unter der Schwelle für die automatische Freigabe (:threshold).', [
                'score' => (int) round((float) $report['final_score']),
                'threshold' => (int) $report['threshold'],
            ]);
        }

        return array_values(array_unique($reasons));
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function reportSummary(array $report): array
    {
        return [
            'final_score' => isset($report['final_score']) ? (int) round((float) $report['final_score']) : null,
            'threshold' => $report['threshold'] ?? null,
            'scores' => (array) ($report['scores'] ?? []),
            'fix_runs' => (int) ($report['fix_runs'] ?? 0),
            'lint' => array_values(array_filter((array) ($report['lint'] ?? []), fn (mixed $row): bool => is_array($row) && ! ($row['passed'] ?? true))),
            'fact_check' => array_values(array_filter((array) ($report['fact_check'] ?? []), 'is_array')),
            'link_check' => array_values(array_filter((array) ($report['link_check'] ?? []), fn (mixed $row): bool => is_array($row) && ($row['blocking'] ?? false))),
            'rubric' => array_values(array_filter((array) ($report['rubric']['per_criterion'] ?? []), 'is_array')),
            'readability' => is_array($report['readability'] ?? null) ? ($report['readability']['score'] ?? null) : ($report['readability'] ?? null),
        ];
    }

    /**
     * Changelog-Vorschlag: neuer Eintrag = Eintrag der neuen Fassung, der in
     * der bisherigen nicht steht.
     *
     * @return array{new: array<string, mixed>|null, previous: list<array<string, mixed>>}
     */
    private function changelog(?ArticleVersion $old, ?ArticleVersion $new): array
    {
        $before = (array) ($old?->changelog_json ?? []);
        $after = (array) ($new?->changelog_json ?? []);
        $first = $after[0] ?? null;

        $isNew = is_array($first) && ! in_array($first, $before, false);

        return [
            'new' => $isNew ? $first : null,
            'previous' => array_values(array_filter(array_slice($after, $isNew ? 1 : 0, 3), 'is_array')),
        ];
    }

    /**
     * Abschnitte entlang der Gliederung, Diff anhand der Abschnitts-ids.
     *
     * @param  list<array<string, mixed>>  $unreachable
     * @return list<array<string, mixed>>
     */
    private function sections(Topic $topic, ?ArticleVersion $old, ArticleVersion $new, TopicRun $run, array $unreachable): array
    {
        $before = $this->html->split((string) ($old?->body_html ?? $topic->article?->getAttribute('body') ?? ''));
        $after = $this->html->split((string) $new->body_html);
        $map = SectionFactMap::fromArray($new->section_fact_map_json);
        $changedFacts = collect((array) ($run->research_json['changed_facts'] ?? []))->filter(fn (mixed $fact): bool => is_array($fact))->keyBy('key');
        $brokenIds = array_column($unreachable, 'id');
        $replaced = $this->replacedSources($topic, $run);

        $facts = Fact::query()
            ->where('guide_topic_id', $topic->getKey())
            ->where('is_current', true)
            ->with('source')
            ->get()
            ->groupBy('key');

        $rows = [];

        foreach (OutlineAnchors::flatten($topic->outline_json) as $entry) {
            $id = (string) $entry['id'];
            $oldBody = isset($before[$id]) ? $this->html->body($before[$id]) : null;
            $newBody = isset($after[$id]) ? $this->html->body($after[$id]) : null;
            $changed = trim((string) $oldBody) !== trim((string) $newBody);
            $keys = $map->sections[$id] ?? [];

            $rows[] = [
                'id' => $id,
                'level' => (int) $entry['level'],
                'heading' => (string) $entry['text'],
                'changed' => $changed,
                'reasons' => collect($keys)
                    ->filter(fn (string $key): bool => $changedFacts->has($key))
                    ->map(fn (string $key): string => __('Fakt geändert: :label :old → :new', [
                        'label' => $changedFacts[$key]['label'] ?? $key,
                        'old' => $changedFacts[$key]['old_value'] ?? '–',
                        'new' => $changedFacts[$key]['new_value'] ?? '–',
                    ]))
                    ->values()
                    ->all(),
                'diff' => $changed ? $this->diff->diff($oldBody, $newBody) : null,
                'sources' => $changed ? $this->sourcesFor($keys, $facts, $brokenIds, $replaced, $run) : [],
            ];
        }

        return $rows;
    }

    /**
     * Im Lauf ersetzte Belege (§5.7.5 A.2): Fakt-key => alte URL und Label
     * der alten Quelle (Titel, sonst Herausgeber, sonst URL). Zuordnung nur
     * ueber den Fakt-key aus research_json.replace_sources.
     *
     * @return array<string, list<array{url: string, label: string}>>
     */
    private function replacedSources(Topic $topic, TopicRun $run): array
    {
        $entries = collect((array) ($run->research_json['replace_sources'] ?? []))
            ->filter(fn (mixed $entry): bool => is_array($entry) && filled($entry['key'] ?? null) && filled($entry['url'] ?? null));

        if ($entries->isEmpty()) {
            return [];
        }

        $oldSources = Source::query()
            ->where('guide_topic_id', $topic->getKey())
            ->get()
            ->keyBy(fn (Source $source): string => ResearchService::urlKey((string) $source->url));

        return $entries
            ->groupBy(fn (array $entry): string => (string) $entry['key'])
            ->map(fn ($group): array => $group
                ->map(function (array $entry) use ($oldSources): array {
                    $url = (string) $entry['url'];
                    $source = $oldSources->get(ResearchService::urlKey($url));

                    return ['url' => $url, 'label' => (string) ($source?->title ?? $source?->publisher ?? $url)];
                })
                ->values()
                ->all())
            ->all();
    }

    /**
     * Quellen zu einem Abschnitt: ueber die Fakten des Abschnitts, nicht
     * erreichbare zuerst (§5.7.5 A.2). Eine im Lauf ersetzte alte Quelle
     * erscheint nur, solange sie noch einen anderen aktuellen Fakt belegt;
     * die neue traegt "neu in diesem Lauf" und die ersetzten Label.
     *
     * @param  array<int, string>  $keys
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, Fact>>  $facts
     * @param  array<int, int>  $brokenIds
     * @param  array<string, list<array{url: string, label: string}>>  $replaced
     * @return list<array<string, mixed>>
     */
    private function sourcesFor(array $keys, $facts, array $brokenIds, array $replaced, TopicRun $run): array
    {
        $timezone = (string) config('guide.timezone');
        $runStart = $run->started_at ?? $run->created_at;
        $sources = [];

        foreach ($keys as $key) {
            foreach ($facts->get($key, collect()) as $fact) {
                /** @var Source|null $source */
                $source = $fact->source;

                if ($source === null) {
                    continue;
                }

                $sources[$source->getKey()] ??= [
                    'id' => (int) $source->getKey(),
                    'url' => (string) $source->url,
                    'title' => $source->title,
                    'publisher' => $source->publisher,
                    'published_at' => $source->published_at?->timezone($timezone)->format('d.m.Y'),
                    'trust' => $source->trust_level?->label(),
                    'unreachable' => in_array((int) $source->getKey(), $brokenIds, true),
                    'code' => $source->link_status_code,
                    'checked_at' => $source->link_checked_at?->timezone($timezone)->format('d.m., H:i'),
                    'is_new' => $runStart !== null && $source->created_at !== null && $source->created_at->gte($runStart),
                    'replaces' => [],
                    'facts' => [],
                ];

                $sourceKey = ResearchService::urlKey((string) $source->url);

                foreach ($replaced[$key] ?? [] as $old) {
                    if (ResearchService::urlKey($old['url']) === $sourceKey) {
                        continue;
                    }

                    $sources[$source->getKey()]['is_new'] = true;
                    $sources[$source->getKey()]['replaces'][] = $old['label'];
                }

                $sources[$source->getKey()]['facts'][] = trim(($fact->label ?: $fact->key).': '.$fact->value.' '.($fact->unit ?? ''));
            }
        }

        return collect($sources)
            ->map(fn (array $source): array => [...$source, 'replaces' => array_values(array_unique($source['replaces']))])
            ->sortByDesc('unreachable')
            ->values()
            ->all();
    }

    /**
     * Kurzantwort, FAQ und Meta, falls geaendert (§8.2 Punkt 5).
     *
     * @return list<array{label: string, diff: array<string, mixed>}>
     */
    private function extras(?ArticleVersion $old, ArticleVersion $new): array
    {
        $faq = fn (?ArticleVersion $version): string => collect((array) ($version?->faq_json ?? []))
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(fn (array $item): string => '<p>'.e((string) ($item['question'] ?? '')).'</p><p>'.e((string) ($item['answer'] ?? '')).'</p>')
            ->implode("\n");

        $parts = [
            __('Kurzantwort') => ['<p>'.e((string) $old?->short_answer).'</p>', '<p>'.e((string) $new->short_answer).'</p>'],
            __('Häufige Fragen') => [$faq($old), $faq($new)],
            __('Titel und Beschreibung') => [
                '<p>'.e((string) $old?->meta_title).'</p><p>'.e((string) $old?->meta_description).'</p>',
                '<p>'.e((string) $new->meta_title).'</p><p>'.e((string) $new->meta_description).'</p>',
            ],
        ];

        $rows = [];

        foreach ($parts as $label => [$before, $after]) {
            $diff = $this->diff->diff($before, $after);

            if (! $diff['identical']) {
                $rows[] = ['label' => $label, 'diff' => $diff];
            }
        }

        return $rows;
    }
}
