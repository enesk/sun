<?php

declare(strict_types=1);

namespace App\Guide\Import;

use App\Guide\Models\Category;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\OutlineAnchors;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vorschau des Import-Wizards (#15, design/guide-dashboard.md §4.3).
 *
 * Wendet dieselben Regeln an wie TopicListImporter und TopicListAssigner,
 * ohne etwas zu speichern: Fehlerzeilen (werden uebersprungen), Dubletten in
 * der Datei und im Vorschau-Portal, neue Kategorien, vorgegebene
 * Gliederungen, Kategorienverteilung mit Hinweisen. Platzhalter werden fuer
 * das Vorschau-Portal aufgeloest.
 */
class ImportPreview
{
    /** Ab so vielen Kategorien ein Hinweis (empfohlen 6 bis 12). */
    public const MANY_CATEGORIES = 15;

    /** Kategorien mit weniger Themen bekommen keine eigene Seite (#18). */
    public const MIN_CATEGORY_TOPICS = 3;

    public function __construct(
        private readonly TopicRowParser $parser,
        private readonly PlaceholderResolver $placeholders,
        private readonly QuestionNormalizer $normalizer,
    ) {}

    /**
     * @param  iterable<int, array<int, mixed>>  $rows  Zeilennummer => Zellen
     * @param  array<string, int>  $columnMap  Feld => Spaltenindex
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     counts: array{new: int, duplicate: int, new_category: int, outline: int, error: int},
     *     categories: array<string, int>,
     *     hints: list<string>,
     *     importable: int,
     * }
     */
    public function build(iterable $rows, array $columnMap, bool $hasHeader, ?Tenant $tenant): array
    {
        $context = $tenant !== null ? $this->portalContext($tenant) : null;
        $result = [];
        $seen = [];
        $firstRow = true;

        foreach ($rows as $line => $cells) {
            if ($firstRow && array_filter($cells, static fn (mixed $cell): bool => trim((string) $cell) !== '') !== []) {
                $firstRow = false;

                if ($hasHeader) {
                    continue;
                }
            }

            try {
                $row = $this->parser->parse($cells, $columnMap, (int) $line);
            } catch (InvalidTopicRow $e) {
                $question = trim((string) ($cells[$columnMap[TopicRowParser::FIELD_QUESTION] ?? 0] ?? ''));
                $result[] = [
                    'line' => (int) $line,
                    'question' => $question,
                    'resolved' => $question,
                    'category' => null,
                    'outline' => [],
                    'marks' => ['error'],
                    'duplicate_of' => null,
                    'warning' => null,
                    'error' => $e->getMessage(),
                ];

                continue;
            }

            if ($row === null) {
                continue;
            }

            $result[] = $this->previewRow($row, $seen, $context);
            $seen[$row->normalizedQuestion] ??= $row->line;
        }

        return $this->summarize($result);
    }

    /**
     * @param  array<string, int>  $seen  normalisierte Frage => Zeile
     * @param  array{settings: TenantGuideSetting, questions: array<string, array{key: string, question: string}>, categories: array<string, true>}|null  $context
     * @return array<string, mixed>
     */
    private function previewRow(TopicRow $row, array $seen, ?array $context): array
    {
        $marks = [];
        $resolved = $row->question;
        $warning = null;
        $duplicateOf = null;
        $category = $row->categoryName;

        if ($context !== null) {
            try {
                $resolved = $this->placeholders->resolveForTenant($row->question, $context['settings']);
                $category = $category !== null ? $this->placeholders->resolveForTenant($category, $context['settings']) : null;
            } catch (MissingPlaceholderValue $e) {
                $warning = __('Platzhalter im Vorschau-Portal nicht belegt: :reason', ['reason' => $e->getMessage()]);
            }
        }

        if (isset($seen[$row->normalizedQuestion])) {
            $marks[] = 'duplicate';
            $duplicateOf = ['line' => $seen[$row->normalizedQuestion]];
        }

        $existing = $context['questions'][$this->normalizer->normalize($resolved)] ?? null;

        if ($existing !== null && $duplicateOf === null) {
            $marks[] = 'duplicate';
            $duplicateOf = $existing;
        }

        if ($marks === []) {
            $marks[] = 'new';
        }

        if ($category !== null && $context !== null && ! isset($context['categories'][Str::slug($category, '-', 'de')])) {
            $marks[] = 'new_category';
        }

        if ($row->outline !== null) {
            $marks[] = 'outline';
        }

        return [
            'line' => $row->line,
            'question' => $row->question,
            'resolved' => $resolved,
            'category' => $category,
            'outline' => OutlineAnchors::flatten($row->outline),
            'marks' => $marks,
            'duplicate_of' => $duplicateOf,
            'warning' => $warning,
            'error' => null,
        ];
    }

    /**
     * Vorhandene Fragen und Kategorien des Vorschau-Portals.
     *
     * @return array{settings: TenantGuideSetting, questions: array<string, array{key: string, question: string}>, categories: array<string, true>}|null
     */
    private function portalContext(Tenant $tenant): ?array
    {
        try {
            return $tenant->run(fn (): array => [
                'settings' => TenantGuideSetting::current(),
                'questions' => Topic::query()->get(['id', 'question'])
                    ->mapWithKeys(fn (Topic $topic): array => [
                        $this->normalizer->normalize($topic->question) => [
                            'key' => TopicDirectory::key($tenant->getKey(), $topic->getKey()),
                            'question' => (string) $topic->question,
                        ],
                    ])
                    ->all(),
                'categories' => Category::query()->pluck('slug')->flip()->map(fn (): bool => true)->all(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>, counts: array{new: int, duplicate: int, new_category: int, outline: int, error: int}, categories: array<string, int>, hints: list<string>, importable: int}
     */
    private function summarize(array $rows): array
    {
        $count = fn (string $mark): int => count(array_filter($rows, fn (array $row): bool => in_array($mark, $row['marks'], true)));

        // Fehlerzeilen oben (§4.3), sonst Reihenfolge der Datei.
        usort($rows, fn (array $a, array $b): int => [! in_array('error', $a['marks'], true), $a['line']] <=> [! in_array('error', $b['marks'], true), $b['line']]);

        $categories = collect($rows)
            ->reject(fn (array $row): bool => in_array('error', $row['marks'], true) || in_array('duplicate', $row['marks'], true))
            ->map(fn (array $row): string => (string) ($row['category'] ?? ''))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->all();

        return [
            'rows' => $rows,
            'counts' => [
                'new' => $count('new'),
                'duplicate' => $count('duplicate'),
                'new_category' => $count('new_category'),
                'outline' => $count('outline'),
                'error' => $count('error'),
            ],
            'categories' => $categories,
            'hints' => $this->hints($categories),
            'importable' => $count('new'),
        ];
    }

    /**
     * Hinweise zur Kategorienverteilung (Leitbild §7), je Regel ein Satz.
     * Auch fuer den Reiter Kategorien (design/guide-dashboard.md §6).
     *
     * @param  array<string, int>  $categories  Name => Zahl der Themen
     * @return list<string>
     */
    public function hints(array $categories): array
    {
        $hints = [];

        if (count($categories) >= self::MANY_CATEGORIES) {
            $hints[] = __(':count Kategorien — empfohlen sind 6 bis 12. Kleine Kategorien zusammenlegen?', ['count' => count($categories)]);
        }

        $small = count(array_filter($categories, fn (int $topics): bool => $topics < self::MIN_CATEGORY_TOPICS));

        if ($small > 0) {
            $hints[] = trans_choice(
                '{1} Eine Kategorie hat weniger als :min Themen und bekommt keine eigene Seite.|[2,*] :count Kategorien haben weniger als :min Themen und bekommen keine eigene Seite.',
                $small,
                ['count' => $small, 'min' => self::MIN_CATEGORY_TOPICS],
            );
        }

        $names = array_keys($categories);
        $pairs = 0;

        foreach ($names as $i => $a) {
            foreach (array_slice($names, $i + 1) as $b) {
                if ($pairs < 3 && $this->similar($a, $b)) {
                    $hints[] = __('„:a“ und „:b“ zusammenlegen?', ['a' => $a, 'b' => $b]);
                    $pairs++;
                }
            }
        }

        return $hints;
    }

    private function similar(string $a, string $b): bool
    {
        $a = Str::slug($a, '-', 'de');
        $b = Str::slug($b, '-', 'de');

        if ($a === $b) {
            return true;
        }

        if (min(strlen($a), strlen($b)) < 5) {
            return false;
        }

        return levenshtein($a, $b) <= 2;
    }
}
