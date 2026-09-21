<?php

declare(strict_types=1);

namespace App\Guide\Quality;

use App\Guide\Enums\RunMode;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Fact;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Writing\HtmlAssembler;
use App\Guide\Writing\WritingContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fachlogik hinter dem QualityCheckJob (#11): prueft eine Fassung und
 * entscheidet ueber Freigabe, Fix-Durchlauf oder Pruef-Queue.
 *
 * Reihenfolge: (1) Lint (Linter), (2) Faktenabgleich (FactChecker),
 * (3) Link-Check der Quellen (LinkChecker), (4) Rubrik (RubricEvaluator),
 * dazu Lesbarkeit (ReadabilityScorer, nie blockierend). Lint und
 * Faktenabgleich laufen immer ueber den ganzen Artikel; die Rubrik im
 * Update-Modus nur ueber die geaenderten Teile.
 *
 * Entscheidung:
 *  - publish: keine blockierenden Befunde und final_score >= wirksame
 *    Schwelle (TenantGuideSetting::effectiveThreshold(): eingetragener Wert,
 *    bei YMYL mindestens 90). Bei wirksamer Schwelle 100 nie, auch nicht bei
 *    final_score 100 (#38 G4). Jeder blockierende Befund verhindert die Freigabe unabhaengig
 *    vom Score — insbesondere jede unbelegte Zahl und jeder Widerspruch zum
 *    Fakten-Set.
 *  - fix: noch kein Fix-Durchlauf in diesem Lauf (guide_lint.max_fix_runs)
 *    und alle blockierenden Befunde sind nachbesserbar; der Fix-Plan nennt
 *    die Abschnitte und Anweisungen.
 *  - review: sonst. Posts werden hier nie angefasst — die veroeffentlichte
 *    Fassung bleibt online, bis der Publisher (#12) eine neue schreibt.
 *
 * Laeuft im Tenant-Kontext.
 */
class QualityGate
{
    public const DECISION_PUBLISH = 'publish';

    public const DECISION_FIX = 'fix';

    public const DECISION_REVIEW = 'review';

    public function __construct(
        private readonly Linter $linter,
        private readonly FactChecker $facts,
        private readonly LinkChecker $links,
        private readonly RubricEvaluator $rubric,
        private readonly ReadabilityScorer $readability,
        private readonly HtmlAssembler $html,
    ) {}

    /**
     * @return array<string, mixed> quality_report_json
     */
    public function check(Topic $topic, TopicRun $run, ArticleVersion $version, int $fixRuns = 0): array
    {
        $context = WritingContext::for($topic, $run);
        $setting = $context->setting;
        $isYmyl = (bool) $setting->is_ymyl;
        $threshold = $this->threshold($setting);
        $body = (string) $version->body_html;

        $lint = $this->linter->lint($version, $context->entries(), $isYmyl);

        /** @var Collection<int, Fact> $previousFacts */
        $previousFacts = $topic->facts()->where('is_current', false)->get();
        $factCheck = $this->facts->check($version, $context->facts, $previousFacts);

        $linkCheck = $this->links->check(
            $topic->sources()->get(),
            $this->html->links($body, internal: false),
            $context->facts->pluck('source_id')->filter()->map(fn (mixed $id): int => (int) $id)->values()->all(),
        );

        [$scopeIds, $extraParts] = $this->rubricScope($run);
        $rubric = $scopeIds === [] && $extraParts === []
            ? ['scope' => 'none', 'section_ids' => [], 'score' => null, 'per_criterion' => [], 'blocking_issues' => [], 'fix_instructions' => [], 'template' => null]
            : $this->rubric->evaluate($context, $version, $scopeIds, $extraParts);

        $readability = $this->readability->score($body);

        $scores = [
            'lint' => $this->linter->score($lint),
            'fact' => $this->facts->score($factCheck),
            'rubric' => $rubric['score'],
            'readability' => $readability['passed'] ? 100.0 : max(0.0, round($readability['score'] / max(1, $readability['target']) * 100, 1)),
        ];
        $finalScore = $this->finalScore($scores);

        $blocking = $this->blockingIssues($lint, $factCheck, $linkCheck, $rubric['blocking_issues']);
        $plan = $this->fixPlan($lint, $factCheck, $rubric, $context->facts);
        $decision = $this->decide($blocking, $finalScore, $threshold, $plan, $fixRuns);

        return [
            'version_id' => (int) $version->getKey(),
            'mode' => $run->mode->value,
            'checked_at' => Carbon::now()->toIso8601String(),
            'is_ymyl' => $isYmyl,
            'threshold' => $threshold,
            'final_score' => $finalScore,
            'scores' => $scores,
            'decision' => $decision,
            'fix_runs' => $fixRuns,
            'blocking_issues' => $blocking,
            'lint' => $lint,
            'fact_check' => $factCheck,
            'link_check' => $linkCheck,
            'rubric' => $rubric,
            'readability' => $readability,
            'fix_plan' => $decision === self::DECISION_FIX ? $plan : [],
        ];
    }

    public function threshold(TenantGuideSetting $setting): int
    {
        return $setting->effectiveThreshold();
    }

    /**
     * Neuanlage: ganzer Artikel (null). Update: die geschriebenen, fehlenden
     * und im Fix-Durchlauf nachgebesserten Abschnitte samt neu entstandener
     * Kurzantwort, FAQ und Meta.
     *
     * @return array{0: array<int, string>|null, 1: array<int, string>}
     */
    private function rubricScope(TopicRun $run): array
    {
        if ($run->mode !== RunMode::UPDATE) {
            return [null, []];
        }

        $writing = (array) ($run->research_json['writing'] ?? []);
        $fix = (array) ($run->research_json['quality_fix'] ?? []);

        $ids = array_values(array_unique(array_map('strval', [
            ...(array) ($writing['changed_section_ids'] ?? []),
            ...(array) ($writing['rewritten_missing_section_ids'] ?? []),
            ...(array) ($fix['section_ids'] ?? []),
        ])));

        $parts = array_keys(array_filter([
            'short_answer' => (bool) ($writing['short_answer_updated'] ?? false),
            'faq' => (bool) ($writing['faq_updated'] ?? false),
            'meta' => (bool) ($writing['meta_updated'] ?? false),
        ]));

        $parts = array_values(array_unique([...$parts, ...array_intersect(['short_answer', 'faq', 'meta'], (array) ($fix['parts'] ?? []))]));

        return [$ids, $parts];
    }

    /**
     * Gewichtete Summe; fehlt ein Teilscore (Rubrik ohne geaenderten Teil),
     * werden die uebrigen Gewichte hochgerechnet.
     *
     * @param  array<string, float|null>  $scores
     */
    private function finalScore(array $scores): float
    {
        $weights = (array) config('guide_lint.weights', []);
        $sum = 0.0;
        $total = 0.0;

        foreach ($scores as $key => $score) {
            $weight = (float) ($weights[$key] ?? 0);

            if ($score === null || $weight <= 0) {
                continue;
            }

            $sum += $weight * $score;
            $total += $weight;
        }

        return $total > 0 ? round($sum / $total, 1) : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $lint
     * @param  list<array<string, mixed>>  $factCheck
     * @param  list<array<string, mixed>>  $linkCheck
     * @param  list<array<string, mixed>>  $rubricIssues
     * @return list<array{source: string, code: string, section_id: ?string, message: string, fixable: bool}>
     */
    private function blockingIssues(array $lint, array $factCheck, array $linkCheck, array $rubricIssues): array
    {
        $issues = [];

        foreach ($lint as $row) {
            if (! $row['passed'] && $row['blocking']) {
                $issues[] = ['source' => 'lint', 'code' => "lint_{$row['rule']}", 'section_id' => $row['section_id'], 'message' => $row['message'], 'fixable' => $row['fixable'] && $row['section_id'] !== null];
            }
        }

        foreach ($this->facts->failures($factCheck) as $row) {
            $issues[] = [
                'source' => 'fact_check',
                'code' => $row['status'] === FactChecker::STATUS_CONTRADICTION ? 'faktenwiderspruch' : 'unbelegte_zahl',
                'section_id' => $row['section_id'],
                'message' => $row['status'] === FactChecker::STATUS_CONTRADICTION
                    ? "„{$row['wert']}\" widerspricht dem Fakten-Set ({$row['fact_key']}): {$row['sentence']}"
                    : "„{$row['wert']}\" ist im Fakten-Set nicht belegt: {$row['sentence']}",
                'fixable' => $row['section_id'] !== 'intro',
            ];
        }

        foreach ($linkCheck as $row) {
            if ($row['blocking']) {
                $issues[] = ['source' => 'link_check', 'code' => 'quelle_defekt', 'section_id' => null, 'message' => "{$row['url']}: {$row['message']} Blockiert, bis die Recherche eine Ersatzquelle liefert.", 'fixable' => false];
            }
        }

        foreach ($rubricIssues as $row) {
            $issues[] = [
                'source' => 'rubric',
                'code' => (string) $row['code'],
                'section_id' => $row['section_id'],
                'message' => trim("{$row['explanation']} „{$row['quote']}\""),
                'fixable' => $row['section_id'] !== null,
            ];
        }

        return $issues;
    }

    /**
     * Anweisungen je Fundort fuer den Fix-Durchlauf (ArticleWriter::fix()).
     *
     * @param  list<array<string, mixed>>  $lint
     * @param  list<array<string, mixed>>  $factCheck
     * @param  array<string, mixed>  $rubric
     * @param  Collection<int, Fact>  $current
     * @return array<string, list<string>>
     */
    private function fixPlan(array $lint, array $factCheck, array $rubric, Collection $current): array
    {
        $plan = [];
        $byKey = $current->keyBy('key');

        foreach ($lint as $row) {
            if (! $row['passed'] && $row['fixable'] && $row['section_id'] !== null) {
                $plan[$row['section_id']][] = $row['rule'] === 'ymyl_disclaimer'
                    ? 'Ergänze den Pflicht-Disclaimer laut Styleguide als eigenen Absatz.'
                    : $row['message'];
            }
        }

        foreach ($this->facts->failures($factCheck) as $row) {
            if ($row['section_id'] === 'intro') {
                continue;
            }

            if ($row['status'] === FactChecker::STATUS_CONTRADICTION) {
                /** @var Fact|null $fact */
                $fact = $row['fact_key'] !== null ? $byKey->get($row['fact_key']) : null;
                $currentValue = $fact === null ? 'kein aktueller Wert, Angabe streichen' : trim("{$fact->value} {$fact->unit}");
                $plan[$row['section_id']][] = "„{$row['wert']}\" ist ein veralteter Wert ({$row['fact_key']}); aktuell laut Fakten-Set: {$currentValue}. Satz: {$row['sentence']}";

                continue;
            }

            $plan[$row['section_id']][] = "„{$row['wert']}\" steht nicht im Fakten-Set. Streiche die Angabe oder ersetze sie durch den exakten Wert aus dem Fakten-Set. Satz: {$row['sentence']}";
        }

        foreach ((array) ($rubric['fix_instructions'] ?? []) as $row) {
            $plan[$row['section_id']][] = $row['instruction'];
        }

        foreach ((array) ($rubric['blocking_issues'] ?? []) as $row) {
            if ($row['section_id'] !== null) {
                $plan[$row['section_id']][] = trim("{$row['code']}: {$row['explanation']} „{$row['quote']}\"");
            }
        }

        return array_map(fn (array $items): array => array_values(array_unique($items)), $plan);
    }

    /**
     * @param  list<array{fixable: bool}>  $blocking
     * @param  array<string, list<string>>  $plan
     */
    private function decide(array $blocking, float $finalScore, int $threshold, array $plan, int $fixRuns): string
    {
        if ($blocking === [] && $finalScore >= $threshold && TenantGuideSetting::allowsAutoPublish($threshold)) {
            return self::DECISION_PUBLISH;
        }

        $fixable = array_diff_key($plan, ['artikel' => true]) !== []
            && array_filter($blocking, fn (array $issue): bool => ! $issue['fixable']) === [];

        if ($fixRuns < (int) config('guide_lint.max_fix_runs', 1) && $fixable) {
            return self::DECISION_FIX;
        }

        return self::DECISION_REVIEW;
    }
}
