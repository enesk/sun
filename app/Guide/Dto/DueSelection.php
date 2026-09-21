<?php

declare(strict_types=1);

namespace App\Guide\Dto;

use App\Guide\Models\Topic;
use Illuminate\Support\Carbon;

/**
 * Tagesauswahl eines Tenants (#9, App\Guide\Scheduling\DueTopicSelector).
 *
 * `deferred` sind faellige Themen, die heute nicht laufen, mit Grund
 * `budget` (Tagesbudget knapp) oder `max_creates` (Neuanlage-Grenze je Tag).
 */
final class DueSelection
{
    public const REASON_BUDGET = 'budget';

    public const REASON_MAX_CREATES = 'max_creates';

    /**
     * @param  array<int, Topic>  $selected  in Laufreihenfolge
     * @param  array<int, array{topic: Topic, reason: string}>  $deferred
     */
    public function __construct(
        public readonly Carbon $date,
        public readonly array $selected,
        public readonly array $deferred,
        public readonly bool $budgetScarce,
        public readonly ?float $remainingUsd,
        public readonly float $estimatedUsd,
    ) {}

    public function isEmpty(): bool
    {
        return $this->selected === [];
    }
}
