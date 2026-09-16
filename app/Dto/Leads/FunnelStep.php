<?php

declare(strict_types=1);

namespace App\Dto\Leads;

/**
 * Schritt eines Funnels (#25), adressiert ueber seine Position.
 */
final readonly class FunnelStep
{
    /**
     * @param  list<FunnelQuestion>  $questions
     */
    public function __construct(
        public int $position,
        public string $title,
        public ?string $description,
        public array $questions,
    ) {}

    /**
     * Fragen, die der Besucher sieht — ohne die vom Portal gesetzten Felder.
     *
     * @return list<FunnelQuestion>
     */
    public function visibleQuestions(): array
    {
        return array_values(array_filter(
            $this->questions,
            static fn (FunnelQuestion $question): bool => ! $question->systemProvided,
        ));
    }
}
