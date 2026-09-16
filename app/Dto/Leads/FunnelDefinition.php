<?php

declare(strict_types=1);

namespace App\Dto\Leads;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Veroeffentlichter Funnel eines Portals (#25), gelesen aus dem oeffentlichen
 * Snapshot des Leadsystems (GET /funnels/{token}, Format siehe
 * docs/funnel-builder/snapshot-format.md im Leadsystem).
 *
 * Bedingungen stehen nur zur Information drin: welcher Schritt als naechstes
 * kommt, entscheidet das Leadsystem nach jedem Schritt selbst (Epic #23).
 */
final readonly class FunnelDefinition
{
    /**
     * @param  list<FunnelStep>  $steps
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>|null  $theme
     */
    public function __construct(
        public string $token,
        public ?int $version,
        public string $name,
        public ?int $contactStepPosition,
        public array $steps,
        public array $conditions,
        public ?array $theme,
        public CarbonImmutable $fetchedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Snapshot ohne 'data'-Huelle
     * @param  list<string>  $systemFieldKeys
     * @param  (callable(string $fieldKey, string $type): void)|null  $onUnknownType
     *
     * @throws InvalidArgumentException wenn der Snapshot keine Schritte traegt
     */
    public static function fromArray(
        string $token,
        array $payload,
        CarbonImmutable $fetchedAt,
        array $systemFieldKeys = [],
        ?callable $onUnknownType = null,
    ): self {
        $rawSteps = $payload['steps'] ?? null;

        if (! is_array($rawSteps) || $rawSteps === []) {
            throw new InvalidArgumentException('Funnel-Snapshot ohne Schritte.');
        }

        $steps = [];

        foreach (array_filter($rawSteps, 'is_array') as $rawStep) {
            $questions = [];

            foreach (array_filter((array) ($rawStep['questions'] ?? []), 'is_array') as $rawQuestion) {
                $type = (string) ($rawQuestion['type'] ?? '');

                if (! FunnelQuestion::isKnownType($type)) {
                    if ($onUnknownType !== null) {
                        $onUnknownType((string) ($rawQuestion['field_key'] ?? ''), $type);
                    }

                    continue;
                }

                $questions[] = FunnelQuestion::fromArray($rawQuestion, $systemFieldKeys);
            }

            usort($questions, static fn (FunnelQuestion $a, FunnelQuestion $b): int => $a->position <=> $b->position);

            $steps[] = new FunnelStep(
                position: (int) ($rawStep['position'] ?? 0),
                title: (string) ($rawStep['title'] ?? ''),
                description: isset($rawStep['description']) ? (string) $rawStep['description'] : null,
                questions: $questions,
            );
        }

        usort($steps, static fn (FunnelStep $a, FunnelStep $b): int => $a->position <=> $b->position);

        $funnel = (array) ($payload['funnel'] ?? []);

        return new self(
            token: $token,
            version: isset($payload['version']) ? (int) $payload['version'] : null,
            name: (string) ($funnel['name'] ?? ''),
            contactStepPosition: isset($funnel['contact_step_position']) ? (int) $funnel['contact_step_position'] : null,
            steps: $steps,
            conditions: array_values(array_filter((array) ($payload['conditions'] ?? []), 'is_array')),
            theme: is_array($payload['theme'] ?? null) ? $payload['theme'] : null,
            fetchedAt: $fetchedAt,
        );
    }

    public function step(int $position): ?FunnelStep
    {
        foreach ($this->steps as $step) {
            if ($step->position === $position) {
                return $step;
            }
        }

        return null;
    }

    public function firstStep(): FunnelStep
    {
        return $this->steps[0];
    }

    /**
     * Alle Fragen, die das Portal selbst befuellt (z. B. firmenprofil).
     *
     * @return list<FunnelQuestion>
     */
    public function systemQuestions(): array
    {
        $questions = [];

        foreach ($this->steps as $step) {
            foreach ($step->questions as $question) {
                if ($question->systemProvided) {
                    $questions[] = $question;
                }
            }
        }

        return $questions;
    }
}
