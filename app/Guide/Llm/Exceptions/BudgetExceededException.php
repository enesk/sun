<?php

declare(strict_types=1);

namespace App\Guide\Llm\Exceptions;

use RuntimeException;

/**
 * Eine Budgetgrenze aus config('guide.budget') ist fuer den laufenden Tag
 * (Europe/Berlin) erreicht (#5). Der Tag endet um 00:00 Berliner Zeit; danach
 * gibt die Summe aus llm_usage_logs den Aufruf von selbst wieder frei.
 */
class BudgetExceededException extends RuntimeException
{
    // Tagesbudget des Ratgebersystems ueber alle Tenants.
    public const SCOPE_TOTAL = 'total';

    // Tagesbudget eines Tenants.
    public const SCOPE_TENANT = 'tenant';

    // Reissleine je Lauf (guide.budget.max_usd_per_run).
    public const SCOPE_RUN = 'run';

    // Aeussere Notbremse der alten Pipeline (content.budget) bis #19.
    public const SCOPE_NETWORK = 'network';

    public function __construct(
        string $message,
        public readonly string $scope,
        public readonly float $spentUsd,
        public readonly float $limitUsd,
        public readonly ?int $tenantId = null,
        public readonly ?int $runId = null,
    ) {
        parent::__construct($message);
    }

    public static function forScope(string $scope, float $spent, float $limit, ?int $tenantId = null, ?int $runId = null): self
    {
        $amounts = sprintf('%.2f von %.2f USD', $spent, $limit);

        $message = match ($scope) {
            self::SCOPE_TOTAL => "Tagesbudget des Ratgebersystems erreicht ({$amounts}).",
            self::SCOPE_TENANT => "Tagesbudget von Tenant {$tenantId} erreicht ({$amounts}).",
            self::SCOPE_RUN => "Budget je Lauf fuer Lauf {$runId} erreicht ({$amounts}).",
            default => "Tagesbudget der Content-Pipeline erreicht ({$amounts}).",
        };

        return new self($message, $scope, $spent, $limit, $tenantId, $runId);
    }
}
