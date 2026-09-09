<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Models\ArticleDraft;
use App\Content\Models\FactSnippet;

/**
 * Liest `article_drafts.quality_report_json` fuer die Pruefflaeche (#20).
 *
 * Das Qualitaetsgate (#15) schreibt den Bericht nach dem Schema des Templates
 * `quality_rubric` (docs/content-prompts.md, §6): `score`, `per_criterion[]`,
 * `blocking_issues[]` und `fix_instructions[]`. Aeltere bzw. abweichende
 * Schreibweisen (`rubrics` als Zuordnung, wie sie das Board in #19 liest)
 * werden mitgelesen — die Pruefung darf nicht an einer Schluesselvariante
 * scheitern, solange #15 noch im Bau ist.
 *
 * Der Faktencheck kommt aus dem Bericht, wenn er dort steht, sonst aus den
 * `fact_snippets` des Entwurfs. So ist die Tabelle auch dann gefuellt, wenn
 * das Gate nur bewertet und nicht protokolliert hat.
 */
final class QualityReportPresenter
{
    /**
     * @return array{
     *     score: float|null,
     *     threshold: int,
     *     level: string|null,
     *     criteria: list<array{label: string, score: float, weight: float|null, reason: string|null, below: bool}>,
     *     blocking_issues: list<array{text: string, anchor: string|null}>,
     *     fact_checks: list<array{statement: string, verdict: string|null, source: string|null, url: string|null, note: string|null, stale: bool}>,
     *     seo_lint: list<array{check: string, status: string, message: string|null, blocking: bool}>,
     *     fix_instructions: list<string>,
     *     fix_runs: int|null,
     *     decision: string|null,
     *     checked_at: string|null
     * }
     */
    public function present(ArticleDraft $draft, int $threshold): array
    {
        $report = $draft->quality_report_json ?? [];

        $score = $draft->quality_score !== null
            ? (float) $draft->quality_score
            : (isset($report['score']) ? (float) $report['score'] : null);

        return [
            'score' => $score,
            'threshold' => $threshold,
            'level' => $this->level($score, $threshold),
            'criteria' => $this->criteria($report, $threshold),
            'blocking_issues' => $this->blockingIssues($report),
            'fact_checks' => $this->factChecks($draft, $report),
            'seo_lint' => $this->seoLint($report),
            'fix_instructions' => $this->fixInstructions($report),
            'fix_runs' => isset($report['quality']['fix_runs']) ? (int) $report['quality']['fix_runs'] : null,
            'decision' => isset($report['quality']['decision']) ? (string) $report['quality']['decision'] : null,
            'checked_at' => isset($report['checked_at']) ? (string) $report['checked_at'] : null,
        ];
    }

    public function level(?float $score, int $threshold): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= $threshold => 'good',
            $score >= $threshold * 0.75 => 'mid',
            default => 'poor',
        };
    }

    /**
     * Rubriken unter der Freigabegrenze zuerst — sie sind der Grund, warum
     * der Artikel hier liegt (design/content-dashboard.md, §4).
     *
     * @param  array<string, mixed>  $report
     * @return list<array{label: string, score: float, weight: float|null, reason: string|null, below: bool}>
     */
    private function criteria(array $report, int $threshold): array
    {
        $raw = $report['per_criterion'] ?? $report['criteria'] ?? $report['rubrics'] ?? [];
        $criteria = [];

        foreach ((array) $raw as $key => $entry) {
            if (is_array($entry)) {
                $label = (string) ($entry['label'] ?? $entry['name'] ?? $entry['criterion'] ?? (is_string($key) ? $key : ''));
                $value = (float) ($entry['score'] ?? $entry['value'] ?? 0);
                $weight = isset($entry['weight']) ? (float) $entry['weight'] : null;
                $reason = $entry['reason'] ?? $entry['note'] ?? $entry['comment'] ?? null;
            } else {
                $label = is_string($key) ? $key : '';
                $value = (float) $entry;
                $weight = null;
                $reason = null;
            }

            if (trim($label) === '') {
                continue;
            }

            // Kriteriumsscores kommen je nach Stufe als 0-100 oder als
            // Gewichtsanteil. Der Balken rechnet auf Prozent des Gewichts.
            $percent = $weight !== null && $weight > 0 && $value <= $weight
                ? ($value / $weight) * 100
                : $value;

            $criteria[] = [
                'label' => trim($label),
                'score' => round($percent, 1),
                'weight' => $weight,
                'reason' => is_string($reason) && trim($reason) !== '' ? trim($reason) : null,
                'below' => $percent < $threshold,
            ];
        }

        usort($criteria, fn (array $a, array $b): int => [$b['below'], $a['score']] <=> [$a['below'], $b['score']]);

        return $criteria;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{text: string, anchor: string|null}>
     */
    private function blockingIssues(array $report): array
    {
        $issues = [];

        foreach ((array) ($report['blocking_issues'] ?? []) as $entry) {
            if (is_string($entry)) {
                $text = trim($entry);
                $anchor = null;
            } elseif (is_array($entry)) {
                $text = trim((string) ($entry['issue'] ?? $entry['text'] ?? $entry['message'] ?? ''));
                $anchor = isset($entry['anchor']) ? (string) $entry['anchor'] : null;
            } else {
                continue;
            }

            if ($text === '') {
                continue;
            }

            $issues[] = ['text' => $text, 'anchor' => $anchor];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<array{statement: string, verdict: string|null, source: string|null, url: string|null, note: string|null, stale: bool}>
     */
    private function factChecks(ArticleDraft $draft, array $report): array
    {
        $rows = [];

        foreach ((array) ($report['fact_check'] ?? $report['facts'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $statement = trim((string) ($entry['statement'] ?? $entry['claim'] ?? ''));

            if ($statement === '') {
                continue;
            }

            $rows[] = [
                'statement' => $statement,
                'verdict' => isset($entry['verdict']) ? (string) $entry['verdict'] : (isset($entry['status']) ? (string) $entry['status'] : null),
                'source' => isset($entry['source']) ? (string) $entry['source'] : null,
                'url' => isset($entry['url']) ? (string) $entry['url'] : null,
                'note' => isset($entry['note']) ? (string) $entry['note'] : null,
                'stale' => (bool) ($entry['stale'] ?? false),
            ];
        }

        if ($rows !== []) {
            return $rows;
        }

        return $draft->factSnippets()
            ->orderByDesc('confidence')
            ->get()
            ->map(fn (FactSnippet $snippet): array => [
                'statement' => trim((string) $snippet->statement),
                'verdict' => $snippet->verified_at !== null ? __('belegt') : __('ungeprüft'),
                'source' => $snippet->source_name,
                'url' => $snippet->source_url,
                'note' => trim(implode(' ', array_filter([
                    (string) $snippet->value,
                    (string) $snippet->unit,
                    $snippet->period !== null ? "({$snippet->period})" : null,
                ]))) ?: null,
                'stale' => $snippet->verified_at === null,
            ])
            ->values()
            ->all();
    }

    /**
     * Die SEO-Pruefliste des Reports (§4.2). Beschriftung ist immer die
     * deutsche 'label' des Linters — ein snake_case-Bezeichner hat in einer
     * deutschen Pruefflaeche nichts zu suchen. 'blocking' geht mit in die
     * Zeile, weil ein roter Punkt allein nicht zwischen 'kostet Punkte' und
     * 'verhindert die Freigabe' unterscheidet.
     *
     * @param  array<string, mixed>  $report
     * @return list<array{check: string, status: string, message: string|null, blocking: bool}>
     */
    private function seoLint(array $report): array
    {
        $rows = [];

        foreach ((array) ($report['seo_lint'] ?? $report['seo'] ?? []) as $key => $entry) {
            if (is_array($entry)) {
                $check = (string) ($entry['label'] ?? $entry['check'] ?? (is_string($key) ? $key : ''));
                $status = (string) ($entry['status'] ?? (($entry['passed'] ?? true) ? 'ok' : 'fail'));
                $message = isset($entry['message']) ? (string) $entry['message'] : null;
                $blocking = (bool) ($entry['blocking'] ?? false);
            } else {
                $check = is_string($key) ? $key : '';
                $status = $entry === true || $entry === 'ok' ? 'ok' : 'fail';
                $message = is_string($entry) && $entry !== 'ok' ? $entry : null;
                $blocking = false;
            }

            if (trim($check) === '') {
                continue;
            }

            $rows[] = [
                'check' => trim($check),
                'status' => $status,
                'message' => $message,
                'blocking' => $blocking,
            ];
        }

        return $this->sortSeoLint($rows);
    }

    /**
     * Oben steht, was Arbeit macht: fail, warn, ok, skipped. Innerhalb einer
     * Gruppe bleibt die Reihenfolge der Regelkonfiguration erhalten.
     *
     * @param  list<array{check: string, status: string, message: string|null, blocking: bool}>  $rows
     * @return list<array{check: string, status: string, message: string|null, blocking: bool}>
     */
    private function sortSeoLint(array $rows): array
    {
        $order = ['fail' => 0, 'warn' => 1, 'ok' => 2, 'skipped' => 3];

        usort($rows, static fn (array $a, array $b): int => ($order[$a['status']] ?? 4) <=> ($order[$b['status']] ?? 4));

        return array_values($rows);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return list<string>
     */
    private function fixInstructions(array $report): array
    {
        $instructions = [];

        foreach ((array) ($report['fix_instructions'] ?? []) as $entry) {
            $text = is_array($entry)
                ? trim((string) ($entry['instruction'] ?? $entry['text'] ?? ''))
                : trim((string) $entry);

            if ($text !== '') {
                $instructions[] = $text;
            }
        }

        return $instructions;
    }
}
