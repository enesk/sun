<?php

declare(strict_types=1);

namespace App\Content\Llm;

use App\Content\Generation\FaqStep;
use App\Content\Generation\FixSectionsStep;
use App\Content\Generation\MetaStep;
use App\Content\Generation\OutlineStep;
use App\Content\Generation\RegionalBlockStep;
use App\Content\Generation\SectionStep;
use App\Content\Generation\ShortAnswerStep;
use App\Content\Jobs\ClusterLeadQuestionsJob;
use App\Content\Jobs\DiscoverTopicsJob;
use App\Content\Quality\RubricEvaluator;

/**
 * Vertrag zwischen einer Prompt-Vorlage und der Pipeline-Stufe, die sie
 * benutzt (design/content-dashboard.md, §7b.1 Abschnitt 1 und 2).
 *
 * Reine Nachschlageklasse: kein Container, kein Modell, kein
 * GenerationContext. Nur so ist dieselbe Antwort im Job (Schemawahl zur
 * Laufzeit) und im Formular (gesperrtes Feld, Pille "Schema veraltet")
 * verfuegbar, ohne die Pruefung zweimal zu schreiben.
 */
final class PromptSchemaContract
{
    /**
     * Zuordnung Vorlagenschluessel -> Code-Vertrag, verbindlicher Stand aus
     * §7b.1 Abschnitt 2. Kein Eintrag haben `system_ratgeber_redakteur` und
     * alle `styleguide_*`; `refresh_update` hat seit #24 einen, weil der
     * Refresh-Loop denselben FixSectionsStep benutzt wie der Fix-Durchlauf
     * des Qualitaetsgates.
     *
     * Ein Pfad ist ein punktgetrennter Zeiger in das Schema. Wo eine
     * Konstante existiert, wird sie gelesen statt abgeschrieben.
     *
     * @var array<string, array{class: class-string, paths: array<string, array<int, string>>}>
     */
    private const MAP = [
        'topic_discover' => [
            'class' => DiscoverTopicsJob::class,
            'paths' => [
                'properties.topics.items.required' => DiscoverTopicsJob::REQUIRED_TOPIC_FIELDS,
            ],
        ],
        'outline' => [
            'class' => OutlineStep::class,
            'paths' => [
                'required' => ['title', 'outline'],
                'properties.outline.items.required' => ['heading', 'level', 'key_points'],
            ],
        ],
        'section_write' => [
            'class' => SectionStep::class,
            'paths' => [
                'required' => ['heading', 'summary_sentence', 'html', 'word_count', 'used_fact_ids', 'used_link_urls'],
            ],
        ],
        'short_answer' => [
            'class' => ShortAnswerStep::class,
            'paths' => [
                'required' => ['short_answer'],
            ],
        ],
        'faq' => [
            'class' => FaqStep::class,
            'paths' => [
                'required' => ['faq'],
                'properties.faq.items.required' => ['question', 'answer'],
            ],
        ],
        'meta' => [
            'class' => MetaStep::class,
            'paths' => [
                'required' => ['meta_title', 'meta_description'],
            ],
        ],
        'regional_block' => [
            'class' => RegionalBlockStep::class,
            'paths' => [
                'required' => ['heading', 'intro', 'html', 'outro'],
            ],
        ],
        'refresh_update' => [
            'class' => FixSectionsStep::class,
            'paths' => [
                'required' => ['heading', 'summary_sentence', 'html', 'used_fact_ids'],
            ],
        ],
        'quality_rubric' => [
            'class' => RubricEvaluator::class,
            'paths' => [
                'required' => ['score', 'per_criterion', 'blocking_issues', 'fix_instructions'],
            ],
        ],
        'lead_question_cluster' => [
            'class' => ClusterLeadQuestionsJob::class,
            'paths' => [
                'required' => ['questions'],
                'properties.questions.items.required' => ClusterLeadQuestionsJob::REQUIRED_QUESTION_FIELDS,
            ],
        ],
    ];

    /**
     * @param  array<string, array<int, string>>  $paths
     */
    private function __construct(
        private readonly string $key,
        private readonly string $className,
        private readonly array $paths,
    ) {}

    /**
     * Eintrag der Zuordnungstabelle, `null` = kein Code-Vertrag.
     */
    public static function for(?string $key): ?self
    {
        $entry = self::MAP[(string) $key] ?? null;

        if ($entry === null) {
            return null;
        }

        return new self((string) $key, $entry['class'], $entry['paths']);
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * Vollstaendiger Klassenname als Klartext fuer die Herkunftszeile.
     */
    public function className(): string
    {
        return $this->className;
    }

    /**
     * Fehlende Pflichtfelder; leer = Schema passt.
     *
     * Fehlt ein Pfad oder ist er kein Array, gelten alle dort deklarierten
     * Felder als fehlend. Ein leeres oder fehlendes Schema gilt als "passt
     * nicht" — der Zustand "Leer und schlecht" aus §7b.
     *
     * @param  array<string, mixed>|null  $schema
     * @return array<int, string>
     */
    public function missingFields(?array $schema): array
    {
        if ($schema === null || $schema === []) {
            return $this->allFields();
        }

        $missing = [];

        foreach ($this->paths as $path => $fields) {
            $declared = data_get($schema, $path);

            if (! is_array($declared)) {
                $missing = array_merge($missing, $fields);

                continue;
            }

            $present = array_map('strval', $declared);
            $missing = array_merge($missing, array_diff($fields, $present));
        }

        return array_values(array_unique($missing));
    }

    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function isSatisfiedBy(?array $schema): bool
    {
        return $schema !== null && $schema !== [] && $this->missingFields($schema) === [];
    }

    /**
     * @return array<int, string>
     */
    private function allFields(): array
    {
        $fields = [];

        foreach ($this->paths as $declared) {
            $fields = array_merge($fields, $declared);
        }

        return array_values(array_unique($fields));
    }
}
