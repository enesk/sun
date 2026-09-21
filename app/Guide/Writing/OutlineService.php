<?php

declare(strict_types=1);

namespace App\Guide\Writing;

use App\Guide\Enums\TopicStatus;
use App\Guide\Events\OutlineLocked;
use App\Guide\Llm\Exceptions\LlmSchemaException;
use App\Guide\Llm\LlmClient;
use App\Guide\Models\Topic;
use App\Guide\Support\OutlineDraft;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Gliederungsvorschlag (#10, Stufe guide.propose_outline).
 *
 * Grundlage sind Frage, Kategorie, Notizen und das Fakten-Set, soweit es
 * schon eines gibt (bei Themen aus dem Import ohne Gliederung ist es meist
 * noch leer; dann entsteht der Vorschlag aus Frage, Kategorie und Notizen).
 *
 * Die ids vergibt der Service selbst (s1, s2, ... und s1-1, ...), nicht das
 * Modell: Sie sind ab der Sperre das Sprungziel im Artikel und die Adresse
 * abschnittsweiser Updates. Eine gesperrte Gliederung fasst der Service nie
 * an — store() wirft dann.
 *
 * Das Ausgabeschema gehoert dem Code (outputSchema()), die Vorlage liefert
 * nur den Prompt-Text.
 */
class OutlineService
{
    public const TEMPLATE = 'guide.propose_outline';

    public function __construct(
        private readonly LlmClient $client,
    ) {}

    /**
     * Schlaegt eine Gliederung vor, ohne sie zu speichern.
     *
     * @return list<array{id: string, level: int, heading: string, children: list<array{id: string, level: int, heading: string}>}>
     */
    public function propose(WritingContext $context): array
    {
        if ($context->topic->isOutlineLocked()) {
            throw new LogicException("Die Gliederung von Thema {$context->topic->getKey()} ist gesperrt und wird nicht neu vorgeschlagen.");
        }

        $result = $this->client->structured(
            WritingContext::template(self::TEMPLATE),
            $context->vars(['outline' => 'keine']),
            self::outputSchema(),
            $context->llmContext(),
        );

        return $this->normalize((array) ($result['sections'] ?? []));
    }

    /**
     * Speichert den Vorschlag. Mit $lock wird er sofort gesperrt und das
     * Thema aktiv, sonst wartet das Thema als outline_pending auf die
     * Bestaetigung im Dashboard.
     *
     * @param  list<array{id: string, level: int, heading: string, children: list<array{id: string, level: int, heading: string}>}>  $outline
     */
    public function store(Topic $topic, array $outline, bool $lock, int|string $tenantId): void
    {
        if ($topic->isOutlineLocked()) {
            throw new LogicException("Die Gliederung von Thema {$topic->getKey()} ist gesperrt und wird nicht ueberschrieben.");
        }

        $attributes = ['outline_json' => $outline];

        if ($lock) {
            $attributes['outline_locked_at'] = Carbon::now();

            if ($topic->status->canTransitionTo(TopicStatus::ACTIVE)) {
                $attributes['status'] = TopicStatus::ACTIVE;
            }
        } elseif ($topic->status !== TopicStatus::OUTLINE_PENDING && $topic->status->canTransitionTo(TopicStatus::OUTLINE_PENDING)) {
            $attributes['status'] = TopicStatus::OUTLINE_PENDING;
        }

        $topic->forceFill($attributes)->save();

        if ($lock) {
            OutlineLocked::dispatch($tenantId, (int) $topic->getKey(), false);
        }
    }

    public function autoLock(): bool
    {
        return (bool) config('guide.auto_lock_outline', false);
    }

    /**
     * Ausgabeschema der Stufe guide.propose_outline.
     *
     * @return array<string, mixed>
     */
    public static function outputSchema(): array
    {
        $child = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['id', 'level', 'heading', 'fact_keys'],
            'properties' => [
                'id' => ['type' => 'string', 'pattern' => '^s[0-9]+-[0-9]+$'],
                'level' => ['type' => 'integer', 'enum' => [3]],
                'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => OutlineDraft::MAX_HEADING_LENGTH],
                'fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['sections'],
            'properties' => [
                'sections' => [
                    'type' => 'array',
                    'minItems' => self::minH2(),
                    'maxItems' => self::maxH2(),
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['id', 'level', 'heading', 'fact_keys', 'children'],
                        'properties' => [
                            'id' => ['type' => 'string', 'pattern' => '^s[0-9]+$'],
                            'level' => ['type' => 'integer', 'enum' => [2]],
                            'heading' => ['type' => 'string', 'minLength' => 3, 'maxLength' => OutlineDraft::MAX_HEADING_LENGTH],
                            'fact_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'children' => ['type' => 'array', 'maxItems' => self::maxH3(), 'items' => $child],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function minH2(): int
    {
        return max(1, (int) config('guide.writing.outline.min_h2', 4));
    }

    public static function maxH2(): int
    {
        return max(self::minH2(), (int) config('guide.writing.outline.max_h2', 7));
    }

    public static function maxH3(): int
    {
        return max(0, (int) config('guide.writing.outline.max_h3_per_h2', OutlineDraft::MAX_H3_PER_H2));
    }

    /**
     * Form von guide_topics.outline_json mit eigenen ids; leere und doppelte
     * Ueberschriften fallen weg.
     *
     * @param  array<int, mixed>  $sections
     * @return list<array{id: string, level: int, heading: string, children: list<array{id: string, level: int, heading: string}>}>
     */
    private function normalize(array $sections): array
    {
        $outline = [];
        $seen = [];

        foreach ($sections as $section) {
            $heading = OutlineDraft::clean((string) (is_array($section) ? ($section['heading'] ?? '') : ''));

            if ($heading === '' || isset($seen[mb_strtolower($heading)])) {
                continue;
            }

            $seen[mb_strtolower($heading)] = true;
            $id = 's'.(count($outline) + 1);
            $children = [];

            foreach ((array) ($section['children'] ?? []) as $child) {
                $childHeading = OutlineDraft::clean((string) (is_array($child) ? ($child['heading'] ?? '') : ''));

                if ($childHeading === '' || isset($seen[mb_strtolower($childHeading)]) || count($children) >= self::maxH3()) {
                    continue;
                }

                $seen[mb_strtolower($childHeading)] = true;
                $children[] = ['id' => "{$id}-".(count($children) + 1), 'level' => 3, 'heading' => $childHeading];
            }

            $outline[] = ['id' => $id, 'level' => 2, 'heading' => $heading, 'children' => $children];
        }

        if (count($outline) < self::minH2() || count($outline) > self::maxH2()) {
            throw new LlmSchemaException(
                sprintf('Gliederungsvorschlag hat %d verwertbare H2, erlaubt sind %d bis %d.', count($outline), self::minH2(), self::maxH2()),
                self::TEMPLATE,
            );
        }

        return $outline;
    }
}
